<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantTask extends Model
{
    protected $fillable = ['plant_id', 'name', 'due_on', 'repeat_days'];

    protected $casts = [
        'repeat_days' => 'integer',
        'archived_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at')->whereNull('completed_at');
    }

    public function isActive(): bool
    {
        return $this->archived_at === null && $this->completed_at === null;
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(PlantTaskCompletion::class);
    }
}
