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

    public function avatarUrl(): string
    {
        return $this->avatar === 'plant.png' ? asset('images/plant.png') : $this->avatar;
    }

    public function nextWatering(): ?Carbon
    {
        return $this->nextCareDate($this->history?->watered_at, $this->needs?->watering_frequency);
    }

    public function nextFertilizing(): ?Carbon
    {
        return $this->nextCareDate($this->history?->fertilized_at, $this->needs?->fertilizing_frequency);
    }

    private function nextCareDate(?Carbon $previous, ?int $days): ?Carbon
    {
        return $previous && $days > 0 ? $previous->copy()->addDays($days) : null;
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
