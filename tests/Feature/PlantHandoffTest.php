<?php

namespace Tests\Feature;

use App\Enums\CareType;
use App\Enums\PlantCheckOutcome;
use App\Models\GardenMembership;
use App\Models\Plant;
use App\Models\PlantHandoff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_verified_helper_only_gets_shared_care_during_the_owners_local_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-25 12:00 UTC'));
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $helper = User::factory()->create();
        $fern = $owner->plants()->create(['name' => 'Fern by the window']);
        $ivy = $owner->plants()->create(['name' => 'Ivy on the shelf']);
        $orchid = $owner->plants()->create(['name' => 'Private orchid']);
        $fern->careLogs()->create([
            'type' => CareType::Watering, 'performed_at' => now()->subDay(), 'performed_on' => '2027-03-24',
            'note' => 'Watered before the trip.', 'performed_by_user_id' => $owner->id,
        ]);
        $fern->observations()->create([
            'category' => 'other', 'observed_on' => '2027-03-24', 'notes' => 'Private observation.', 'actor_id' => $owner->id,
        ]);
        $fern->plantChecks()->create([
            'checked_by_user_id' => $owner->id, 'outcome' => PlantCheckOutcome::StillMoist, 'schedule_version' => 0,
            'scheduled_for' => '2027-03-24', 'checked_on' => '2027-03-24', 'checked_at' => now()->subDay(),
            'note' => 'Soil was moist at the last check.',
        ]);
        $this->actingAs($owner)->patch(route('plant-checks.schedule', $fern), [
            'enabled' => true, 'next_check_on' => '2027-03-28',
        ])->assertRedirect();
        $url = $this->invite($owner, $helper, [$fern, $ivy], '2027-03-28', '2027-03-28');

        $this->actingAs($helper)->get($url)->assertOk()->assertDontSee('Fern by the window')->assertDontSee('Water using the blue jug.');
        $this->post($url)->assertRedirect('/handoff');
        $this->post($url)->assertGone();
        $this->get('/handoff')->assertOk()->assertSee('Scheduled')->assertDontSee('Water using the blue jug.');
        $this->get(route('plants', $fern))->assertNotFound();

        $this->travelTo(CarbonImmutable::parse('2027-03-27 23:00 UTC'));
        $this->get('/handoff')->assertOk()->assertSee('Water using the blue jug.')->assertSee('Ivy on the shelf')
            ->assertSee('Last checked 2027-03-24')->assertSee('#plant-checks', false)->assertDontSee('Private orchid');
        $this->get(route('plants', $fern))->assertOk()->assertSee('Watered before the trip.')
            ->assertSee('id="plant-checks"', false)->assertSee('Soil was moist at the last check.')->assertDontSee('Private observation.');
        $this->get(route('plants', $ivy))->assertOk();
        $this->get(route('plants', $orchid))->assertNotFound();
        $this->get(route('displayEditPlant', $fern))->assertNotFound();
        $this->post(route('plants.observations.store', $fern), ['category' => 'other', 'notes' => 'Keep observations private.'])->assertNotFound();
        $this->post(route('care-logs.store', $fern), ['type' => 'watering', 'care_date' => '2027-03-28'])->assertRedirect();
        $this->post(route('plant-checks.store', $fern), ['outcome' => 'still_moist', 'schedule_version' => 1])->assertRedirect();
        $this->assertDatabaseHas('care_logs', ['plant_id' => $fern->id, 'performed_by_user_id' => $helper->id]);
        $this->assertDatabaseHas('plant_checks', ['plant_id' => $fern->id, 'checked_by_user_id' => $helper->id]);
        $this->assertDatabaseMissing('garden_memberships', ['owner_id' => $owner->id, 'member_id' => $helper->id]);

        $this->travelTo(CarbonImmutable::parse('2027-03-28 22:00 UTC'));
        $this->get('/handoff')->assertOk()->assertDontSee('Water using the blue jug.');
        $this->get(route('plants', $fern))->assertNotFound();
        $this->post(route('care-logs.store', $fern), ['type' => 'watering', 'care_date' => '2027-03-28'])->assertNotFound();
        $this->post(route('plant-checks.store', $fern), ['outcome' => 'still_moist', 'schedule_version' => 2])->assertNotFound();
    }

    public function test_a_final_day_with_a_midnight_clock_jump_expires_at_the_next_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-09-04 12:00 UTC'));
        $owner = User::factory()->create(['reminder_timezone' => 'America/Santiago']);
        $helper = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'My fern']);
        $url = $this->invite($owner, $helper, [$plant], '2027-09-05', '2027-09-05');
        $this->actingAs($helper)->post($url)->assertRedirect('/handoff');

        $this->travelTo(CarbonImmutable::parse('2027-09-06 02:59 UTC'));
        $this->get(route('plants', $plant))->assertOk();
        $this->travelTo(CarbonImmutable::parse('2027-09-06 03:00 UTC'));
        $this->get(route('plants', $plant))->assertNotFound();
    }

    public function test_the_owner_can_revoke_an_accepted_handoff_without_affecting_permanent_sharing(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-25 12:00 UTC'));
        $owner = User::factory()->create();
        $helper = User::factory()->create();
        $fern = $owner->plants()->create(['name' => 'Temporary fern']);
        $ivy = $owner->plants()->create(['name' => 'Permanently shared ivy']);
        GardenMembership::query()->create(['owner_id' => $owner->id, 'member_id' => $helper->id, 'plant_id' => $ivy->id]);
        $url = $this->invite($owner, $helper, [$fern, $ivy], '2027-03-25', '2027-03-26');
        $this->actingAs($helper)->post($url)->assertRedirect('/handoff');
        $this->get(route('plants', $fern))->assertOk();

        $revoke = '/handoff/'.PlantHandoff::query()->sole()->id;
        $this->delete($revoke)->assertNotFound();
        $this->actingAs($owner)->delete($revoke)->assertRedirect('/handoff');

        $this->actingAs($helper)->get(route('plants', $fern))->assertNotFound();
        $this->get(route('plants', $ivy))->assertOk();
        $this->get('/handoff')->assertDontSee('Water using the blue jug.');
    }

    public function test_invitations_require_the_exact_verified_account_and_owned_plant_selection(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-25 12:00 UTC'));
        $owner = User::factory()->create();
        $helper = User::factory()->create(['email_verified_at' => null]);
        $outsider = User::factory()->create();
        $plant = $owner->plants()->create(['name' => 'My fern']);
        $foreign = $outsider->plants()->create(['name' => 'Foreign ivy']);

        $this->actingAs($owner)->post('/handoff', [
            'email' => $helper->email, 'starts_on' => '2027-03-25', 'ends_on' => '2027-03-26',
            'plant_ids' => [$plant->id, $foreign->id],
        ])->assertSessionHasErrors('plant_ids');
        $this->actingAs($owner)->post('/handoff', [
            'email' => $helper->email, 'starts_on' => '2027-03-26', 'ends_on' => '2027-03-25',
            'plant_ids' => [$plant->id],
        ])->assertSessionHasErrors('ends_on');

        $url = $this->invite($owner, $helper, [$plant], '2027-03-25', '2027-03-26');
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $this->post($url)->assertForbidden();
        $this->actingAs($helper)->get($url)->assertRedirect(route('verification.notice'));
        $this->get(route('plants', $plant))->assertNotFound();

        $helper->markEmailAsVerified();
        $this->actingAs($helper)->post($url)->assertRedirect('/handoff');
        $this->get(route('plants', $plant))->assertOk();
        $helper->forceFill(['email' => 'changed@example.test', 'email_verified_at' => null])->save();
        $this->actingAs($helper->fresh())->get(route('plants', $plant))->assertNotFound();
    }

    /** @param list<Plant> $plants */
    private function invite(User $owner, User $helper, array $plants, string $startsOn, string $endsOn): string
    {
        return $this->actingAs($owner)->post('/handoff', [
            'email' => $helper->email, 'starts_on' => $startsOn, 'ends_on' => $endsOn,
            'plant_ids' => array_map(fn (Plant $plant) => $plant->id, $plants),
            'instructions' => [$plants[0]->id => 'Water using the blue jug.'],
        ])->assertCreated()->assertHeader('Referrer-Policy', 'no-referrer')->viewData('inviteUrl');
    }
}
