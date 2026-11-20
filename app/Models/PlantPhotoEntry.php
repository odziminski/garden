<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlantPhotoEntry extends Model
{
    protected $fillable = [
        'plant_id',
        'taken_on',
        'public_id',
        'format',
        'provider',
        'note',
    ];

    protected function casts(): array
    {
        return ['taken_on' => 'immutable_date'];
    }

    #[Scope]
    protected function forPlant(Builder $query, Plant $plant): void
    {
        $query->where('plant_id', $plant->getKey());
    }
}
