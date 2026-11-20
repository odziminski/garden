<?php

namespace App\Contracts;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface CarePushDelivery
{
    /** @param Collection<int, Plant> $plants */
    public function send(User $recipient, Collection $plants): void;
}
