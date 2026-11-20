<?php

namespace App\Services;

use App\Enums\CareType;
use App\Models\CareLog;
use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use Carbon\CarbonImmutable;

class MonthlyPlantReport
{
    public function forPlant(Plant $plant, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth()->toDateString();
        // SQLite can store Eloquent date casts with a midnight time suffix.
        $end = $month->endOfMonth()->toDateTimeString();

        $logs = $plant->careLogs()
            ->whereBetween('performed_on', [$start, $end])
            ->orderBy('performed_on')->orderBy('performed_at')->orderBy('id')
            ->get(['id', 'type', 'performed_on', 'note', 'is_legacy']);

        $photos = PlantPhotoEntry::query()->forPlant($plant)
            ->whereBetween('taken_on', [$start, $end])
            ->orderBy('taken_on')->orderBy('id')
            ->get(['id', 'taken_on', 'note']);

        $timeline = $logs->toBase()->map(fn (CareLog $log) => [
            'date' => CarbonImmutable::parse($log->performed_on, 'UTC'),
            'label' => $log->type->actionLabel(),
            'note' => $log->note,
            'photoId' => null,
            'legacy' => $log->is_legacy,
        ])->concat($photos->map(fn (PlantPhotoEntry $photo) => [
            'date' => $photo->taken_on,
            'label' => 'Photo',
            'note' => $photo->note,
            'photoId' => $photo->id,
            'legacy' => false,
        ]))->sortBy(fn (array $entry) => $entry['date']->toDateString());

        $wateringDates = $logs->whereStrict('type', CareType::Watering)->pluck('performed_on')->all();
        $previousWatering = $wateringDates === [] ? null : $plant->careLogs()
            ->where('type', CareType::Watering->value)
            ->where('performed_on', '<', $start)
            ->latest('performed_on')
            ->value('performed_on');

        $intervals = $this->wateringIntervals($wateringDates, $previousWatering);

        return [
            'timeline' => $timeline,
            'intervals' => $intervals,
            'hasWatering' => $wateringDates !== [],
            'maxInterval' => $intervals === [] ? null : max(array_column($intervals, 'days')),
        ];
    }

    /**
     * @param  list<string>  $dates  ISO calendar dates from the selected month
     * @return list<array{from: string, to: string, days: int}>
     */
    public function wateringIntervals(array $dates, ?string $previousDate = null): array
    {
        $dates = array_unique($dates);
        sort($dates, SORT_STRING);
        $intervals = [];

        foreach ($dates as $date) {
            if ($previousDate !== null) {
                $days = CarbonImmutable::parse($previousDate, 'UTC')->diffInDays(CarbonImmutable::parse($date, 'UTC'));
                $intervals[] = ['from' => $previousDate, 'to' => $date, 'days' => (int) $days];
            }

            $previousDate = $date;
        }

        return $intervals;
    }
}
