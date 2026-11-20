<?php

namespace Tests\Feature;

use App\Models\GardenMembership;
use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Models\PlantTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_change_can_schedule_a_quick_follow_up_in_the_gardens_timezone(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-20 01:00:00', 'UTC'));
        $owner = User::factory()->create(['reminder_timezone' => 'America/Los_Angeles']);
        $plant = Plant::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)->post(route('plants.observations.store', $plant), [
            'category' => 'location_change',
            'observed_on' => '2026-11-19',
            'notes' => 'Moved the fern away from the radiator.',
            'follow_up_in_days' => '7',
        ])->assertSessionHasNoErrors();

        $observation = $plant->observations()->sole();
        $this->assertSame('Check the plant in its new location', $observation->followUpTask->name);
        $this->assertSame('2026-11-26', $observation->followUpTask->due_on);
        $this->get(route('plants', $plant))->assertSee('Change of location')->assertSee('Moved the fern away from the radiator.');
    }

    public function test_member_records_an_observation_using_the_gardens_calendar_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 23:30:00', 'UTC'));
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $member = User::factory()->create(['reminder_timezone' => 'America/Los_Angeles']);
        $plant = $this->plant($owner);
        $membership = GardenMembership::query()->create(['owner_id' => $owner->id, 'member_id' => $member->id]);

        $this->actingAs($member)->post("/plants/{$plant->id}/observations", [
            'category' => 'leaf_health', 'observed_on' => '2026-11-20', 'notes' => 'Two new leaves.',
            'actor_id' => $owner->id, 'plant_id' => 909,
        ])->assertRedirect(route('plants', $plant).'#observations');

        $this->assertDatabaseHas('plant_observations', [
            'plant_id' => $plant->id, 'actor_id' => $member->id,
            'category' => 'leaf_health', 'observed_on' => '2026-11-20', 'notes' => 'Two new leaves.',
        ]);
        $this->get(route('plants', $plant))->assertOk()->assertSee('Two new leaves.');

        $membership->delete();

        $this->get(route('plants', $plant))->assertNotFound();
        $this->post("/plants/{$plant->id}/observations")->assertNotFound();
        $this->actingAs($owner)->get(route('plants', $plant))->assertOk()
            ->assertSee('Two new leaves.')->assertSee($member->email);
    }

    public function test_owner_links_a_private_journal_photo_and_schedules_a_one_time_follow_up(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:30:00', 'UTC'));
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $plant = $this->plant($owner);
        $photo = $this->photo($plant);
        GardenMembership::query()->create(['owner_id' => $owner->id, 'member_id' => $member->id]);

        $this->actingAs($owner)->post("/plants/{$plant->id}/observations", [
            'category' => 'repotting',
            'observed_on' => '2026-11-18',
            'notes' => 'Moved to a larger pot.',
            'container_size' => '18 cm',
            'substrate' => 'Bark and coco coir',
            'photo_entry_id' => $photo->id,
            'follow_up_name' => 'Check soil moisture',
            'follow_up_due_on' => '2026-11-25',
            'repeat_days' => '14',
        ])->assertRedirect(route('plants', $plant).'#observations');

        $task = PlantTask::query()->where('plant_id', $plant->id)->sole();
        $this->assertSame('Check soil moisture', $task->name);
        $this->assertSame('2026-11-25', $task->due_on);
        $this->assertNull($task->repeat_days);
        $this->assertDatabaseHas('plant_observations', [
            'plant_id' => $plant->id, 'photo_entry_id' => $photo->id, 'follow_up_task_id' => $task->id,
            'container_size' => '18 cm', 'substrate' => 'Bark and coco coir',
        ]);
        $photoUrl = route('plants.photo-journal.show', [$plant, $photo]);
        $this->get(route('plants', $plant))->assertOk()->assertSee($photoUrl)->assertSee('View attached photo');
        $this->actingAs($member)->get(route('plants', $plant))->assertOk()->assertSee('Moved to a larger pot.')
            ->assertDontSee($photoUrl)->assertDontSee($photo->note)->assertDontSee($photo->public_id)
            ->assertDontSee('View attached photo');
        $this->get($photoUrl)->assertNotFound();

        $photo->delete();
        $this->assertDatabaseHas('plant_observations', ['plant_id' => $plant->id, 'photo_entry_id' => null]);
    }

    public function test_guest_and_stranger_cannot_record_observations(): void
    {
        $plant = $this->plant(User::factory()->create());
        $url = "/plants/{$plant->id}/observations";

        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post($url)->assertNotFound();
        $this->assertDatabaseCount('plant_observations', 0);
    }

    public function test_members_cannot_link_journal_photos_or_schedule_follow_ups(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $plant = $this->plant($owner);
        $photo = $this->photo($plant);
        GardenMembership::query()->create(['owner_id' => $owner->id, 'member_id' => $member->id]);
        $observation = ['category' => 'pest_check', 'observed_on' => '2025-11-20', 'notes' => 'No pests seen.'];

        $this->actingAs($member)->post("/plants/{$plant->id}/observations", $observation + [
            'photo_entry_id' => $photo->id,
        ])->assertNotFound();
        $this->post("/plants/{$plant->id}/observations", $observation + [
            'follow_up_name' => 'Check for mites', 'follow_up_due_on' => now()->toDateString(),
        ])->assertNotFound();

        $this->assertDatabaseCount('plant_observations', 0);
        $this->assertDatabaseCount('plant_tasks', 0);
    }

    public function test_invalid_observations_do_not_create_an_observation_or_task(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:30:00', 'UTC'));
        $owner = User::factory()->create();
        $plant = $this->plant($owner);
        $otherPlantPhoto = $this->photo($this->plant($owner));
        $url = "/plants/{$plant->id}/observations";
        $valid = ['category' => 'other', 'observed_on' => '2026-11-20', 'notes' => 'Shade now reaches the shelf.'];
        $this->actingAs($owner);

        foreach ([
            ['category', 'something_else', 'category'],
            ['observed_on', '2026-11-21', 'observed_on'],
            ['observed_on', '2026-02-30', 'observed_on'],
            ['notes', '', 'notes'],
            ['notes', str_repeat('x', 1001), 'notes'],
            ['container_size', '18 cm', 'container_size'],
            ['substrate', 'Bark and coco coir', 'substrate'],
            ['photo_entry_id', $otherPlantPhoto->id, 'photo_entry_id'],
            ['follow_up_name', 'Inspect roots', 'follow_up_due_on'],
            ['follow_up_due_on', '2026-11-25', 'follow_up_name'],
        ] as [$field, $value, $error]) {
            $this->post($url, array_replace($valid, [$field => $value]))
                ->assertRedirect(route('plants', $plant).'#observations')
                ->assertSessionHasErrorsIn('plantObservation', $error);
        }

        $this->post($url, $valid + ['follow_up_name' => 'Inspect roots', 'follow_up_due_on' => '2026-11-19'])
            ->assertSessionHasErrorsIn('plantObservation', 'follow_up_due_on');
        $this->assertDatabaseCount('plant_observations', 0);
        $this->assertDatabaseCount('plant_tasks', 0);
    }

    private function plant(User $owner): Plant
    {
        return $owner->plants()->create(['name' => 'Fern', 'created_at' => '2026-10-01 10:00:00']);
    }

    private function photo(Plant $plant): PlantPhotoEntry
    {
        return PlantPhotoEntry::query()->create([
            'plant_id' => $plant->id, 'taken_on' => '2026-11-19', 'note' => 'Private journal caption',
            'public_id' => 'plant_journal/private-fern', 'format' => 'png',
        ]);
    }
}
