<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GardenInvitation extends Model
{
    protected $fillable = ['owner_id', 'email', 'token_hash', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'owner_id' => 'integer',
        'expires_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function digest(string $token): string
    {
        return hash('sha256', $token);
    }
}
