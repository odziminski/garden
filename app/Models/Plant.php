<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use CloudinaryLabs\CloudinaryLaravel\MediaAlly;


class Plant extends Model
{
    
    use HasFactory, Notifiable;
    protected $table = "plants";
    
    public $primaryKey  = 'id';

      /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'watering_frequency',
        'fertilizing_frequency',
    ];


    public function users()
    {
        return $this->belongsTo(User::class);
    }
}
