<?php

namespace App\Models;

use App\Enums\PlantObservationCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantObservation extends Model
{
    protected $fillable = [
        'plant_id',
        'actor_id',
        'category',
        'observed_on',
        'notes',
        'container_size',
        'substrate',
        'photo_entry_id',
        'follow_up_task_id',
    ];

    protected $casts = [
        'category' => PlantObservationCategory::class,
    ];

    public function canBeCorrectedBy(User $user, Plant $plant): bool
    {
        return $plant->isOwnedBy($user) || (int) $this->actor_id === (int) $user->getKey();
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoEntry(): BelongsTo
    {
        return $this->belongsTo(PlantPhotoEntry::class);
    }

    public function followUpTask(): BelongsTo
    {
        return $this->belongsTo(PlantTask::class);
    }
}
