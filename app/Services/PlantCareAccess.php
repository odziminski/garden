<?php

namespace App\Services;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PlantCareAccess
{
    /**
     * Lock the plant and verify current access inside the caller's transaction.
     */
    public static function lock(Plant $plant, User $actor): Plant
    {
        // SQLite ignores row locks, so acquire its write lock before reading the current care state.
        if (DB::getDriverName() === 'sqlite') {
            DB::table('plants')->where('id', $plant->getKey())->update(['id' => DB::raw('id')]);
        }

        $lockedPlant = Plant::query()->lockForUpdate()->findOrFail($plant->getKey());

        if (!$lockedPlant->isOwnedBy($actor)) {
            $membership = DB::table('garden_memberships')
                ->where('owner_id', $lockedPlant->user_id)
                ->where('member_id', $actor->getKey())
                ->lockForUpdate()
                ->first(['member_id']);

            abort_if($membership === null, 404);
        }

        return $lockedPlant;
    }
}
