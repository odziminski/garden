<?php

namespace App\Services;

use App\Models\GardenInvitation;
use App\Models\PlantHandoff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlantHandoffs
{
    public function __construct(private readonly GardenSharing $sharing) {}

    public function invite(User $owner, array $data): string
    {
        if ($data['email'] === $owner->email) {
            throw ValidationException::withMessages(['email' => 'Choose another person to care for your plants.']);
        }

        return DB::transaction(function () use ($owner, $data) {
            $plantsQuery = $owner->plants()->whereKey($data['plant_ids']);
            // Acquire SQLite's write lock before reading and checking the selected plants.
            if (DB::getDriverName() === 'sqlite') {
                (clone $plantsQuery)->toBase()->update(['id' => DB::raw('id')]);
            }

            $plantIds = $plantsQuery->orderBy('id')->lockForUpdate()->pluck('id');
            if ($plantIds->count() !== count($data['plant_ids'])) {
                throw ValidationException::withMessages(['plant_ids' => 'Choose only plants you own.']);
            }

            $timezone = $owner->reminder_timezone;
            $startsAt = CarbonImmutable::parse($data['starts_on'], $timezone)->startOfDay()->utc();
            $followingDay = CarbonImmutable::parse($data['ends_on'], 'UTC')->addDay()->toDateString();
            $endsAt = CarbonImmutable::parse($followingDay, $timezone)->startOfDay()->utc();
            $token = GardenInvitation::generateToken();
            $handoff = PlantHandoff::query()->create([
                'owner_id' => $owner->id,
                'email' => $data['email'],
                'token_hash' => GardenInvitation::digest($token),
                'timezone' => $timezone,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
            $handoff->plants()->attach($plantIds->mapWithKeys(fn (int $id) => [
                $id => ['instructions' => $data['instructions'][$id] ?? null],
            ])->all());

            return $token;
        }, 3);
    }

    public function findInvitation(string $token): PlantHandoff
    {
        return PlantHandoff::query()->where('token_hash', GardenInvitation::digest($token))->firstOrFail();
    }

    public function ensureCanAccept(PlantHandoff $handoff, User $helper): void
    {
        $this->sharing->ensureInvitedUser($helper, $handoff->email, $handoff->owner_id);
        abort_unless(
            $handoff->accepted_at === null && $handoff->revoked_at === null && $handoff->ends_at->isFuture()
                && $handoff->plants()->where('plants.user_id', $handoff->owner_id)->exists(),
            410,
            'This handoff invitation is no longer available.'
        );
    }

    public function accept(PlantHandoff $handoff, User $helper): void
    {
        DB::transaction(function () use ($handoff, $helper) {
            $query = PlantHandoff::query()->whereKey($handoff->id);
            if (DB::getDriverName() === 'sqlite') {
                (clone $query)->toBase()->update(['id' => DB::raw('id')]);
            }
            $handoff = $query->lockForUpdate()->firstOrFail();
            $helper = User::query()->lockForUpdate()->findOrFail($helper->id);
            $this->ensureCanAccept($handoff, $helper);
            $handoff->forceFill(['member_id' => $helper->id, 'accepted_at' => now('UTC')])->save();
        }, 3);
    }

    public function revoke(PlantHandoff $handoff, User $owner): void
    {
        abort_unless($handoff->owner_id === $owner->id, 404);
        $instant = now('UTC');
        PlantHandoff::query()->whereKey($handoff->id)->whereNull('revoked_at')->where('ends_at', '>', $instant)
            ->update(['revoked_at' => $instant]);
    }
}
