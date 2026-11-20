<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GardenMembership extends Model
{
    protected $fillable = ['owner_id', 'member_id', 'plant_id'];

    protected $casts = ['owner_id' => 'integer', 'member_id' => 'integer'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function scopeForPlant(Builder $query, Plant $plant): Builder
    {
        return $query->where('owner_id', $plant->user_id)
            ->where(fn (Builder $memberships) => $memberships->whereNull('plant_id')->orWhere('plant_id', $plant->id));
    }
}
