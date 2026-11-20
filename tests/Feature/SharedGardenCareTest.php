<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedGardenCareTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_see_shared_plants_and_care_but_cannot_manage_them_or_see_private_photos(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $plant = $this->plantThatNeedsCare($owner, 'Shared fern');
        $this->plantThatNeedsCare($stranger, 'Private rose');
        $privatePhoto = PlantPhotoEntry::query()->create([
            'plant_id' => $plant->id, 'taken_on' => now()->toDateString(),
            'format' => 'png', 'public_id' => 'plant_journal/private-fern',
        ]);
        $this->share($owner, $member);

        $this->actingAs($member)->get(route('browse'))->assertOk()->assertSee('Shared fern')->assertDontSee('Private rose');
        $this->get(route('care.today'))->assertOk()->assertSee('Shared fern')->assertDontSee('Private rose');
        $this->get(route('plants', $plant))->assertOk()->assertSee('Care history')->assertDontSee('Photo journal')
            ->assertDontSee(route('displayEditPlant', $plant));

        $this->get(route('displayEditPlant', $plant))->assertNotFound();
        $this->patch(route('editPlant', $plant))->assertNotFound();
        $this->delete(route('deletePlant', $plant))->assertNotFound();
        $this->post(route('plants.photo-journal.store', $plant))->assertNotFound();
        $this->get(route('plants.photo-journal.show', [$plant, 1]))->assertNotFound();
        $this->delete(route('plants.photo-journal.destroy', [$plant, 1]))->assertNotFound();

        $this->actingAs($stranger)->get(route('plants', $plant))->assertNotFound();
        $this->post(route('updateWatering', $plant))->assertNotFound();
    }

    public function test_both_people_see_the_same_due_task_and_a_second_quick_action_does_not_duplicate_the_log(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'UTC'));
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'caretaker@example.test']);
        $plant = $this->plantThatNeedsCare($owner, 'Shared fern');
        $this->share($owner, $member);

        $this->actingAs($owner)->get(route('care.today'))->assertOk()->assertSee(route('updateWatering', $plant));
        $this->actingAs($member)->get(route('care.today'))->assertOk()->assertSee(route('updateWatering', $plant));
        $this->from(route('care.today'))->post(route('updateWatering', $plant))->assertRedirect(route('care.today'));

        $this->actingAs($owner)->get(route('care.today'))->assertOk()->assertDontSee(route('updateWatering', $plant));
        $this->from(route('care.today'))->post(route('updateWatering', $plant))->assertRedirect(route('care.today'));

        $this->assertSame(1, $plant->careLogs()->where('type', 'watering')->count());
        $this->assertDatabaseHas('care_logs', [
            'plant_id' => $plant->id, 'type' => 'watering', 'performed_by_user_id' => $member->id,
        ]);
        $this->get(route('plants', $plant))->assertOk()->assertSee('caretaker@example.test');
    }

    public function test_backdated_entries_credit_the_member_and_removing_access_preserves_their_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00', 'UTC'));
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $plant = $this->plantThatNeedsCare($owner, 'Shared fern');
        $this->share($owner, $member);

        $this->actingAs($member)->post(route('care-logs.store', $plant), [
            'type' => 'watering', 'care_date' => '2026-11-05', 'note' => 'Watered the fern.',
        ])->assertRedirect(route('plants', $plant));
        $this->assertDatabaseHas('care_logs', [
            'plant_id' => $plant->id, 'performed_by_user_id' => $member->id, 'performed_on' => '2026-11-05',
        ]);

        DB::table('garden_memberships')->where('owner_id', $owner->id)->where('member_id', $member->id)->delete();

        $this->get(route('browse'))->assertOk()->assertDontSee('Shared fern');
        $this->get(route('care.today'))->assertOk()->assertDontSee('Shared fern');
        $this->get(route('plants', $plant))->assertNotFound();
        $this->post(route('updateWatering', $plant))->assertNotFound();
        $this->actingAs($owner)->get(route('plants', $plant))->assertOk()
            ->assertSee('Watered the fern.')->assertSee($member->email);
    }

    public function test_shared_tasks_use_the_owners_calendar_day_for_the_due_label(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 00:10:00', 'UTC'));
        $owner = User::factory()->create(['reminder_timezone' => 'Europe/Warsaw']);
        $member = User::factory()->create(['reminder_timezone' => 'America/Los_Angeles']);
        $plant = $owner->plants()->create(['name' => 'Shared fern', 'created_at' => '2026-11-17 09:00:00']);
        $plant->needs()->create(['watering_frequency' => 3, 'fertilizing_frequency' => 30]);
        $this->share($owner, $member);

        $this->actingAs($member)->get(route('care.today'))->assertOk()
            ->assertSee('Thursday, November 19, 2026')->assertSee('Shared fern')->assertSee('Due today')
            ->assertDontSee('Overdue');
    }

    public function test_registration_returns_an_invited_guest_to_the_join_page(): void
    {
        $invite = route('garden-sharing.invitations.show', str_repeat('a', 64));
        $this->get($invite)->assertRedirect(route('login'));

        $this->post(route('register'), [
            'email' => 'new-gardener@example.test',
            'password' => 'safe-password-for-test',
            'password_confirmation' => 'safe-password-for-test',
        ])->assertRedirect($invite);
    }

    private function plantThatNeedsCare(User $owner, string $name): Plant
    {
        $plant = $owner->plants()->create(['name' => $name, 'created_at' => now()->subDays(10)]);
        $plant->needs()->create(['watering_frequency' => 3, 'fertilizing_frequency' => 30]);

        return $plant;
    }

    private function share(User $owner, User $member): void
    {
        DB::table('garden_memberships')->insert([
            'owner_id' => $owner->id, 'member_id' => $member->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
