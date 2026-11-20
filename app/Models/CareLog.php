<?php

namespace App\Models;

use App\Enums\CareType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CareLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'type', 'performed_at', 'performed_on', 'note', 'performed_by_user_id', 'fertilizer', 'dose',
        'previous_performed_at', 'previous_performed_on', 'corrects_entry_id',
    ];

    protected $casts = [
        'type' => CareType::class,
        'performed_at' => 'datetime',
        'previous_performed_at' => 'datetime',
        'voided_at' => 'datetime',
        'is_legacy' => 'boolean',
    ];

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function canBeCorrectedBy(User $actor, Plant $plant): bool
    {
        return $plant->isOwnedBy($actor) || (int) $this->performed_by_user_id === (int) $actor->getKey();
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function plantCheck(): HasOne
    {
        return $this->hasOne(PlantCheck::class);
    }

    public function assertCanChangeIndependently(): void
    {
        abort_if($this->plantCheck()->whereNull('voided_at')->exists(), 409, 'Undo the plant check to change its watering entry.');
    }
}
