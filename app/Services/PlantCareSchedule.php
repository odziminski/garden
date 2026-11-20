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
        $localDate = Carbon::instance($asOf ?? now())->setTimezone($timezone);
        $season = $localDate->month >= 4 && $localDate->month <= 9 ? 'summer' : 'winter';
        $frequency = $type->frequencyColumn();
        $seasonalFrequency = $season.'_'.$frequency;
        $days = (int) ($plant->needs?->$seasonalFrequency ?? $plant->needs?->$frequency);
        if ($days <= 0) {
            return null;
        }

        $dateColumn = $type->lastCareDateColumn();
        $anchor = $plant->history?->$dateColumn;
        if ($anchor !== null) {
            $dueDay = Carbon::parse($anchor, 'UTC')->addDays($days)->toDateString();

            return Carbon::parse($dueDay, $timezone);
        }

        $lastCareColumn = $type->lastCareColumn();
        $previous = $plant->history?->$lastCareColumn ?? $plant->created_at;

        return $previous?->copy()->setTimezone($timezone)->startOfDay()->addDays($days);
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
