<?php

namespace Tests\Feature;

use App\Enums\CareType;
use App\Models\GardenMembership;
use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyPlantReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_is_paginated_and_voided_care_does_not_appear(): void
    {
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'My fern']);
        foreach (range(1, 26) as $day) {
            $this->photo($plant, sprintf('2024-03-%02d', $day), "Journal entry {$day}.");
        }
        $this->care($plant, CareType::Watering, '2024-03-02', 'Incorrect watering');
        $plant->careLogs()->update(['voided_at' => now()]);

        $url = route('monthly-report.index', ['plant' => $plant->id, 'month' => '2024-03']);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Journal entry 25.')
            ->assertDontSee('Journal entry 26.')->assertDontSee('Incorrect watering');
        $this->get($url.'&timeline_page=2')->assertOk()->assertSee('Journal entry 26.')
            ->assertDontSee('Journal entry 25.')->assertSee('No watering dates in this month.');
    }

    public function test_timeline_includes_soil_findings_and_feeding_details_without_double_counting_water(): void
    {
        $this->travelTo(Carbon::parse('2024-03-04T12:00:00Z'));
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $checks = route('plant-checks.store', $plant);

        $this->actingAs($owner)->post($checks, [
            'outcome' => 'still_moist', 'schedule_version' => 0, 'note' => 'Damp below the surface.',
        ])->assertRedirect();
        $this->post($checks, [
            'outcome' => 'watered', 'schedule_version' => 1, 'note' => 'Watered after checking.',
        ])->assertRedirect();
        $this->post(route('care-logs.store', $plant), [
            'type' => 'fertilizing', 'care_date' => '2024-03-04',
            'fertilizer' => 'Fern feed', 'dose' => '2 ml in 1 l',
        ])->assertRedirect();

        $page = $this->get(route('monthly-report.index', ['plant' => $plant, 'month' => '2024-03']))
            ->assertOk()->assertSee('Soil check: Still moist')->assertSee('Damp below the surface.')
            ->assertSee('Fern feed')->assertSee('2 ml in 1 l');
        $this->assertSame(1, substr_count($page->getContent(), 'Watered after checking.'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/monthly-report')->assertRedirect(route('login'));
    }

    public function test_report_never_lists_or_returns_another_users_plant(): void
    {
        $owner = User::factory()->create();
        $owner->plants()->create(['name' => 'My cactus']);
        $otherPlant = User::factory()->create()->plants()->create(['name' => 'Private fig']);
        $this->photo($otherPlant, '2024-03-02', 'Private fig photo');

        $this->actingAs($owner)->get('/monthly-report?month=2024-03')
            ->assertOk()
            ->assertDontSee('Private fig');

        $this->get("/monthly-report?month=2024-03&plant={$otherPlant->id}")->assertNotFound();
    }

    public function test_members_can_visit_a_shared_plant_but_not_its_private_report(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Shared fig']);
        $this->photo($plant, '2024-03-02', 'Private fig photo');
        GardenMembership::query()->create(['owner_id' => $owner->id, 'member_id' => $member->id]);

        $this->actingAs($member)->get(route('plants', $plant))
            ->assertOk()->assertSee('Shared fig')
            ->assertDontSee(route('monthly-report.index', ['plant' => $plant->id]));
        $this->get(route('monthly-report.index', ['month' => '2024-03', 'plant' => $plant->id]))
            ->assertNotFound();
        $this->get(route('monthly-report.index'))->assertOk()
            ->assertDontSee('Shared fig')->assertDontSee('Private fig photo');
    }

    public function test_report_uses_calendar_dates_to_merge_and_scope_the_monthly_timeline(): void
    {
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $owner->plants()->create(['name' => 'Aloe']);
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $this->care($plant, CareType::Watering, '2024-02-25', 'Old watering');
        $this->care($plant, CareType::Watering, '2024-02-27', 'Previous watering');
        $this->care($plant, CareType::Fertilizing, '2024-02-29', 'Previous fertilizing');
        $this->care($plant, CareType::Watering, '2024-03-01', 'First watering', '2024-02-29 23:30:00');
        $this->care($plant, CareType::Watering, '2024-03-01', 'Second watering');
        $this->care($plant, CareType::Fertilizing, '2024-03-10', 'March fertilizing');
        $marchPhoto = $this->photo($plant, '2024-03-04', 'March photo');
        $this->care($plant, CareType::Watering, '2024-03-31', 'Final March watering');
        $this->photo($plant, '2024-03-31', 'Final March photo');
        $this->care($plant, CareType::Watering, '2024-04-01', 'April watering', '2024-03-31 22:30:00');
        $this->photo($plant, '2024-02-29', 'February photo');
        $this->photo($plant, '2024-04-01', 'April photo');
        $this->photo($owner->plants()->where('name', 'Aloe')->sole(), '2024-03-05', 'Other plant photo');

        $this->actingAs($owner)->get("/monthly-report?month=2024-03&plant={$plant->id}")
            ->assertOk()
            ->assertSeeInOrder(['First watering', 'Second watering', 'March photo', 'March fertilizing', 'Final March watering', 'Final March photo'])
            ->assertSee(route('plants.photo-journal.show', [$plant, $marchPhoto]))
            ->assertSee('3 days')
            ->assertSee('30 days')
            ->assertDontSee('>0 days<', false)
            ->assertDontSee('Previous watering')
            ->assertDontSee('Previous fertilizing')
            ->assertDontSee('Old watering')
            ->assertDontSee('February photo')
            ->assertDontSee('April photo')
            ->assertDontSee('April watering')
            ->assertDontSee('Other plant photo')
            ->assertDontSee($marchPhoto->public_id);
    }

    public function test_month_defaults_to_the_users_local_month(): void
    {
        $this->travelTo(Carbon::parse('2024-02-29T23:30:00Z'));
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $this->photo($plant, '2024-02-29', 'February photo');
        $this->photo($plant, '2024-03-01', 'First sunset');

        $this->actingAs($owner)->get('/monthly-report')
            ->assertOk()
            ->assertSee('First sunset')
            ->assertDontSee('February photo')
            ->assertSee('No watering dates in this month.');
    }

    public function test_report_has_distinct_empty_states(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get('/monthly-report')->assertOk()->assertSee('Add your first plant');

        $plant = $owner->plants()->create(['name' => 'Cactus']);
        $this->get('/monthly-report?month=2024-03')->assertOk()->assertSee('No care logs or photos for March 2024.');

        $this->care($plant, CareType::Watering, '2024-03-02', 'Watered once');
        $this->get('/monthly-report?month=2024-03')->assertOk()->assertSee('No interval can be calculated from one watering date.');
    }

    public function test_invalid_month_and_plant_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/monthly-report?month=2024-13&plant[]=1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['month', 'plant']);

        $this->from('/monthly-report?month=invalid')->get('/monthly-report?month=invalid')
            ->assertRedirect('/monthly-report')
            ->assertSessionHasErrors('month');
    }

    private function care(Plant $plant, CareType $type, string $date, string $note, ?string $timestamp = null): void
    {
        $plant->careLogs()->create([
            'type' => $type,
            'performed_on' => $date,
            'performed_at' => $timestamp ?? "$date 12:00:00",
            'note' => $note,
        ]);
    }

    private function photo(Plant $plant, string $date, string $note): PlantPhotoEntry
    {
        return PlantPhotoEntry::query()->create([
            'plant_id' => $plant->id,
            'taken_on' => $date,
            'note' => $note,
            'public_id' => 'plant_journal/'.fake()->uuid(),
            'format' => 'png',
        ]);
    }
}
