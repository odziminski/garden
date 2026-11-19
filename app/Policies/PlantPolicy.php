<?php

namespace App\Policies;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PlantPolicy
{
    public function manage(User $user, Plant $plant): Response
    {
        return (int) $user->id === (int) $plant->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
