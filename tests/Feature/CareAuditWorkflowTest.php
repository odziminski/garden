<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareAuditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_snoozes_a_due_item_and_can_undo_a_completion_after_refresh(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 10)->startOfDay());
        $owner = User::factory()->create();
        $plant = $this->plant($owner, 'Thirsty violet');
        $today = route('care.today');
        $this->actingAs($owner);

        $this->get($today)->assertOk()->assertSee('Thirsty violet');
        $this->from($today)->post(route('care-actions.snooze', [$plant, 'watering']))->assertRedirect($today);
        $this->get($today)->assertOk()->assertSee('You’re all caught up')->assertSee('Jul 11, 2026');

        $this->travel(1)->days();
        $this->from($today)->post(route('updateWatering', $plant))->assertRedirect($today);
        $entry = $plant->careLogs()->sole();
        $this->get($today)->assertOk()->assertSee('Undo');
        $this->from($today)->delete(route('care-logs.undo', [$plant, $entry]))->assertRedirect($today);
        $this->get($today)->assertOk()->assertSee('Thirsty violet');
        $this->assertNotNull($entry->fresh()->voided_at);
        $this->assertSame('2026-07-02', $plant->history()->sole()->watered_at->toDateString());
    }

    public function test_today_can_log_the_next_scheduled_care_early_once(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 3)->startOfDay());
        $owner = User::factory()->create();
        $plant = $this->plant($owner, 'Water early violet');
        $today = route('care.today');
        $this->actingAs($owner)->get($today)->assertOk()->assertSee('Jul 5, 2026')->assertSee('Water early');

        $endpoint = route('care-actions.early', [$plant, 'watering']);
        $this->from($today)->post($endpoint, ['scheduled_for' => '2026-07-05'])->assertRedirect($today);
        $this->from($today)->post($endpoint, ['scheduled_for' => '2026-07-05'])->assertRedirect($today);
        $this->assertDatabaseCount('care_logs', 1);
        $this->assertSame('2026-07-06', $plant->fresh()->nextWatering()->toDateString());
    }

    public function test_the_owner_can_edit_a_custom_task_schedule_and_correct_an_observation(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 10)->startOfDay());
        $owner = User::factory()->create();
        $plant = $this->plant($owner, 'Practical violet');
        $task = $plant->careTasks()->create(['name' => 'Old label', 'due_on' => '2026-07-13']);
        $observation = $plant->observations()->create([
            'category' => 'repotting', 'observed_on' => '2026-07-08', 'notes' => 'Old note',
            'actor_id' => $owner->id,
        ]);
        $detail = route('plants', $plant);
        $this->actingAs($owner);

        $this->get(route('plant-tasks.edit', [$plant, $task]))->assertOk()->assertSee('Old label');
        $this->patch(route('plant-tasks.update', [$plant, $task]), [
            'name' => 'Rotate pot', 'due_on' => '2026-07-12', 'repeat_days' => 30,
        ])->assertRedirect($detail.'#custom-tasks');

        $this->get(route('plants.observations.edit', [$plant, $observation]))->assertOk()->assertSee('Old note');
        $this->patch(route('plants.observations.update', [$plant, $observation]), [
            'category' => 'repotting', 'observed_on' => '2026-07-09', 'notes' => 'Corrected note', 'container_size' => '8 inch',
        ])->assertRedirect($detail.'#observations');

        $this->get($detail)->assertOk()->assertSee('Rotate pot')->assertSee('Corrected note')->assertDontSee('Old note');
        $this->assertSame('2026-07-12', $task->fresh()->due_on);
        $this->assertSame('2026-07-09', $observation->fresh()->observed_on);
    }

    public function test_custom_task_snooze_and_undo_restore_the_same_occurrence(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 10)->startOfDay());
        $owner = User::factory()->create();
        $plant = $this->plant($owner, 'Practical violet');
        $task = $plant->careTasks()->create(['name' => 'Rotate pot', 'due_on' => '2026-07-10', 'repeat_days' => 14]);
        $today = route('care.today');
        $this->actingAs($owner)->from($today)->post(route('plant-tasks.snooze', [$plant, $task]), [
            'scheduled_for' => '2026-07-10',
        ])->assertRedirect($today);
        $this->assertSame('2026-07-11', $task->fresh()->effectiveDueOn());

        $this->travel(1)->days();
        $this->from($today)->post(route('plant-tasks.complete', [$plant, $task]), [
            'scheduled_for' => '2026-07-10',
        ])->assertRedirect($today);
        $completion = $task->completions()->sole();

        $this->get($today)->assertOk()->assertSee('Undo task completion');
        $this->from($today)->delete(route('plant-tasks.undo', [$plant, $task, $completion]))->assertRedirect($today);
        $this->assertSame('2026-07-10', $task->fresh()->due_on);
        $this->assertSame('2026-07-11', $task->fresh()->snoozed_until);
        $this->assertModelMissing($completion);
    }

    public function test_a_care_correction_voids_and_replaces_the_entry_atomically(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 10)->startOfDay());
        $owner = User::factory()->create();
        $plant = $this->plant($owner, 'Practical violet');
        $this->actingAs($owner)->post(route('care-logs.store', $plant), [
            'type' => 'watering', 'care_date' => '2026-07-09', 'note' => 'Mistaken date',
        ])->assertRedirect(route('plants', $plant));
        $entry = $plant->careLogs()->sole();
        $update = route('care-logs.update', [$plant, $entry]);
        $this->get(route('care-logs.edit', [$plant, $entry]))->assertOk()->assertSee('Mistaken date');

        $this->patchJson($update, ['type' => 'watering', 'care_date' => '2026-07-11'])->assertUnprocessable();
        $this->assertNull($entry->fresh()->voided_at);

        $this->patch($update, ['type' => 'watering', 'care_date' => '2026-07-08', 'note' => 'Correct date'])
            ->assertRedirect(route('plants', $plant).'#care-history');
        $this->assertNotNull($entry->fresh()->voided_at);
        $this->assertDatabaseHas('care_logs', [
            'plant_id' => $plant->id, 'performed_on' => '2026-07-08', 'corrects_entry_id' => $entry->id,
        ]);
        $this->assertSame('2026-07-11', $plant->fresh()->nextWatering()->toDateString());
    }

    public function test_browse_and_today_only_render_one_page_of_plants(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 10)->startOfDay());
        $owner = User::factory()->create();
        $plants = collect(range(1, 25))->map(fn (int $number) => $this->plant($owner, sprintf('Audit fern %02d', $number)));
        $this->actingAs($owner)->get(route('care.today'))->assertOk()
            ->assertSee('Audit fern 20')->assertDontSee('Audit fern 21')->assertSee('More due plants');
        $this->get(route('care.today', ['after' => '2026-07-05:'.$plants[19]->id]))->assertOk()
            ->assertSee('Audit fern 21')->assertDontSee('Audit fern 20');
        $this->get(route('browse'))->assertOk()->assertSee('Audit fern 24')->assertDontSee('Audit fern 25');
        $this->get(route('browse', ['page' => 2]))->assertOk()->assertSee('Audit fern 25')->assertDontSee('Audit fern 24');
    }

    private function plant(User $owner, string $name): Plant
    {
        $plant = $owner->plants()->create(['name' => $name]);
        $plant->needs()->create(['watering_frequency' => 3, 'fertilizing_frequency' => 0]);
        $plant->history()->create(['watered_at' => '2026-07-02 08:00:00']);

        return $plant;
    }
}
