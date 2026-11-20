<?php

namespace App\Services;

use App\Models\GardenInvitation;
use App\Models\GardenMembership;
use App\Models\PlantTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GardenSharing
{
    public function invite(User $owner, string $email, ?int $plantId = null): string
    {
        return DB::transaction(function () use ($owner, $email, $plantId) {
            $this->lockGarden($owner->id);

            if ($owner->email === $email) {
                throw ValidationException::withMessages(['email' => 'You already own this garden.']);
            }

            if ($plantId !== null && $owner->plants()->whereKey($plantId)->lockForUpdate()->first(['id']) === null) {
                throw ValidationException::withMessages(['plant_id' => 'Choose a plant in your garden.']);
            }

            $isAlreadyAMember = GardenMembership::query()->where('owner_id', $owner->id)->where('plant_id', $plantId)
                ->whereHas('member', fn ($query) => $query->where('email', $email))->exists();

            if ($isAlreadyAMember) {
                throw ValidationException::withMessages(['email' => 'This account already has access to your garden.']);
            }

            GardenInvitation::query()->where('owner_id', $owner->id)->where('email', $email)->pending()
                ->update(['revoked_at' => now()]);

            $token = GardenInvitation::generateToken();
            GardenInvitation::query()->create([
                'owner_id' => $owner->id,
                'email' => $email,
                'plant_id' => $plantId,
                'token_hash' => GardenInvitation::digest($token),
                'expires_at' => now()->addDays(7),
            ]);

            return $token;
        }, 3);
    }

    public function findInvitation(string $token): GardenInvitation
    {
        return GardenInvitation::query()->where('token_hash', GardenInvitation::digest($token))->firstOrFail();
    }

    public function ensureCanAccept(GardenInvitation $invitation, User $member): void
    {
        $this->ensureInvitedUser($member, $invitation->email, $invitation->owner_id);
        abort_unless($invitation->isPending(), 410, 'This invitation has expired or is no longer available.');
    }

    public function ensureInvitedUser(User $member, string $email, int $ownerId): void
    {
        abort_unless($member->hasVerifiedEmail(), 403, 'Verify your email address before accepting an invitation.');
        abort_unless(
            $email === $member->email && $ownerId !== $member->id,
            403,
            'This link can only be used by the account with the invited email address.'
        );
    }

    public function accept(GardenInvitation $invitation, User $member): void
    {
        DB::transaction(function () use ($invitation, $member) {
            $this->lockGarden($invitation->owner_id);
            $invitation = GardenInvitation::query()->findOrFail($invitation->id);
            $this->ensureCanAccept($invitation, User::query()->lockForUpdate()->findOrFail($member->id));

            GardenMembership::query()->updateOrCreate([
                'owner_id' => $invitation->owner_id,
                'member_id' => $member->id,
            ], ['plant_id' => $invitation->plant_id]);
            if ($invitation->plant_id !== null) {
                $this->clearAssignments($invitation->owner_id, $member->id, $invitation->plant_id);
            }
            $invitation->forceFill(['accepted_at' => now()])->save();
        }, 3);
    }

    public function revoke(GardenInvitation $invitation, User $owner): void
    {
        abort_unless($invitation->owner_id === $owner->id, 404);

        DB::transaction(function () use ($invitation, $owner) {
            $this->lockGarden($owner->id);
            $revoked = GardenInvitation::query()->whereKey($invitation->id)->pending()->update([
                'revoked_at' => now(),
            ]);

            abort_unless($revoked === 1, 410, 'This invitation is no longer pending.');
        }, 3);
    }

    public function removeMembership(GardenMembership $membership, User $user): void
    {
        abort_unless($membership->owner_id === $user->id || $membership->member_id === $user->id, 404);

        DB::transaction(function () use ($membership) {
            $this->lockGarden($membership->owner_id);
            GardenMembership::query()->whereKey($membership->id)->delete();
            $this->clearAssignments($membership->owner_id, $membership->member_id);
        }, 3);
    }

    private function clearAssignments(int $ownerId, int $memberId, ?int $exceptPlantId = null): void
    {
        PlantTask::query()->where('assignee_id', $memberId)
            ->whereHas('plant', fn ($plants) => $plants->where('user_id', $ownerId))
            ->when($exceptPlantId !== null, fn ($tasks) => $tasks->where('plant_id', '!=', $exceptPlantId))
            ->update(['assignee_id' => null]);
    }

    private function lockGarden(int $ownerId): void
    {
        $owner = User::query()->whereKey($ownerId);

        // SQLite does not support row locks. Start with a write to acquire its writer lock before any reads.
        if (DB::getDriverName() === 'sqlite') {
            $owner->toBase()->update(['id' => DB::raw('id')]);

            return;
        }

        $owner->lockForUpdate()->firstOrFail();
    }
}
