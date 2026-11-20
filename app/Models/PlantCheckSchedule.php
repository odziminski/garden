<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantCheckSchedule extends Model
{
    protected $attributes = ['enabled' => false, 'version' => 0];

    protected $casts = [
        'enabled' => 'boolean',
        'interval_days' => 'integer',
        'legacy_watering_frequency' => 'integer',
        'version' => 'integer',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function isScheduled(): bool
    {
        return $this->enabled && $this->next_check_on !== null;
    }
}
