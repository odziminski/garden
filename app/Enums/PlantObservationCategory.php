<?php

namespace App\Enums;

enum PlantObservationCategory: string
{
    case Repotting = 'repotting';
    case PestCheck = 'pest_check';
    case LeafHealth = 'leaf_health';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Repotting => 'Repotting',
            self::PestCheck => 'Pest check',
            self::LeafHealth => 'Leaf health',
            self::Other => 'Other',
        };
    }
}
