<?php

namespace App\Services;

use App\Models\GardenMembership;
use App\Models\Plant;
use App\Models\PlantHandoff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PlantCareAccess
{
    /**
     * Lock the plant and verify current access inside the caller's transaction.
     */
    public static function lock(Plant $plant, User $actor, bool $allowHandoff = true): Plant
    {
        // SQLite ignores row locks, so acquire its write lock before reading the current care state.
        if (DB::getDriverName() === 'sqlite') {
            DB::table('plants')->where('id', $plant->getKey())->update(['id' => DB::raw('id')]);
        }

        $lockedPlant = Plant::query()->lockForUpdate()->findOrFail($plant->getKey());

        if (!$lockedPlant->isOwnedBy($actor)) {
            $membership = GardenMembership::query()->forPlant($lockedPlant)
                ->where('member_id', $actor->getKey())
                ->lockForUpdate()
                ->first(['member_id']);

            if ($membership === null) {
                abort_unless($allowHandoff, 404);
                $handoff = PlantHandoff::query()->activeAt(now('UTC'))->where('member_id', $actor->getKey())
                    ->where('owner_id', $lockedPlant->user_id)
                    ->whereHas('plants', fn ($plants) => $plants->whereKey($lockedPlant->getKey()))
                    ->lockForUpdate()->first();
                abort_unless($handoff?->isActiveAt(now('UTC')), 404);
            }
        }

        return $lockedPlant;
    }
}
