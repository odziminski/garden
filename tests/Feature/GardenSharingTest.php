<?php

namespace Tests\Feature;

use App\Mail\GardenInviteMail;
use App\Models\GardenInvitation;
use App\Models\User;
use App\Services\GardenSharing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GardenSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invitation_is_emailed_when_a_mail_transport_is_configured(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $owner = User::factory()->create();
        $email = 'guest@example.test';
        $url = $this->invite($owner, $email);

        Mail::assertSent(GardenInviteMail::class, fn (GardenInviteMail $mail) => $mail->hasTo($email)
            && $mail->inviteUrl === $url);
    }

    public function test_unverified_email_cannot_accept_an_invitation(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create(['email_verified_at' => null]);
        $url = $this->invite($owner, $recipient->email);

        $this->actingAs($recipient)->get($url)->assertRedirect(route('verification.notice'));
        $this->post($url)->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('garden_memberships', 0);
    }

    public function test_invitation_can_limit_access_to_one_owned_plant(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $fern = $owner->plants()->create(['name' => 'Shared fern']);
        $cactus = $owner->plants()->create(['name' => 'Private cactus']);
        $foreignPlant = $guest->plants()->create(['name' => 'Guest violet']);

        $this->actingAs($owner)->postJson(route('garden-sharing.invitations.store'), [
            'email' => $guest->email, 'plant_id' => $foreignPlant->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('plant_id');

        $invitation = $this->post(route('garden-sharing.invitations.store'), [
            'email' => $guest->email, 'plant_id' => $fern->id,
        ])->assertCreated();
        $url = $invitation->viewData('inviteUrl');
        $this->actingAs($guest)->get($url)->assertOk()->assertSee('Shared fern');
        $this->post($url)->assertRedirect(route('browse'));
        $this->get(route('plants', $fern))->assertOk();
        $this->get(route('plants', $cactus))->assertNotFound();
        $this->post(route('care-logs.store', $cactus), [
            'type' => 'watering', 'care_date' => today()->toDateString(),
        ])->assertNotFound();
        $this->get(route('browse'))->assertSee('Shared fern')->assertDontSee('Private cactus');

        $this->actingAs($owner)->delete(route('deletePlant', $fern))->assertRedirect();
        $this->assertDatabaseCount('garden_memberships', 0);
    }

    public function test_invitation_is_bound_to_the_recipient_and_consumed_on_acceptance(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'partner@example.com']);
        $other = User::factory()->create(['email' => 'Partner@example.com']);
        $url = $this->invite($owner, $invitee->email);
        $invitation = DB::table('garden_invitations')->sole();

        $this->assertSame(hash('sha256', basename($url)), $invitation->token_hash);
        $this->assertDatabaseMissing('garden_invitations', ['token_hash' => basename($url)]);

        $this->actingAs($other)->get($url)->assertForbidden();
        $this->post($url)->assertForbidden();
        $this->assertDatabaseCount('garden_memberships', 0);

        $this->actingAs($invitee)->get($url)->assertOk()->assertSee($owner->email);
        $this->assertDatabaseCount('garden_memberships', 0);
        $this->post($url)->assertRedirect('/browse');
        $this->post($url)->assertGone();

        $this->assertDatabaseHas('garden_memberships', ['owner_id' => $owner->id, 'member_id' => $invitee->id]);
        $this->assertDatabaseCount('garden_memberships', 1);
        $this->get('/garden-sharing')->assertOk()->assertSee($owner->email);
        $this->actingAs($owner)->get('/garden-sharing')->assertOk()->assertSee($invitee->email);
    }

    public function test_reissued_expired_and_revoked_invitations_cannot_be_accepted(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $original = $this->invite($owner, $invitee->email);
        $replacement = $this->invite($owner, $invitee->email);
        $invitation = DB::table('garden_invitations')->where('token_hash', hash('sha256', basename($replacement)))->sole();

        $this->actingAs($invitee)->post($original)->assertGone();
        $this->delete("/garden-sharing/invitations/{$invitation->id}")->assertNotFound();
        $this->get($replacement)->assertOk();
        $this->actingAs($owner)->delete("/garden-sharing/invitations/{$invitation->id}")
            ->assertRedirect('/garden-sharing');
        $this->actingAs($invitee)->post($replacement)->assertGone();

        $expiring = $this->invite($owner, $invitee->email);
        $this->travel(7)->days();
        $this->actingAs($invitee)->get($expiring)->assertGone();
        $this->post($expiring)->assertGone();
        $this->assertDatabaseCount('garden_memberships', 0);
    }

    public function test_owner_can_remove_a_member_and_members_can_only_leave_their_own_memberships(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $url = $this->invite($owner, $member->email);
        $this->actingAs($member)->post($url)->assertRedirect('/browse');
        $membership = DB::table('garden_memberships')->sole();
        $path = "/garden-sharing/memberships/{$membership->id}";

        $this->actingAs($other)->delete($path)->assertNotFound();
        $this->actingAs($owner)->post('/garden-sharing/invitations', ['email' => $member->email])
            ->assertSessionHasErrors('email');
        $this->delete($path)->assertRedirect('/garden-sharing');
        $this->assertDatabaseCount('garden_memberships', 0);
        $this->actingAs($member)->post($url)->assertGone();

        $url = $this->invite($owner, $member->email);
        $this->actingAs($member)->post($url)->assertRedirect('/browse');
        $membership = DB::table('garden_memberships')->sole();
        $this->delete("/garden-sharing/memberships/{$membership->id}")->assertRedirect('/garden-sharing');
        $this->assertDatabaseCount('garden_memberships', 0);
    }

    public function test_guest_cannot_manage_sharing_or_accept_an_invitation(): void
    {
        $url = '/garden-sharing/join/'.str_repeat('a', 64);

        $this->get('/garden-sharing')->assertRedirect('/login');
        $this->post('/garden-sharing/invitations', ['email' => 'invitee@example.com'])->assertRedirect('/login');
        $this->delete('/garden-sharing/invitations/1')->assertRedirect('/login');
        $this->delete('/garden-sharing/memberships/1')->assertRedirect('/login');
        $this->get($url)->assertRedirect('/login');
        $this->post($url)->assertRedirect('/login');
    }

    public function test_owner_cannot_invite_their_own_account(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post('/garden-sharing/invitations', ['email' => $owner->email])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('garden_invitations', 0);
    }

    public function test_acceptance_rechecks_the_invitation_after_an_owner_revokes_it(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $this->invite($owner, $invitee->email);
        $pendingInvitation = GardenInvitation::query()->sole();
        $sharing = app(GardenSharing::class);

        $sharing->revoke($pendingInvitation, $owner);

        try {
            $sharing->accept($pendingInvitation, $invitee);
            $this->fail('A revoked invitation granted access.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('garden_memberships', 0);
    }

    private function invite(User $owner, string $email): string
    {
        return $this->actingAs($owner)->post('/garden-sharing/invitations', ['email' => $email])
            ->assertCreated()->assertHeader('Referrer-Policy', 'no-referrer')
            ->viewData('inviteUrl');
    }
}
