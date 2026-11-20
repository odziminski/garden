<?php

namespace App\Enums;

enum PlantObservationCategory: string
{
    case Repotting = 'repotting';
    case PestCheck = 'pest_check';
    case LeafHealth = 'leaf_health';
    case LocationChange = 'location_change';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Repotting => 'Repotting',
            self::PestCheck => 'Pest check',
            self::LeafHealth => 'Leaf health',
            self::LocationChange => 'Change of location',
            self::Other => 'Other',
        };
    }

    public function followUpName(): string
    {
        return match ($this) {
            self::Repotting => 'Check after repotting',
            self::PestCheck => 'Check for pests again',
            self::LeafHealth => 'Recheck leaf health',
            self::LocationChange => 'Check the plant in its new location',
            self::Other => 'Recheck this observation',
        };
    }
}
