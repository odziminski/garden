<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PlantTaskSchedule
{
    public static function nextDueOn(CarbonInterface $completedOn, ?int $repeatDays): ?string
    {
        if ($repeatDays === null) {
            return null;
        }

        $nextDueOn = CarbonImmutable::parse($completedOn->toDateString(), 'UTC')->addDays($repeatDays);

        return $nextDueOn->year <= 9999 ? $nextDueOn->toDateString() : null;
    }
}
