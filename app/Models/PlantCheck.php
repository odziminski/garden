<?php

namespace App\Models;

use App\Enums\PlantCheckOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'checked_by_user_id', 'care_log_id', 'outcome', 'scheduled_for', 'schedule_version',
        'checked_on', 'checked_at', 'next_check_on', 'note',
    ];

    protected $casts = [
        'outcome' => PlantCheckOutcome::class,
        'checked_at' => 'datetime',
        'voided_at' => 'datetime',
        'schedule_version' => 'integer',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_user_id');
    }

    public function careLog(): BelongsTo
    {
        return $this->belongsTo(CareLog::class);
    }

    public function canBeUndoneBy(User $actor, Plant $plant): bool
    {
        return $plant->isOwnedBy($actor) || (int) $this->checked_by_user_id === (int) $actor->getKey();
    }
}
