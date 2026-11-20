<?php

namespace Tests\Feature;

use App\Models\GardenMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantTaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_owner_can_assign_tasks_to_members_and_revocation_clears_assignment(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern']);
        $task = $plant->careTasks()->create(['name' => 'Check soil', 'due_on' => today()->toDateString()]);
        $memberAccess = GardenMembership::query()->create(['owner_id' => $owner->id, 'member_id' => $member->id]);
        $url = "/plants/{$plant->id}/tasks/{$task->id}/assignment";

        $this->actingAs($member)->putJson($url, ['assignee_id' => $member->id])->assertNotFound();
        $this->actingAs($owner)->putJson($url, ['assignee_id' => $other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
        $this->put($url, ['assignee_id' => $member->id])->assertRedirect();
        $this->assertEquals($member->id, $task->fresh()->assignee_id);
        $this->delete(route('garden-sharing.memberships.destroy', $memberAccess))->assertRedirect();
        $this->assertNull($task->fresh()->assignee_id);
    }
}
