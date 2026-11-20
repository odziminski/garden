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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'room',
        'species_name',
        'care_instructions',
    ];


    public function isOwnedBy(User $user): bool
    {
        return (string) $this->user_id === (string) $user->getKey();
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $viewer): void
    {
        $query->where(fn (Builder $plants) => $plants->permanentlyVisibleTo($viewer)
            ->orWhere(fn (Builder $shared) => $shared->withActiveHandoffFor($viewer)));
    }

    #[Scope]
    protected function permanentlyVisibleTo(Builder $query, User $viewer): void
    {
        $query->where(function (Builder $plants) use ($viewer) {
            $plants->where('plants.user_id', $viewer->getKey())
                ->orWhereExists(function ($memberships) use ($viewer) {
                    $memberships->selectRaw('1')->from('garden_memberships')
                        ->whereColumn('garden_memberships.owner_id', 'plants.user_id')
                        ->where('garden_memberships.member_id', $viewer->getKey())
                        ->where(fn ($scope) => $scope->whereNull('garden_memberships.plant_id')
                            ->orWhereColumn('garden_memberships.plant_id', 'plants.id'));
                });
        });
    }

    #[Scope]
    protected function withActiveHandoffFor(Builder $query, User $viewer, ?CarbonInterface $instant = null): void
    {
        $handoffs = PlantHandoff::query()->activeAt($instant ?? now('UTC'))
            ->where('plant_handoffs.member_id', $viewer->getKey())
            ->whereColumn('plant_handoffs.owner_id', 'plants.user_id')
            ->join('plant_handoff_plants', 'plant_handoff_plants.plant_handoff_id', '=', 'plant_handoffs.id')
            ->whereColumn('plant_handoff_plants.plant_id', 'plants.id')->selectRaw('1');

        $query->whereExists($handoffs->toBase());
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

    public function checkSchedule(): HasOne
    {
        return $this->hasOne(PlantCheckSchedule::class);
    }

    public function plantChecks(): HasMany
    {
        return $this->hasMany(PlantCheck::class);
    }

    public function latestPlantCheck(): HasOne
    {
        return $this->hasOne(PlantCheck::class)->ofMany(
            ['checked_at' => 'max', 'id' => 'max'],
            fn (Builder $checks) => $checks->whereNull('voided_at'),
        );
    }

    public function careTasks(): HasMany
    {
        return $this->hasMany(PlantTask::class);
    }

    public function taskCompletions(): HasManyThrough
    {
        return $this->hasManyThrough(PlantTaskCompletion::class, PlantTask::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(PlantObservation::class);
    }

    public function plantData()
    {
        return $this->hasOne(PlantData::class);
    }

    public function avatarUrl(): string
    {
        return $this->cover_public_id && $this->cover_format
            ? route('plants.cover', $this, false)
            : asset('images/plant.png');
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
