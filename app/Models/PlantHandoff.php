<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlantHandoff extends Model
{
    protected $fillable = ['owner_id', 'email', 'token_hash', 'timezone', 'starts_at', 'ends_at'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'owner_id' => 'integer',
        'member_id' => 'integer',
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function plants(): BelongsToMany
    {
        return $this->belongsToMany(Plant::class, 'plant_handoff_plants')->withPivot('instructions');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereNotNull('plant_handoffs.accepted_at')
            ->whereNull('plant_handoffs.revoked_at')
            ->whereHas('member', fn (Builder $members) => $members->whereNotNull('users.email_verified_at')
                ->whereColumn('users.email', 'plant_handoffs.email'));
    }

    public function scopeActiveAt(Builder $query, CarbonInterface $instant): Builder
    {
        $instant = $instant->copy()->utc();

        return $query->accepted()->where('plant_handoffs.starts_at', '<=', $instant)
            ->where('plant_handoffs.ends_at', '>', $instant);
    }

    public function isActiveAt(CarbonInterface $instant): bool
    {
        return $this->accepted_at !== null && $this->revoked_at === null
            && $this->starts_at->lte($instant) && $this->ends_at->gt($instant);
    }

    public function dateRange(): string
    {
        $start = $this->starts_at->setTimezone($this->timezone);
        $lastDay = $this->ends_at->setTimezone($this->timezone)->subDay();

        return $start->isSameDay($lastDay)
            ? $start->toFormattedDateString()
            : $start->toFormattedDateString().' – '.$lastDay->toFormattedDateString();
    }
}
