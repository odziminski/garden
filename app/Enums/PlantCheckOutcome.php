<?php

namespace App\Enums;

enum PlantCheckOutcome: string
{
    case Skipped = 'skipped';
    case StillMoist = 'still_moist';
    case Watered = 'watered';

    public function label(): string
    {
        return match ($this) {
            self::Skipped => 'Skipped',
            self::StillMoist => 'Still moist',
            self::Watered => 'Watered',
        };
    }
}
