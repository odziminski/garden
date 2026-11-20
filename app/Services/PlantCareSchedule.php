<?php

namespace App\Services;

use App\Enums\CareType;
use App\Models\Plant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PlantCareSchedule
{
    public function markWateringDue(): void
    {
        $this->refreshDueFlags(CareType::Watering);
    }

    public function markFertilizingDue(): void
    {
        $this->refreshDueFlags(CareType::Fertilizing);
    }

    public static function nextDate(Plant $plant, CareType $type, ?CarbonInterface $asOf = null): ?Carbon
    {
        $timezone = $plant->careTimezone();
        $frequency = $type->frequencyColumn();
        $needs = $plant->needs;
        $regularDays = (int) $needs?->$frequency;
        if ($regularDays <= 0) {
            return null;
        }

        $dateColumn = $type->lastCareDateColumn();
        $lastCareColumn = $type->lastCareColumn();
        $previous = $plant->history?->$lastCareColumn ?? $plant->created_at;
        $anchorDay = $plant->history?->$dateColumn ?? $previous?->copy()->setTimezone($timezone)->toDateString();
        if ($anchorDay === null) {
            return null;
        }

        $summerDays = (int) ($needs->{'summer_'.$frequency} ?? $regularDays);
        $winterDays = (int) ($needs->{'winter_'.$frequency} ?? $regularDays);
        $anchor = Carbon::parse($anchorDay, 'UTC');
        $due = self::scheduledDay($anchor, $summerDays, $winterDays, $needs->seasonal_hemisphere === 'south');
        $snoozeColumn = $type->snoozeColumn();

        return Carbon::parse(max($due, $needs->$snoozeColumn ?? $due), $timezone);
    }

    private static function scheduledDay(Carbon $anchor, int $summerDays, int $winterDays, bool $southern): string
    {
        if ($summerDays === $winterDays) {
            return $anchor->addDays($summerDays)->toDateString();
        }

        // Find the first date that satisfied the interval in effect at that time.
        // Once an occurrence is due, a later change of season cannot defer it.
        $period = $anchor->copy();
        while (true) {
            $isAprilThroughSeptember = $period->month >= 4 && $period->month <= 9;
            $summer = $isAprilThroughSeptember !== $southern;
            $boundary = $period->copy()->startOfYear();
            if ($isAprilThroughSeptember) {
                $boundary->month(10);
            } else {
                if ($period->month >= 10) {
                    $boundary->addYear();
                }
                $boundary->month(4);
            }

            $due = $anchor->copy()->addDays($summer ? $summerDays : $winterDays)->max($period);
            if ($due->lt($boundary)) {
                return $due->toDateString();
            }

            $period = $boundary;
        }
    }

    public function refreshDueFlags(?CareType $type = null, ?CarbonInterface $asOf = null): void
    {
        $asOf ??= now();
        $plants = Plant::query()->has('needs')->with('owner', 'history', 'needs')->lazyById(200);

        foreach ($plants as $plant) {
            if (! $plant->needs?->fill(self::expectedFlags($plant, $type, $asOf))->isDirty()) {
                continue;
            }

            DB::transaction(function () use ($plant, $type, $asOf) {
                $lockedPlant = Plant::query()->lockForUpdate()->find($plant->id);

                if ($lockedPlant !== null) {
                    self::syncFlags($lockedPlant, $type, $asOf);
                }
            }, 3);
        }
    }

    public static function syncFlags(Plant $plant, ?CareType $type = null, ?CarbonInterface $asOf = null): void
    {
        $needs = $plant->needs;
        if ($needs === null) {
            return;
        }

        $needs->fill(self::expectedFlags($plant, $type, $asOf ?? now()));

        if ($needs->isDirty()) {
            $needs->save();
        }
    }

    private static function expectedFlags(Plant $plant, ?CareType $type, CarbonInterface $asOf): array
    {
        $flags = [];
        foreach ($type === null ? CareType::cases() : [$type] as $careType) {
            $date = self::nextDate($plant, $careType, $asOf);
            $flags[$careType->dueFlag()] = $date !== null && $date->lte($asOf);
        }

        return $flags;
    }
}
