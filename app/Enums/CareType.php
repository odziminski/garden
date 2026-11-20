<?php

namespace App\Enums;

enum CareType: string
{
    case Watering = 'watering';
    case Fertilizing = 'fertilizing';

    public function lastCareColumn(): string
    {
        return match ($this) {
            self::Watering => 'watered_at',
            self::Fertilizing => 'fertilized_at',
        };
    }

    public function frequencyColumn(): string
    {
        return $this->value.'_frequency';
    }

    public function lastCareDateColumn(): string
    {
        return match ($this) {
            self::Watering => 'watered_on',
            self::Fertilizing => 'fertilized_on',
        };
    }

    public function dueFlag(): string
    {
        return 'need_'.$this->value;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::Watering => 'Watered',
            self::Fertilizing => 'Fertilized',
        };
    }

    public function actionRoute(): string
    {
        return match ($this) {
            self::Watering => 'updateWatering',
            self::Fertilizing => 'updateFertilizing',
        };
    }
}
