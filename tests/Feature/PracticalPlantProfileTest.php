<?php

namespace Tests\Feature;

use App\Mail\DailyCareDigest;
use App\Models\Plant;
use App\Models\PlantHandoff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PracticalPlantProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_does_not_hydrate_plants_without_any_schedule(): void
    {
        $owner = User::factory()->create();
        $manualPlant = $owner->plants()->create(['name' => 'Manual fern', 'room' => 'Kitchen']);
        $scheduledPlant = $owner->plants()->create(['name' => 'Scheduled ivy', 'created_at' => now()->subDays(5)]);
        $scheduledPlant->needs()->create(['watering_frequency' => 3, 'fertilizing_frequency' => 0]);
        $loaded = [];
        Plant::retrieved(function (Plant $plant) use (&$loaded): void {
            $loaded[] = $plant->id;
        });

        $this->actingAs($owner)->get(route('care.today'))->assertOk()->assertSee('Scheduled ivy')->assertSee('Kitchen');
        $this->assertContains($scheduledPlant->id, $loaded);
        $this->assertNotContains($manualPlant->id, $loaded);
    }

    public function test_new_plant_only_needs_a_name_and_has_no_automatic_care_actions(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('add-plant-query'), ['name' => 'Parlor fern'])
            ->assertRedirect(route('browse'));

        $plant = $owner->plants()->sole();
        $this->assertSame(0, (int) $plant->needs->watering_frequency);
        $this->assertNull($plant->nextWatering());
        $this->assertNull($plant->nextFertilizing());
    }

    public function test_optional_context_is_visible_and_the_garden_can_be_filtered_by_room(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $other->plants()->create(['name' => 'Private succulent', 'room' => 'Bedroom']);
        $owner->plants()->create(['name' => 'Kitchen ivy', 'room' => 'Kitchen']);

        $this->actingAs($owner)->post(route('add-plant-query'), [
            'name' => 'Parlor fern', 'room' => 'Bedroom', 'species_name' => 'Nephrolepis',
            'care_instructions' => 'Compare the weight of the pot when checking the soil.',
        ])->assertRedirect(route('browse'));
        $plant = $owner->plants()->where('name', 'Parlor fern')->sole();

        $this->get(route('browse', ['room' => 'Bedroom']))->assertOk()
            ->assertSee('Parlor fern')->assertDontSee('Kitchen ivy')->assertDontSee('Private succulent');
        $this->get(route('plants', $plant))->assertOk()
            ->assertSee('Nephrolepis')->assertSee('Compare the weight of the pot');
    }

    public function test_a_feeding_entry_can_record_the_product_and_dose_without_scheduling_feeding(): void
    {
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Peace lily']);
        $this->actingAs($owner)->post(route('care-logs.store', $plant), [
            'type' => 'fertilizing', 'care_date' => today()->toDateString(),
            'fertilizer' => 'Houseplant feed', 'dose' => 'Half label strength',
        ])->assertRedirect(route('plants', $plant));

        $this->assertDatabaseHas('care_logs', [
            'plant_id' => $plant->id, 'type' => 'fertilizing',
            'fertilizer' => 'Houseplant feed', 'dose' => 'Half label strength',
        ]);
        $this->get(route('plants', $plant))->assertOk()->assertSee('Houseplant feed')->assertSee('Half label strength');
        $this->assertNull($plant->fresh()->nextFertilizing());
    }

    public function test_today_groups_and_filters_soil_checks_by_room(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(12, 0));
        $owner = User::factory()->create();
        $this->actingAs($owner);
        foreach (['Bedroom' => 'Bedroom fern', 'Kitchen' => 'Kitchen ficus'] as $room => $name) {
            $this->post(route('add-plant-query'), [
                'name' => $name, 'room' => $room, 'first_check_on' => '2026-11-21',
            ])->assertRedirect(route('browse'));
        }

        $this->get(route('care.today'))->assertOk()->assertSee('Bedroom fern')->assertSee('Kitchen ficus')
            ->assertSee('Check soil')->assertSee('Still moist');
        $this->get(route('care.today', ['room' => 'Bedroom']))->assertOk()
            ->assertSee('Bedroom fern')->assertDontSee('Kitchen ficus');
    }

    public function test_due_checks_are_sent_as_inspections_without_watering_instructions(): void
    {
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(12, 0));
        $owner = User::factory()->create(['reminder_time' => '10:00', 'reminder_timezone' => 'UTC']);
        $this->actingAs($owner)->post(route('add-plant-query'), [
            'name' => 'Sunny monstera', 'first_check_on' => '2026-11-21',
        ])->assertRedirect(route('browse'));

        $this->artisan('email:care')->assertSuccessful();
        Mail::assertSent(DailyCareDigest::class, function (DailyCareDigest $digest) use ($owner) {
            $digest->assertSeeInHtml('Sunny monstera');
            $digest->assertSeeInHtml('Check soil');
            $digest->assertDontSeeInHtml('needs watering');

            return $digest->hasTo($owner->email);
        });
    }

    public function test_a_plant_without_reminders_can_be_checked_manually_without_enabling_them(): void
    {
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Manual fern']);
        $this->actingAs($owner)->get(route('plants', $plant))->assertOk()->assertSee('Still moist');
        $this->post(route('plant-checks.store', $plant), [
            'outcome' => 'still_moist', 'schedule_version' => 0,
        ])->assertSessionHas('plant_check_status');
        $check = $plant->plantChecks()->sole();
        $this->assertNull($check->scheduled_for);
        $this->assertFalse($plant->checkSchedule()->sole()->enabled);
        $this->assertDatabaseCount('care_logs', 0);
        $this->delete(route('plant-checks.undo', [$plant, $check]))->assertSessionHas('plant_check_status');
        $this->assertNotNull($check->fresh()->voided_at);
    }

    public function test_handoff_reminders_include_only_selected_plants_during_the_grant(): void
    {
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(12, 0));
        $owner = User::factory()->create(['reminders_enabled' => false]);
        $helper = User::factory()->create(['reminder_time' => '10:00', 'reminder_timezone' => 'UTC']);
        $this->actingAs($owner);
        foreach (['Handed-off fern', 'Private cactus'] as $name) {
            $this->post(route('add-plant-query'), ['name' => $name, 'first_check_on' => '2026-11-21'])->assertRedirect();
        }
        $handoff = PlantHandoff::query()->create([
            'owner_id' => $owner->id, 'email' => $helper->email, 'token_hash' => hash('sha256', 'test-handoff'),
            'timezone' => 'UTC', 'starts_at' => now()->startOfDay(), 'ends_at' => now()->addDay()->startOfDay(),
        ]);
        $handoff->forceFill(['member_id' => $helper->id, 'accepted_at' => now()])->save();
        $handoff->plants()->attach($owner->plants()->where('name', 'Handed-off fern')->sole());

        $this->artisan('email:care')->assertSuccessful();
        Mail::assertSent(DailyCareDigest::class, function (DailyCareDigest $digest) use ($helper) {
            $digest->assertSeeInHtml('Handed-off fern');
            $digest->assertDontSeeInHtml('Private cactus');

            return $digest->hasTo($helper->email);
        });

        Mail::fake();
        $this->travel(1)->days();
        $this->artisan('email:care')->assertSuccessful();
        Mail::assertNothingSent();
    }
}
