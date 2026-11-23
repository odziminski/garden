<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\PlantTask;
use App\Models\PlantTaskCompletion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_one_time_and_recurring_tasks(): void
    {
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);

        $this->actingAs($owner)->post("/plants/$plant->id/tasks", [
            'name' => 'Repot fern',
            'due_on' => '2026-11-20',
        ])->assertRedirect();

        $this->post("/plants/$plant->id/tasks", [
            'name' => 'Rotate pot',
            'due_on' => '2026-12-01',
            'repeat_days' => 7,
        ])->assertRedirect();

        $this->assertDatabaseHas('plant_tasks', [
            'plant_id' => $plant->id, 'name' => 'Repot fern', 'due_on' => '2026-11-20', 'repeat_days' => null,
        ]);
        $this->assertDatabaseHas('plant_tasks', [
            'plant_id' => $plant->id, 'name' => 'Rotate pot', 'due_on' => '2026-12-01', 'repeat_days' => 7,
        ]);
        $this->get(route('plants', $plant))->assertOk()->assertSee('Rotate pot');
    }

    public function test_task_creation_validates_the_name_calendar_date_and_interval(): void
    {
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);

        $this->actingAs($owner)->post("/plants/$plant->id/tasks", [
            'name' => ' ',
            'due_on' => '2026-02-30',
            'repeat_days' => 0,
        ])->assertSessionHasErrors(['name', 'due_on', 'repeat_days'], null, 'customTask');

        $this->assertDatabaseCount('plant_tasks', 0);
    }

    public function test_shared_member_completes_a_late_recurring_task_on_the_owners_calendar_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 00:15:00', 'UTC'));
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $member = User::factory()->create(['reminder_timezone' => 'America/Los_Angeles']);
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $task = $this->task($plant, '2026-11-17', 7);
        $this->share($owner, $member);

        $this->actingAs($member)->get(route('plants', $plant))->assertOk()
            ->assertSee('Wipe leaves')->assertDontSee('Add task');
        $this->from(route('care.today'))->post(route('plant-tasks.complete', [$plant, $task]), [
            'scheduled_for' => '2026-11-17',
        ])->assertRedirect(route('care.today'));

        $this->assertSame('2026-11-27', $task->refresh()->due_on);
        $completion = PlantTaskCompletion::query()->sole();
        $this->assertSame('2026-11-17', $completion->scheduled_for);
        $this->assertSame('2026-11-20', $completion->completed_on);
        $this->assertSame('2026-11-20 00:15:00', $completion->completed_at->toDateTimeString());
        $this->assertTrue($completion->completedBy->is($member));
        $this->actingAs($owner)->get(route('plants', $plant))->assertOk()->assertSee($member->email);

        $this->travelTo(CarbonImmutable::parse('2026-11-29 10:00:00', 'UTC'));
        $this->post(route('plant-tasks.complete', [$plant, $task]), [
            'scheduled_for' => '2026-11-17',
        ])->assertRedirect();
        $this->assertDatabaseCount('plant_task_completions', 1);
        $this->assertSame('2026-11-27', $task->refresh()->due_on);

        $this->post(route('plant-tasks.complete', [$plant, $task]), [
            'scheduled_for' => '2026-11-27',
        ])->assertRedirect();
        $this->assertDatabaseCount('plant_task_completions', 2);
        $this->assertSame('2026-12-06', $task->refresh()->due_on);
    }

    public function test_one_time_completion_removes_only_the_schedule_and_preserves_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'UTC'));
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $task = $this->task($plant, '2026-11-20');
        $url = route('plant-tasks.complete', [$plant, $task]);

        $this->actingAs($owner)->post($url, ['scheduled_for' => '2026-11-20'])->assertRedirect();
        $this->post($url, ['scheduled_for' => '2026-11-20'])->assertRedirect();

        $this->assertDatabaseCount('plant_task_completions', 1);
        $this->assertSame('2026-11-20', $task->refresh()->due_on);
        $this->assertNotNull($task->completed_at);
        $this->assertFalse(PlantTask::query()->active()->whereKey($task->id)->exists());
    }

    public function test_recurring_task_skips_a_date_completed_before_the_schedule_was_edited(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'UTC'));
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $task = $this->task($plant, '2026-11-27', 7);
        $complete = route('plant-tasks.complete', [$plant, $task]);

        $this->actingAs($owner)->post($complete, [
            'scheduled_for' => '2026-11-27', 'complete_early' => true,
        ])->assertRedirect();
        $this->patch(route('plant-tasks.update', [$plant, $task]), [
            'name' => 'Wipe leaves', 'due_on' => '2026-11-20', 'repeat_days' => 7,
        ])->assertRedirect();
        $this->post($complete, ['scheduled_for' => '2026-11-20'])->assertRedirect();

        $this->assertSame('2026-12-04', $task->fresh()->due_on);
        $this->assertDatabaseCount('plant_task_completions', 2);
    }

    public function test_completing_a_stuck_occurrence_repairs_the_schedule_without_recording_it_twice(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'UTC'));
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $task = $this->task($plant, '2026-11-27', 7);
        $complete = route('plant-tasks.complete', [$plant, $task]);
        $this->actingAs($owner)->post($complete, [
            'scheduled_for' => '2026-11-27', 'complete_early' => true,
        ])->assertRedirect();

        $task->refresh()->update(['due_on' => '2026-11-27']);
        $this->travelTo(CarbonImmutable::parse('2026-12-10 09:00:00', 'UTC'));
        $this->post($complete, ['scheduled_for' => '2026-11-27'])->assertRedirect();

        $this->assertSame('2026-12-04', $task->fresh()->due_on);
        $this->assertDatabaseCount('plant_task_completions', 1);
    }

    public function test_future_and_archived_tasks_cannot_be_completed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'UTC'));
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $future = $this->task($plant, '2026-11-21');
        $archived = $this->task($plant, '2026-11-19', 7);

        $this->actingAs($owner)->delete(route('plant-tasks.archive', [$plant, $archived]))->assertRedirect();
        $this->assertNotNull($archived->refresh()->archived_at);

        $this->post(route('plant-tasks.complete', [$plant, $future]), ['scheduled_for' => '2026-11-21'])->assertRedirect();
        $this->post(route('plant-tasks.complete', [$plant, $archived]), ['scheduled_for' => '2026-11-19'])->assertRedirect();
        $this->post(route('plant-tasks.complete', [$plant, $archived]))->assertSessionHasErrors('scheduled_for');

        $this->assertDatabaseCount('plant_task_completions', 0);
    }

    public function test_archiving_a_recurring_task_preserves_its_completion_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'UTC'));
        $owner = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $task = $this->task($plant, '2026-11-19', 7);

        $this->actingAs($owner)->post(route('plant-tasks.complete', [$plant, $task]), [
            'scheduled_for' => '2026-11-19',
        ])->assertRedirect();
        $this->delete(route('plant-tasks.archive', [$plant, $task]))->assertRedirect();

        $this->assertFalse(PlantTask::query()->active()->whereKey($task->id)->exists());
        $this->assertDatabaseHas('plant_task_completions', ['plant_task_id' => $task->id, 'completed_on' => '2026-11-20']);
        $this->get(route('plants', $plant))->assertOk()->assertSee('Wipe leaves')->assertDontSee(route('plant-tasks.archive', [$plant, $task]));
    }

    public function test_task_routes_enforce_owner_member_and_plant_boundaries(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $otherPlant = $owner->plants()->create(['name' => 'Palm']);
        $task = $this->task($plant, '2026-11-19');
        $this->share($owner, $member);
        $complete = route('plant-tasks.complete', [$plant, $task]);
        $archive = route('plant-tasks.archive', [$plant, $task]);
        $occurrence = ['scheduled_for' => '2026-11-19'];

        $this->post($complete, $occurrence)->assertRedirect(route('login'));

        $this->actingAs($stranger)->get(route('plants', $plant))->assertNotFound();
        $this->post($complete, $occurrence)->assertNotFound();
        $this->post(route('plant-tasks.store', $plant))->assertNotFound();

        $this->actingAs($member)->post(route('plant-tasks.store', $plant))->assertNotFound();
        $this->delete($archive)->assertNotFound();

        $this->actingAs($owner)->post(route('plant-tasks.complete', [$otherPlant, $task]), $occurrence)->assertNotFound();
        $this->delete(route('plant-tasks.archive', [$otherPlant, $task]))->assertNotFound();
        $this->assertDatabaseCount('plant_task_completions', 0);
        $this->assertNull($task->refresh()->archived_at);
    }

    private function task(Plant $plant, string $dueOn, ?int $repeatDays = null): PlantTask
    {
        return PlantTask::query()->create([
            'plant_id' => $plant->id,
            'name' => 'Wipe leaves',
            'due_on' => $dueOn,
            'repeat_days' => $repeatDays,
        ]);
    }

    private function share(User $owner, User $member): void
    {
        DB::table('garden_memberships')->insert([
            'owner_id' => $owner->id,
            'member_id' => $member->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
