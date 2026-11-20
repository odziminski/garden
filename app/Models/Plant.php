<?php

namespace App\Models;

use App\Enums\CareType;
use App\Services\PlantCareSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;


class Plant extends Model
{
    use HasFactory, Notifiable;
    protected $table = "plants";

    public $primaryKey  = 'id';
    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

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


    public function isOwnedBy(User $user): bool
    {
        return (string) $this->user_id === (string) $user->getKey();
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $viewer): void
    {
        $query->where(function (Builder $plants) use ($viewer) {
            $plants->where('plants.user_id', $viewer->getKey())
                ->orWhereExists(function ($memberships) use ($viewer) {
                    $memberships->selectRaw('1')->from('garden_memberships')
                        ->whereColumn('garden_memberships.owner_id', 'plants.user_id')
                        ->where('garden_memberships.member_id', $viewer->getKey());
                });
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function needs()
    {
        return $this->hasOne(Needs::class);
    }

    public function history()
    {
        return $this->hasOne(History::class);
    }

    public function careLogs(): HasMany
    {
        return $this->hasMany(CareLog::class);
    }

    public function plantData()
    {
        return $this->hasOne(PlantData::class);
    }

    public function avatarUrl(): string
    {
        return !$this->avatar || $this->avatar === 'plant.png' ? asset('images/plant.png') : $this->avatar;
    }

    public function nextWatering(?CarbonInterface $asOf = null): ?Carbon
    {
        return PlantCareSchedule::nextDate($this, CareType::Watering, $asOf);
    }

    public function nextFertilizing(?CarbonInterface $asOf = null): ?Carbon
    {
        return PlantCareSchedule::nextDate($this, CareType::Fertilizing, $asOf);
    }

    public function careTimezone(): string
    {
        return $this->owner?->reminder_timezone ?? 'UTC';
    }
}
