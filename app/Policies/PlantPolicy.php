<?php

namespace App\Policies;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PlantPolicy
{
    public function view(User $user, Plant $plant): Response
    {
        return $plant->isOwnedBy($user) || Plant::query()->visibleTo($user)->whereKey($plant->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function manage(User $user, Plant $plant): Response
    {
        return $plant->isOwnedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
