<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GardenMembership extends Model
{
    protected $fillable = ['owner_id', 'member_id'];

    protected $casts = ['owner_id' => 'integer', 'member_id' => 'integer'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
