<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;


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
    ];


    public function users()
    {
        return $this->belongsTo(User::class);
    }

    public function needs()
    {
        return $this->hasOne(Needs::class);
    }

    public function history()
    {
        return $this->hasOne(History::class);
    }

    public function plantData()
    {
        return $this->hasOne(PlantData::class);
    }

    public static function getDateForHumans($date): string
    {
        return Carbon::parse($date)->diffForHumans();
    }

    public static function getNextCareDate($date, $interval): Carbon
    {
        return Carbon::parse($date)->addDays($interval);
    }
}
