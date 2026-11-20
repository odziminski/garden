<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantTaskCompletion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'plant_task_id',
        'scheduled_for',
        'completed_on',
        'completed_at',
        'completed_by_user_id',
        'resulting_due_on',
        'previous_snoozed_until',
    ];

    protected $casts = ['completed_at' => 'datetime'];

    public function plantTask(): BelongsTo
    {
        return $this->belongsTo(PlantTask::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
