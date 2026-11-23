<?php

namespace Tests\Feature;

use App\Contracts\CarePushDelivery;
use App\Mail\DailyCareDigest;
use App\Models\Plant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class PlantCareTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_plants_are_grouped_in_one_reminder_per_owner_and_local_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:10:00', 'UTC'));
        Mail::fake();

        $owner = User::factory()->create(['reminders_enabled' => true, 'reminder_timezone' => 'Europe/Warsaw']);
        $first = $this->createPlant($owner, 'Fern', 10);
        $second = $this->createPlant($owner, 'Ivy', 4);
        $upcoming = $this->createPlant($owner, 'Poppy', 1);
        $other = User::factory()->create(['reminders_enabled' => true]);
        $this->createPlant($other, 'Clover', 10);
        $disabled = User::factory()->create(['reminders_enabled' => false]);
        $this->createPlant($disabled, 'Sage', 10);

        $this->artisan('checkForWatering')->assertSuccessful();
        $this->artisan('checkForFertilizing')->assertSuccessful();
        $this->assertDatabaseHas('needs', [
            'plant_id' => $first->id,
            'need_watering' => true,
            'need_fertilizing' => true,
        ]);
        $this->assertDatabaseHas('needs', [
            'plant_id' => $upcoming->id,
            'need_watering' => false,
            'need_fertilizing' => false,
        ]);

        $this->artisan('email:care')->assertSuccessful();
        $this->artisan('email:care')->assertSuccessful();
        Mail::assertSentCount(1);
        Mail::assertSent(DailyCareDigest::class, fn ($mail) => $mail->hasTo($owner->email)
            && $mail->plants->modelKeys() === [$first->id, $second->id]);

        $this->travelTo(now()->setTime(10, 10));
        $this->artisan('email:care')->assertSuccessful();
        Mail::assertSentCount(2);
        Mail::assertSent(DailyCareDigest::class, fn ($mail) => $mail->hasTo($other->email));
        Mail::assertNotSent(DailyCareDigest::class, fn ($mail) => $mail->hasTo($disabled->email));

        $digest = new DailyCareDigest(Plant::with('needs')->whereKey([$first->id, $second->id])->get());
        $digest->assertSeeInHtml($first->name);
        $digest->assertSeeInHtml($second->name);
        $digest->assertDontSeeInHtml($upcoming->name);
    }

    public function test_digest_recalculates_due_actions_before_sending(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));
        Mail::fake();

        $owner = User::factory()->create(['reminders_enabled' => true, 'reminder_timezone' => 'Europe/Warsaw']);
        $noLongerDue = $this->createPlant($owner, 'Ivy', 3);
        $noLongerDue->needs()->update([
            'summer_watering_frequency' => 2,
            'winter_watering_frequency' => 7,
            'need_watering' => true,
        ]);
        $newlyDue = $this->createPlant($owner, 'Fern', 3);
        $newlyDue->needs()->update([
            'summer_watering_frequency' => 7,
            'winter_watering_frequency' => 2,
        ]);

        $this->artisan('email:care')->assertSuccessful();

        Mail::assertSentCount(1);
        Mail::assertSent(DailyCareDigest::class, fn ($mail) => $mail->hasTo($owner->email)
            && $mail->plants->modelKeys() === [$newlyDue->id]);
        $this->assertDatabaseHas('needs', ['plant_id' => $noLongerDue->id, 'need_watering' => true]);
        $this->assertDatabaseHas('needs', ['plant_id' => $newlyDue->id, 'need_watering' => false]);
    }

    public function test_failed_delivery_is_retried_after_the_backoff(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        $owner = User::factory()->create(['reminders_enabled' => true]);
        $plant = $this->createPlant($owner, 'Fern', 10);
        $plant->needs()->update(['need_watering' => true]);

        Mail::shouldReceive('getDefaultDriver')->andReturn('array');
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP temporarily unavailable'));
        $this->artisan('email:care')->assertFailed();
        $this->assertNull($owner->fresh()->reminder_sent_on);

        Mail::fake();
        $this->artisan('email:care')->assertSuccessful();
        Mail::assertNothingSent();

        $this->travel(5)->minutes();
        $this->artisan('email:care --pending-only')->assertSuccessful();
        Mail::assertSent(DailyCareDigest::class, fn ($mail) => $mail->hasTo($owner->email));
        $this->assertSame('2026-11-20', $owner->fresh()->reminder_sent_on);
    }

    public function test_expired_delivery_lease_can_be_reclaimed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        Mail::fake();
        $owner = User::factory()->create(['reminders_enabled' => true]);
        $this->createPlant($owner, 'Fern', 10);

        $this->artisan('email:care --discover-only')->assertSuccessful();
        Mail::assertNothingSent();
        DB::table('care_reminder_deliveries')->where('user_id', $owner->id)->update([
            'attempts' => 1,
            'available_at' => now()->addMinutes(5),
        ]);

        $this->artisan('email:care --pending-only')->assertSuccessful();
        Mail::assertNothingSent();

        $this->travel(5)->minutes();
        $this->artisan('email:care --pending-only')->assertSuccessful();
        Mail::assertSent(DailyCareDigest::class, fn ($mail) => $mail->hasTo($owner->email));
    }

    public function test_a_cancelled_reminder_can_be_queued_again_if_care_becomes_due_the_same_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        Mail::fake();
        $owner = User::factory()->create(['reminders_enabled' => true]);
        $plant = $this->createPlant($owner, 'Fern', 10);

        $this->artisan('email:care --discover-only')->assertSuccessful();
        $plant->needs()->update(['watering_frequency' => 0, 'fertilizing_frequency' => 0]);
        $this->artisan('email:care --pending-only')->assertSuccessful();
        $this->assertDatabaseHas('care_reminder_deliveries', [
            'user_id' => $owner->id, 'status' => 'cancelled',
        ]);
        Mail::assertNothingSent();

        $plant->careTasks()->create(['name' => 'Inspect roots', 'due_on' => '2026-11-20']);
        $this->artisan('email:care')->assertSuccessful();
        $this->artisan('email:care')->assertSuccessful();
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('care_reminder_deliveries', [
            'user_id' => $owner->id, 'status' => 'sent', 'attempts' => 1,
        ]);
    }

    public function test_unconfigured_browser_push_does_not_queue_deliveries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        $owner = User::factory()->create(['reminders_enabled' => false, 'push_reminders_enabled' => true]);
        $this->createPlant($owner, 'Fern', 10);

        $this->artisan('email:care')->assertSuccessful();
        $this->assertDatabaseMissing('care_reminder_deliveries', ['user_id' => $owner->id]);
    }

    public function test_an_unverified_address_does_not_receive_a_queued_reminder(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        Mail::fake();
        $owner = User::factory()->create(['reminders_enabled' => true]);
        $unverified = User::factory()->create(['reminders_enabled' => true, 'email_verified_at' => null]);
        $this->createPlant($owner, 'Fern', 10);
        $this->createPlant($unverified, 'Ivy', 10);

        $this->artisan('email:care --discover-only')->assertSuccessful();
        $this->assertDatabaseMissing('care_reminder_deliveries', ['user_id' => $unverified->id]);
        $owner->forceFill(['email_verified_at' => null])->save();

        $this->artisan('email:care --pending-only')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_scheduler_health_requires_fresh_discovery_and_delivery_heartbeats(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        $this->artisan('care:status')->assertFailed();
        $this->artisan('email:care --discover-only --scheduled')->assertSuccessful();
        $this->artisan('email:care --pending-only --scheduled')->assertSuccessful();
        $this->artisan('care:status')->assertSuccessful();

        $this->travel(11)->minutes();
        $this->artisan('care:status')->assertFailed();
    }

    public function test_reminder_delivery_stops_after_five_failed_attempts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        $owner = User::factory()->create(['reminders_enabled' => true]);
        $this->createPlant($owner, 'Fern', 10);
        Mail::shouldReceive('getDefaultDriver')->andReturn('array');
        Mail::shouldReceive('to')->times(5)->andThrow(new RuntimeException('SMTP temporarily unavailable'));

        $this->artisan('email:care')->assertFailed();
        foreach ([5, 15, 30, 60] as $minutes) {
            $this->travel($minutes)->minutes();
            $this->artisan('email:care --pending-only')->assertFailed();
        }
        $this->assertDatabaseHas('care_reminder_deliveries', ['user_id' => $owner->id, 'status' => 'failed']);

        $this->travel(60)->minutes();
        $this->artisan('email:care')->assertSuccessful();
        $this->assertNull($owner->fresh()->reminder_sent_on);
    }

    public function test_a_caretaker_receives_only_their_assigned_due_tasks_from_shared_gardens(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        Mail::fake();
        $owner = User::factory()->create(['reminders_enabled' => false]);
        $member = User::factory()->create(['reminders_enabled' => true]);
        DB::table('garden_memberships')->insert(['owner_id' => $owner->id, 'member_id' => $member->id]);
        $plant = $this->createPlant($owner, 'Fern', 10);
        $plant->careTasks()->create(['name' => 'Inspect roots', 'due_on' => '2026-11-20'])
            ->forceFill(['assignee_id' => $member->id])->save();
        $plant->careTasks()->create(['name' => 'Clean leaves', 'due_on' => '2026-11-20']);
        $plant->careTasks()->create(['name' => 'Trim stem', 'due_on' => '2026-11-20'])
            ->forceFill(['assignee_id' => $member->id, 'snoozed_until' => '2026-11-21'])->save();

        $this->artisan('email:care')->assertSuccessful();
        Mail::assertSentCount(1);
        $digest = Mail::sent(DailyCareDigest::class)->sole();
        $this->assertTrue($digest->hasTo($member->email));
        $digest->assertSeeInHtml('Inspect roots');
        $digest->assertDontSeeInHtml('Clean leaves');
        $digest->assertDontSeeInHtml('Trim stem');
        $digest->assertDontSeeInHtml('needs watering');
    }

    public function test_browser_push_is_delivered_independently_of_email_opt_in(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        Mail::fake();
        $push = new class implements CarePushDelivery
        {
            public array $deliveries = [];

            public function send(User $recipient, Collection $plants): void
            {
                $this->deliveries[] = [$recipient->id, $plants->modelKeys()];
            }
        };
        $this->app->instance(CarePushDelivery::class, $push);
        $owner = User::factory()->create(['reminders_enabled' => false, 'push_reminders_enabled' => true]);
        $fern = $this->createPlant($owner, 'Fern', 10);

        $this->artisan('email:care')->assertSuccessful();
        $this->artisan('email:care')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame([[$owner->id, [$fern->id]]], $push->deliveries);
    }

    private function createPlant(User $owner, string $name, int $daysSinceCare): Plant
    {
        $plant = $owner->plants()->create(['name' => $name]);
        $plant->history()->create([
            'watered_at' => now()->subDays($daysSinceCare),
            'fertilized_at' => now()->subDays($daysSinceCare),
        ]);
        $plant->needs()->create(['watering_frequency' => 3, 'fertilizing_frequency' => 10]);

        return $plant;
    }
}
