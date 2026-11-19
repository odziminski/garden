<?php

namespace Tests\Feature;

use App\Mail\DailyCareDigest;
use App\Models\Plant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $first = $this->createPlant($owner, 'Fern', 10);
        $second = $this->createPlant($owner, 'Ivy', 4);
        $upcoming = $this->createPlant($owner, 'Poppy', 1);
        $other = User::factory()->create();
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
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'));
        Mail::fake();

        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
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
        $this->assertDatabaseHas('needs', ['plant_id' => $noLongerDue->id, 'need_watering' => false]);
        $this->assertDatabaseHas('needs', ['plant_id' => $newlyDue->id, 'need_watering' => true]);
    }

    public function test_failed_delivery_releases_the_daily_claim_for_a_retry(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $plant = $this->createPlant($owner, 'Fern', 10);
        $plant->needs()->update(['need_watering' => true]);

        Mail::shouldReceive('getDefaultDriver')->andReturn('array');
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP temporarily unavailable'));
        $this->artisan('email:care')->assertFailed();
        $this->assertNull($owner->fresh()->reminder_sent_on);

        Mail::fake();
        $this->artisan('email:care')->assertSuccessful();
        Mail::assertSent(DailyCareDigest::class, fn ($mail) => $mail->hasTo($owner->email));
        $this->assertSame('2026-11-20', $owner->fresh()->reminder_sent_on);
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
