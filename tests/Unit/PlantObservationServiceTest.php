<?php

namespace Tests\Unit;

use App\Models\GardenMembership;
use App\Models\User;
use App\Services\PlantObservations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PlantObservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_observation_write_rechecks_membership_after_route_authorization(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'Fern', 'created_at' => now()]);
        $membership = GardenMembership::query()->create(['owner_id' => $owner->id, 'member_id' => $member->id]);

        $this->assertTrue($member->can('view', $plant));
        $membership->delete();

        try {
            app(PlantObservations::class)->record($member, $plant, [
                'category' => 'other', 'observed_on' => now()->toDateString(), 'notes' => 'Moved indoors.',
            ]);
            $this->fail('A revoked member was able to record an observation.');
        } catch (NotFoundHttpException) {
            $this->assertDatabaseCount('plant_observations', 0);
        }
    }
}
