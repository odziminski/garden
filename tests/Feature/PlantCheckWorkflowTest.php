<?php

namespace Tests\Feature;

use App\Models\GardenMembership;
use App\Models\Plant;
use App\Models\PlantCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PlantCheckWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_watering_is_preserved_until_an_owner_enables_checks(): void
    {
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $plant = $this->plant($owner);
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(23, 30));
        $this->assertNull($plant->checkSchedule);
        $this->assertSame(7, $plant->needs->watering_frequency);

        $this->actingAs($owner)->patch(route('plant-checks.schedule', $plant), [
            'enabled' => '1', 'use_legacy_interval' => '1',
        ])->assertRedirect();

        $schedule = $plant->checkSchedule()->sole();
        $this->assertTrue($schedule->enabled);
        $this->assertSame(7, $schedule->interval_days);
        $this->assertSame(7, $schedule->legacy_watering_frequency);
        $this->assertSame('2026-11-22', $schedule->next_check_on);
        $this->assertSame(0, $plant->needs()->sole()->watering_frequency);

        $this->patch(route('plant-checks.schedule', $plant), ['enabled' => '0'])->assertRedirect();
        $this->assertFalse($schedule->fresh()->enabled);
        $this->assertSame(0, $plant->needs()->sole()->watering_frequency);
    }

    public function test_skipping_defers_without_recording_an_observation_and_can_be_undone_once(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21)->startOfDay());
        $owner = User::factory()->create();
        $plant = $this->plant($owner);
        $this->actingAs($owner)->patch(route('plant-checks.schedule', $plant), ['enabled' => '1'])->assertRedirect();
        $schedule = $plant->checkSchedule()->sole();
        $details = ['outcome' => 'skipped', 'schedule_version' => $schedule->version];

        $this->post(route('plant-checks.store', $plant), $details)->assertRedirect();
        $this->post(route('plant-checks.store', $plant), $details)->assertRedirect();
        $this->assertSame('2026-11-22', $schedule->refresh()->next_check_on);
        $this->assertDatabaseCount('plant_checks', 0);
        $this->assertDatabaseCount('care_logs', 0);

        $skipVersion = $schedule->version;
        $this->delete(route('plant-checks.undo-skip', $plant), ['schedule_version' => $skipVersion])->assertRedirect();
        $this->delete(route('plant-checks.undo-skip', $plant), ['schedule_version' => $skipVersion])
            ->assertSessionHas('plant_check_notice');
        $this->post(route('plant-checks.store', $plant), $details)->assertSessionHas('plant_check_notice');
        $this->assertSame('2026-11-21', $schedule->fresh()->next_check_on);
        $this->assertNull($plant->latestPlantCheck()->first());
    }

    public function test_moist_check_records_the_observation_and_allows_explicit_follow_up_without_water(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21)->startOfDay());
        $owner = User::factory()->create();
        $plant = $this->plant($owner);
        $this->actingAs($owner)->patch(route('plant-checks.schedule', $plant), ['enabled' => '1'])->assertRedirect();
        $schedule = $plant->checkSchedule()->sole();

        $this->post(route('plant-checks.store', $plant), [
            'outcome' => 'still_moist', 'schedule_version' => $schedule->version,
            'next_check_on' => '2026-11-25', 'note' => 'Soil damp below the surface.',
        ])->assertRedirect();

        $this->assertSame('2026-11-25', $schedule->fresh()->next_check_on);
        $this->assertSame('Soil damp below the surface.', $plant->latestPlantCheck()->sole()->note);
        $this->assertDatabaseCount('care_logs', 0);
        $this->assertSame('2026-11-10', $plant->history()->sole()->watered_at->toDateString());
    }

    public function test_watered_check_and_undo_run_through_the_actual_care_history(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(11, 30));
        $owner = User::factory()->create();
        $plant = $this->plant($owner);
        $this->actingAs($owner)->patch(route('plant-checks.schedule', $plant), ['enabled' => '1'])->assertRedirect();
        $schedule = $plant->checkSchedule()->sole();
        $details = ['outcome' => 'watered', 'schedule_version' => $schedule->version];

        $this->post(route('plant-checks.store', $plant), $details)->assertRedirect();
        $this->post(route('plant-checks.store', $plant), $details)->assertRedirect();
        $check = $plant->latestPlantCheck()->sole();
        $activity = $plant->careLogs()->sole();
        $this->assertSame($activity->id, $check->care_log_id);
        $this->assertSame('2026-11-21', $plant->history()->sole()->watered_on);
        $this->assertNull($schedule->fresh()->next_check_on);
        $this->delete(route('care-logs.undo', [$plant, $activity]))->assertStatus(409);
        $this->get(route('care-logs.edit', [$plant, $activity]))->assertStatus(409);
        $this->assertNull($activity->fresh()->voided_at);

        $this->delete(route('plant-checks.undo', [$plant, $check]))->assertRedirect();
        $this->assertNotNull($check->fresh()->voided_at);
        $this->assertNotNull($activity->fresh()->voided_at);
        $this->assertSame('2026-11-10', $plant->history()->sole()->watered_at->toDateString());
        $this->assertSame('2026-11-21', $schedule->fresh()->next_check_on);
    }

    public function test_check_scope_prevents_unshared_plant_access_and_stale_undo(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21)->startOfDay());
        $owner = User::factory()->create();
        $plant = $this->plant($owner);
        $this->actingAs($owner)->patch(route('plant-checks.schedule', $plant), ['enabled' => '1'])->assertRedirect();
        $schedule = $plant->checkSchedule()->sole();
        $details = ['outcome' => 'still_moist', 'schedule_version' => $schedule->version];

        $this->actingAs(User::factory()->create())->post(route('plant-checks.store', $plant), $details)->assertNotFound();
        $this->actingAs($owner)->post(route('plant-checks.store', $plant), $details)->assertRedirect();
        $check = $plant->plantChecks()->sole();
        $this->patch(route('plant-checks.schedule', $plant), [
            'enabled' => '1', 'next_check_on' => '2026-11-26',
        ])->assertRedirect();

        $this->delete(route('plant-checks.undo', [$plant, $check]))->assertSessionHas('plant_check_notice');
        $this->assertNull($check->fresh()->voided_at);
        $this->assertSame('2026-11-26', $schedule->fresh()->next_check_on);
    }

    public function test_a_plant_scoped_collaborator_can_record_and_undo_but_not_configure_checks(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21)->startOfDay());
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $plant = $this->plant($owner);
        $otherPlant = $this->plant($owner);
        GardenMembership::query()->create(['owner_id' => $owner->id, 'member_id' => $collaborator->id, 'plant_id' => $plant->id]);

        $this->actingAs($owner)->patch(route('plant-checks.schedule', $plant), ['enabled' => '1'])->assertRedirect();
        $schedule = $plant->checkSchedule()->sole();
        $details = ['outcome' => 'watered', 'schedule_version' => $schedule->version];

        $this->actingAs($collaborator)->patch(route('plant-checks.schedule', $plant), ['enabled' => '0'])->assertNotFound();
        $this->post(route('plant-checks.store', $otherPlant), $details)->assertNotFound();
        $this->post(route('plant-checks.store', $plant), $details)->assertRedirect();
        $check = $plant->latestPlantCheck()->sole();
        $this->assertTrue($check->checkedBy->is($collaborator));
        $this->delete(route('plant-checks.undo', [$plant, $check]))->assertRedirect();
        $this->assertNull($plant->latestPlantCheck()->first());
    }

    public function test_an_old_watered_check_can_be_removed_without_rescheduling_newer_checks(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21)->startOfDay());
        $owner = User::factory()->create();
        $plant = $this->plant($owner);
        $this->actingAs($owner)->patch(route('plant-checks.schedule', $plant), ['enabled' => '1'])->assertRedirect();
        $schedule = $plant->checkSchedule()->sole();
        $checks = route('plant-checks.store', $plant);
        $this->post($checks, ['outcome' => 'watered', 'schedule_version' => $schedule->version])->assertRedirect();
        $oldCheck = $plant->plantChecks()->sole();
        $this->patch(route('plant-checks.schedule', $plant), [
            'enabled' => '1', 'next_check_on' => '2026-11-26',
        ])->assertRedirect();

        $this->get(route('plants', $plant))->assertOk()->assertSee('Remove from check history');
        $this->delete(route('plant-checks.undo', [$plant, $oldCheck]), ['history_only' => '1'])
            ->assertSessionHas('plant_check_status');

        $this->assertNotNull($oldCheck->fresh()->voided_at);
        $this->assertNotNull($oldCheck->careLog()->sole()->voided_at);
        $this->assertSame('2026-11-26', $schedule->fresh()->next_check_on);
        $this->assertSame('2026-11-10', $plant->history()->sole()->watered_at->toDateString());
    }

    public function test_a_failed_check_insert_rolls_back_the_linked_watering_entry_and_history(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 21)->startOfDay());
        $owner = User::factory()->create();
        $plant = $this->plant($owner);
        $this->actingAs($owner)->patch(route('plant-checks.schedule', $plant), ['enabled' => '1'])->assertRedirect();
        $schedule = $plant->checkSchedule()->sole();
        PlantCheck::creating(fn () => throw new RuntimeException('Check insert failed.'));

        $this->post(route('plant-checks.store', $plant), [
            'outcome' => 'watered', 'schedule_version' => $schedule->version,
        ])->assertServerError();

        $this->assertDatabaseCount('plant_checks', 0);
        $this->assertDatabaseCount('care_logs', 0);
        $this->assertSame('2026-11-10', $plant->history()->sole()->watered_at->toDateString());
        $this->assertSame($schedule->version, $schedule->fresh()->version);
    }

    private function plant(User $owner): Plant
    {
        $plant = $owner->plants()->create(['name' => 'Pot fern']);
        $plant->needs()->create(['watering_frequency' => 7, 'fertilizing_frequency' => 0]);
        $plant->history()->create(['watered_at' => '2026-11-10 10:00:00']);

        return $plant;
    }
}
