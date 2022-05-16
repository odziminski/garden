<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Needs extends Model
{
    use HasFactory;

    protected $table = "needs";
    
    public $primaryKey  = 'id';
    public $timestamps = false;

    protected $fillable = [
        'plant_id',
        'watering_frequency',
        'fertilizing_frequency',
        'need_watering',
        'need_fertilizing',
    ];
    public function needs()
    {
        return $this->hasOne(Plant::class);
    }
}
