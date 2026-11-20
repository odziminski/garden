<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_backdating_appends_a_note_without_moving_last_care_backwards(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 10)->setTime(12, 0));
        $owner = User::factory()->create();
        $plant = $this->createPlant($owner, 'Kitchen fern', '2026-07-04 08:00:00');
        $detail = route('plants', $plant);
        $this->actingAs($owner);

        $this->from($detail)->post(route('updateWatering', $plant))->assertRedirect($detail);
        $this->post(route('care-logs.store', $plant), [
            'type' => 'watering',
            'care_date' => '2026-07-06',
            'note' => 'Watered after repotting.',
        ])->assertRedirect($detail);

        $this->assertDatabaseHas('history', [
            'plant_id' => $plant->id,
            'watered_at' => '2026-07-10 12:00:00',
            'fertilized_at' => '2026-07-04 08:00:00',
        ]);
        $this->assertDatabaseHas('care_logs', [
            'plant_id' => $plant->id,
            'type' => 'watering',
            'performed_at' => '2026-07-06 00:00:00',
            'note' => 'Watered after repotting.',
        ]);
        $this->assertDatabaseCount('care_logs', 2);
        $this->get($detail)->assertOk()->assertSeeInOrder(['Jul 10, 2026', 'Jul 6, 2026', 'Watered after repotting.']);
    }

    public function test_care_entry_rejects_future_dates_and_nonowners(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 10)->setTime(12, 0));
        $owner = User::factory()->create();
        $plant = $this->createPlant($owner, 'Private ivy', '2026-07-04 08:00:00');
        $endpoint = route('care-logs.store', $plant);

        $this->actingAs(User::factory()->create())->post($endpoint, [
            'type' => 'fertilizing',
            'care_date' => '2026-07-10',
        ])->assertNotFound();

        $this->actingAs($owner)->postJson($endpoint, [
            'type' => 'repotting',
            'care_date' => '2026-07-11',
            'note' => str_repeat('a', 1001),
        ])->assertUnprocessable()->assertJsonValidationErrors(['type', 'care_date', 'note']);

        $this->assertDatabaseCount('care_logs', 0);
    }

    public function test_an_explicit_care_date_stays_the_same_after_changing_time_zone(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 12)->setTime(12, 0, 0));
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $plant = $this->createPlant($owner, 'Calendar fern', '2026-07-04 08:00:00');
        $detail = route('plants', $plant);

        $this->actingAs($owner)->post(route('care-logs.store', $plant), [
            'type' => 'watering', 'care_date' => '2026-07-10',
        ])->assertRedirect($detail);

        $this->put(route('reminders.update'), [
            'reminders_enabled' => false, 'reminder_timezone' => 'UTC', 'reminder_time' => '09:00',
        ])->assertRedirect(route('users'));

        $this->get($detail)->assertOk()->assertSee('Jul 10, 2026')->assertDontSee('Jul 9, 2026');
        $this->get(route('care.today'))->assertOk()->assertSee('You’re all caught up')->assertSee('Jul 13, 2026');
        $this->assertSame('2026-07-13', $plant->fresh()->nextWatering()->toDateString());
        $this->assertDatabaseHas('history', ['plant_id' => $plant->id, 'watered_at' => '2026-07-09 22:00:00']);
    }

    public function test_care_keeps_the_exact_entered_date_across_a_skipped_local_day(): void
    {
        $this->travelTo(now()->setDate(2012, 1, 1)->setTime(12, 0, 0));
        $owner = User::factory()->create(['reminder_timezone' => 'Pacific/Apia']);
        $plant = $this->createPlant($owner, 'Calendar fern', '2011-12-20 08:00:00');

        $this->actingAs($owner)->post(route('care-logs.store', $plant), [
            'type' => 'watering', 'care_date' => '2011-12-30',
        ])->assertRedirect(route('plants', $plant));

        $this->assertDatabaseHas('care_logs', ['plant_id' => $plant->id, 'performed_on' => '2011-12-30']);
        $this->assertSame('2012-01-02', $plant->fresh()->nextWatering()->toDateString());
    }

    public function test_invalid_care_form_arrays_do_not_break_the_redirected_detail_page(): void
    {
        $owner = User::factory()->create();
        $plant = $this->createPlant($owner, 'Calendar fern', '2026-07-04 08:00:00');
        $detail = route('plants', $plant);

        $this->actingAs($owner)->from($detail)->post(route('care-logs.store', $plant), [
            'type' => 'watering', 'care_date' => ['invalid'], 'note' => ['invalid'],
        ])->assertRedirect($detail)->assertSessionHasErrorsIn('care', ['care_date', 'note']);

        $this->get($detail)->assertOk();
    }

    public function test_invalid_plant_form_arrays_and_explicitly_blank_intervals_remain_editable(): void
    {
        $owner = User::factory()->create();
        $plant = $this->createPlant($owner, 'Calendar fern', '2026-07-04 08:00:00');
        $plant->needs()->update(['summer_watering_frequency' => 15]);
        $edit = route('displayEditPlant', $plant);

        $this->actingAs($owner)->from($edit)->patch(route('editPlant', $plant), [
            'name' => ['invalid'],
            'watering_frequency' => ['invalid'],
            'fertilizing_frequency' => 20,
            'summer_watering_frequency' => '',
        ])->assertRedirect($edit)->assertSessionHasErrors(['name', 'watering_frequency']);

        $this->get($edit)->assertOk()->assertDontSee('value="15"', false);
    }

    public function test_an_out_of_range_care_page_links_back_to_newest_entries(): void
    {
        $owner = User::factory()->create();
        $plant = $this->createPlant($owner, 'Calendar fern', '2026-07-04 08:00:00');

        $this->actingAs($owner)->get(route('plants', [$plant, 'care_page' => 500]))
            ->assertOk()->assertSee('care_page=1#care-history');
    }

    public function test_today_shows_only_the_owners_due_and_overdue_actions(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 10)->setTime(12, 0));
        $owner = User::factory()->create();
        $overdue = $this->createPlant($owner, 'Overdue violet', '2026-07-03 15:00:00');
        $today = $this->createPlant($owner, 'Due today fern', '2026-07-07 15:00:00');
        $upcoming = $this->createPlant($owner, 'Upcoming ficus', '2026-07-09 15:00:00');
        $upcoming->needs()->update(['need_watering' => true]);
        $this->createPlant(User::factory()->create(), 'Private plant', '2026-07-01 15:00:00');

        $this->get(route('care.today'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('care.today'))->assertOk()
            ->assertSeeInOrder(['Overdue violet', 'Due today fern'])
            ->assertSee(route('updateWatering', $overdue))
            ->assertSee(route('updateWatering', $today))
            ->assertDontSee(route('updateFertilizing', $today))
            ->assertDontSee('Upcoming ficus')
            ->assertDontSee('Private plant');

        $this->from(route('care.today'))->post(route('updateWatering', $today))->assertRedirect(route('care.today'));
        $this->get(route('care.today'))->assertOk()->assertDontSee('Due today fern');
        $this->assertDatabaseHas('care_logs', ['plant_id' => $today->id, 'type' => 'watering']);
    }

    public function test_new_plants_can_leave_all_care_reminders_disabled(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('add-plant'))->assertOk()->assertDontSee('Seasonal options');
        $this->post(route('add-plant-query'), ['name' => 'Rain lily'])->assertRedirect(route('browse'));
        $plant = $owner->plants()->sole();

        $this->assertDatabaseHas('needs', [
            'plant_id' => $plant->id, 'watering_frequency' => 0, 'fertilizing_frequency' => 0,
        ]);
        $this->assertNull($plant->nextWatering());
        $this->assertNull($plant->nextFertilizing());
    }

    public function test_seasonal_intervals_save_validate_and_refresh_existing_flags(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 10)->setTime(12, 0));
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('add-plant-query'), [
            'name' => 'Seasonal aloe',
            'watering_frequency' => 10,
            'fertilizing_frequency' => 21,
            'summer_watering_frequency' => 3,
            'winter_watering_frequency' => 12,
            'summer_fertilizing_frequency' => 5,
        ])->assertRedirect(route('browse'));

        $plant = $owner->plants()->sole();
        $plant->history()->update(['watered_at' => '2026-07-04 08:00:00', 'fertilized_at' => '2026-07-04 08:00:00']);
        $this->artisan('checkForWatering')->assertSuccessful();
        $this->artisan('checkForFertilizing')->assertSuccessful();

        $this->assertDatabaseHas('needs', [
            'plant_id' => $plant->id,
            'summer_watering_frequency' => 3,
            'winter_watering_frequency' => 12,
            'summer_fertilizing_frequency' => 5,
            'winter_fertilizing_frequency' => null,
            'need_watering' => true,
            'need_fertilizing' => true,
        ]);
        $this->get(route('plants', $plant))->assertOk()->assertSee('Jul 7, 2026')->assertSee('Jul 9, 2026');
        $this->get(route('care.today'))->assertOk()->assertSee(route('updateFertilizing', $plant));

        $form = ['name' => 'Seasonal aloe', 'watering_frequency' => 10, 'fertilizing_frequency' => 21];
        $this->patchJson(route('editPlant', $plant), $form + ['winter_fertilizing_frequency' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('winter_fertilizing_frequency');
        $this->patch(route('editPlant', $plant), $form + ['summer_watering_frequency' => ''])
            ->assertRedirect(route('browse'));

        $this->assertDatabaseHas('needs', [
            'plant_id' => $plant->id,
            'summer_watering_frequency' => null,
            'winter_watering_frequency' => 12,
            'need_watering' => false,
            'need_fertilizing' => true,
        ]);
    }

    public function test_care_migration_preserves_available_legacy_dates(): void
    {
        $plant = $this->createPlant(User::factory()->create(), 'Legacy pothos', '2026-06-20 08:00:00');
        $plant->history()->update(['fertilized_at' => null]);
        $migration = require database_path('migrations/2026_11_19_000001_create_care_logs_table.php');

        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('care_logs', [
            'plant_id' => $plant->id,
            'type' => 'watering',
            'performed_at' => '2026-06-20 08:00:00',
            'is_legacy' => true,
        ]);
        $this->assertDatabaseCount('care_logs', 1);
        $this->actingAs($plant->owner)->get(route('plants', $plant))->assertOk()->assertSee('Jun 20, 2026')
            ->assertSee('Imported from previous care records');
    }

    public function test_refresh_keeps_due_flags_when_a_longer_seasonal_interval_takes_effect(): void
    {
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $plant = $this->createPlant($owner, 'Seasonal fern', '2026-09-28 08:00:00');
        $plant->needs()->update([
            'summer_watering_frequency' => 1,
            'summer_fertilizing_frequency' => 1,
            'winter_watering_frequency' => 10,
            'winter_fertilizing_frequency' => 10,
        ]);

        $this->travelTo(now()->setDate(2026, 9, 30)->setTime(21, 59));
        $this->artisan('care:refresh')->assertSuccessful();
        $this->assertDatabaseHas('needs', [
            'plant_id' => $plant->id, 'need_watering' => true, 'need_fertilizing' => true,
        ]);

        app(\App\Services\PlantCareSchedule::class)->refreshDueFlags(asOf: now()->setTime(22, 0));
        $this->assertDatabaseHas('needs', [
            'plant_id' => $plant->id, 'need_watering' => true, 'need_fertilizing' => true,
        ]);
        $this->travelTo(now()->setTime(22, 0));
        $this->actingAs($owner)->get(route('care.today'))->assertOk()->assertSee($plant->name);
    }

    private function createPlant(User $owner, string $name, string $lastCare): Plant
    {
        $plant = $owner->plants()->create(['name' => $name]);
        $plant->needs()->create(['watering_frequency' => 3, 'fertilizing_frequency' => 10]);
        $plant->history()->create(['watered_at' => $lastCare, 'fertilized_at' => $lastCare]);

        return $plant;
    }
}
