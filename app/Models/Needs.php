<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Needs extends Model
{
    use HasFactory;

    public const DEFAULT_WATERING_DAYS = 7;

    public const SCHEDULE_FIELDS = [
        'watering_frequency',
        'fertilizing_frequency',
        'summer_watering_frequency',
        'winter_watering_frequency',
        'summer_fertilizing_frequency',
        'winter_fertilizing_frequency',
        'seasonal_hemisphere',
    ];

    protected $table = "needs";
    
    public $primaryKey  = 'id';
    public $timestamps = false;

    protected $fillable = [
        'plant_id',
        ...self::SCHEDULE_FIELDS,
        'need_watering',
        'need_fertilizing',
    ];

    protected $casts = [
        'need_watering' => 'boolean',
        'need_fertilizing' => 'boolean',
    ];

    protected $attributes = ['watering_frequency' => self::DEFAULT_WATERING_DAYS, 'fertilizing_frequency' => 0];

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    
    
}
