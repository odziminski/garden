<?php

namespace App\Services;

use App\Models\GardenInvitation;
use App\Models\GardenMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GardenSharing
{
    public function invite(User $owner, string $email): string
    {
        return DB::transaction(function () use ($owner, $email) {
            $this->lockGarden($owner->id);

            if ($owner->email === $email) {
                throw ValidationException::withMessages(['email' => 'You already own this garden.']);
            }

            $isAlreadyAMember = GardenMembership::query()->where('owner_id', $owner->id)
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
        abort_unless(
            $invitation->email === $member->email && $invitation->owner_id !== $member->id,
            403,
            'This link can only be used by the account with the invited email address.'
        );
        abort_unless($invitation->isPending(), 410, 'This invitation has expired or is no longer available.');
    }

    public function accept(GardenInvitation $invitation, User $member): void
    {
        DB::transaction(function () use ($invitation, $member) {
            $this->lockGarden($invitation->owner_id);
            $invitation = GardenInvitation::query()->findOrFail($invitation->id);
            $this->ensureCanAccept($invitation, User::query()->lockForUpdate()->findOrFail($member->id));

            GardenMembership::query()->firstOrCreate([
                'owner_id' => $invitation->owner_id,
                'member_id' => $member->id,
            ]);
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
        }, 3);
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
