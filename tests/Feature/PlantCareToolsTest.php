<?php

namespace Tests\Feature;

use App\Mail\DailyCareDigest;
use App\Models\GardenMembership;
use App\Models\PlantTask;
use App\Models\User;
use App\Services\PlantCareAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlantCareToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_shows_due_custom_tasks_in_the_owners_calendar_to_members(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20T00:10:00Z'));
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $member = User::factory()->create(['reminder_timezone' => 'America/Los_Angeles']);
        $plant = $owner->plants()->create(['name' => 'Shared fern', 'created_at' => now()]);
        $privatePlant = User::factory()->create()->plants()->create(['name' => 'Private cactus']);
        GardenMembership::query()->create(['owner_id' => $owner->id, 'member_id' => $member->id]);

        $due = PlantTask::create(['plant_id' => $plant->id, 'name' => 'Rotate the pot', 'due_on' => '2026-11-20', 'repeat_days' => 14]);
        PlantTask::create(['plant_id' => $plant->id, 'name' => 'Wipe leaves next week', 'due_on' => '2026-11-27']);
        PlantTask::create(['plant_id' => $privatePlant->id, 'name' => 'Private inspection', 'due_on' => '2026-11-20']);

        $today = route('care.today');
        $this->actingAs($member)->get($today)->assertOk()
            ->assertSee('Rotate the pot')->assertSee('Due today')->assertSee(route('plant-tasks.complete', [$plant, $due]))
            ->assertDontSee('Wipe leaves next week')->assertDontSee('Private inspection');

        $this->from($today)->post(route('plant-tasks.complete', [$plant, $due]), ['scheduled_for' => '2026-11-20'])
            ->assertRedirect($today);

        $this->get($today)->assertOk()->assertDontSee('Rotate the pot');
        $this->assertSame('2026-12-04', $due->fresh()->due_on);
        $this->assertDatabaseHas('plant_task_completions', [
            'plant_task_id' => $due->id,
            'completed_by_user_id' => $member->id,
        ]);
    }

    public function test_today_preserves_the_written_deadline_on_a_skipped_local_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2011-12-30T12:00:00Z'));
        $owner = User::factory()->create(['reminder_timezone' => 'Pacific/Apia']);
        $plant = $owner->plants()->create(['name' => 'Fern']);
        PlantTask::create(['plant_id' => $plant->id, 'name' => 'Rotate pot', 'due_on' => '2011-12-30']);

        $this->actingAs($owner)->get(route('care.today'))->assertOk()
            ->assertSee('Rotate pot')->assertSee('Overdue')->assertSee('Dec 30, 2011');
    }

    public function test_sqlite_claims_the_write_lock_before_reading_care_state(): void
    {
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        DB::enableQueryLog();

        try {
            $lockedPlant = DB::transaction(fn () => PlantCareAccess::lock($plant, $owner));
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertTrue($lockedPlant->is($plant));
        $this->assertStringStartsWith('update "plants"', $queries[0]['query']);
        $this->assertStringStartsWith('select', $queries[1]['query']);
    }

    public function test_observation_identifies_its_active_and_archived_follow_up(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20T12:00:00Z'));
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $plantUrl = route('plants', $plant);

        $this->actingAs($owner)->post(route('plants.observations.store', $plant), [
            'category' => 'repotting',
            'observed_on' => '2026-11-20',
            'notes' => 'Repotted fern.',
            'follow_up_name' => 'Inspect the new pot',
            'follow_up_due_on' => '2026-11-27',
        ])->assertRedirect();

        $task = $plant->careTasks()->sole();
        $this->get($plantUrl)->assertOk()->assertSee('href="#custom-task-'.$task->id.'"', false)
            ->assertSeeInOrder(['Follow-up:', 'Inspect the new pot', 'Nov 27, 2026']);
        $this->delete(route('plant-tasks.archive', [$plant, $task]))->assertRedirect();
        $this->get($plantUrl)->assertOk()->assertSeeInOrder(['Follow-up:', 'Inspect the new pot', 'Archived'])
            ->assertDontSee('href="#custom-task-'.$task->id.'"', false);
    }

    public function test_a_reminder_can_be_sent_for_custom_tasks_alone_in_the_owners_local_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19T23:10:00Z'));
        Mail::fake();
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw', 'reminder_time' => '00:05']);
        $plant = $owner->plants()->create(['name' => 'Fern']);
        PlantTask::create(['plant_id' => $plant->id, 'name' => 'Inspect roots', 'due_on' => '2026-11-20']);
        PlantTask::create(['plant_id' => $plant->id, 'name' => 'Clean leaves later', 'due_on' => '2026-11-21']);

        $this->artisan('email:care')->assertSuccessful();
        $this->artisan('email:care')->assertSuccessful();

        Mail::assertSentCount(1);
        Mail::assertSent(DailyCareDigest::class, fn (DailyCareDigest $message) => $message->hasTo($owner->email));
        $message = Mail::sent(DailyCareDigest::class)->sole();
        $message->assertSeeInHtml('Inspect roots');
        $message->assertDontSeeInHtml('Clean leaves later');
    }

    public function test_the_monthly_timeline_shows_completed_tasks_and_observations_in_date_order(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20T12:00:00Z'));
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $task = PlantTask::create(['plant_id' => $plant->id, 'name' => 'Clean the leaves', 'due_on' => '2026-11-20']);

        $this->actingAs($owner)->post(route('plants.observations.store', $plant), [
            'category' => 'leaf_health', 'observed_on' => '2026-11-19', 'notes' => 'Two new fronds.',
        ])->assertRedirect();
        $this->post(route('plant-tasks.complete', [$plant, $task]), ['scheduled_for' => '2026-11-20'])->assertRedirect();

        $this->get(route('monthly-report.index', ['plant' => $plant->id, 'month' => '2026-11']))
            ->assertOk()->assertSeeInOrder(['Two new fronds.', 'Clean the leaves'])
            ->assertSee('No watering dates in this month.');
    }
}
