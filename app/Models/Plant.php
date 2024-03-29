<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use CloudinaryLabs\CloudinaryLaravel\MediaAlly;
use Illuminate\Notifications\Notifiable;

class Plant extends Model
{
    use HasFactory, Notifiable;
    protected $table = "plants";
    
    public $primaryKey  = 'id';
    public $timestamps = false;


      /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'avatar',
        'user_id',
        'created_at',
        'name',
        'species',
        'watering_frequency',
        'fertilizing_frequency',
        'watered_at',
        'fertilized_at',
        'need_watering',
        'need_fertilizing',
    ];


    public function users()
    {
        return $this->belongsTo(User::class);
    }
}
