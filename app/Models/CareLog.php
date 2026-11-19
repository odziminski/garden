<?php

namespace App\Models;

use App\Enums\CareType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'performed_at', 'performed_on', 'note'];

    protected $casts = [
        'type' => CareType::class,
        'performed_at' => 'datetime',
        'is_legacy' => 'boolean',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }
}
