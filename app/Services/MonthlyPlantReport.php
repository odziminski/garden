<?php

namespace App\Services;

use App\Enums\CareType;
use App\Models\Plant;
use App\Enums\PlantObservationCategory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MonthlyPlantReport
{
    public function forPlant(Plant $plant, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth()->toDateString();
        // SQLite can store Eloquent date casts with a midnight time suffix.
        $end = $month->endOfMonth()->toDateTimeString();

        $care = DB::table('care_logs')->where('plant_id', $plant->id)->whereNull('voided_at');
        $photos = DB::table('plant_photo_entries')->where('plant_id', $plant->id)
            ->whereBetween('taken_on', [$start, $end])
            ->selectRaw('id, DATE(taken_on) AS entry_day, NULL AS entry_time, 1 AS source_order, NULL AS event_type, note, FALSE AS legacy');
        $tasks = DB::table('plant_task_completions AS completions')
            ->join('plant_tasks AS tasks', 'tasks.id', '=', 'completions.plant_task_id')
            ->where('tasks.plant_id', $plant->id)->whereBetween('completed_on', [$start, $end])
            ->selectRaw('completions.id, DATE(completed_on) AS entry_day, NULL AS entry_time, 2 AS source_order, tasks.name AS event_type, NULL AS note, FALSE AS legacy');
        $observations = DB::table('plant_observations')->where('plant_id', $plant->id)
            ->whereBetween('observed_on', [$start, $end])
            ->selectRaw('id, DATE(observed_on) AS entry_day, NULL AS entry_time, 3 AS source_order, category AS event_type, notes AS note, FALSE AS legacy');

        $timelineQuery = (clone $care)->whereBetween('performed_on', [$start, $end])
            ->selectRaw('id, DATE(performed_on) AS entry_day, performed_at AS entry_time, 0 AS source_order, type AS event_type, note, is_legacy AS legacy')
            ->unionAll($photos)->unionAll($tasks)->unionAll($observations);

        $timeline = DB::query()->fromSub($timelineQuery, 'entries')
            ->orderBy('entry_day')->orderBy('source_order')->orderBy('entry_time')->orderBy('id')
            ->simplePaginate(25, pageName: 'timeline_page')->withQueryString()
            ->through(fn ($entry) => [
                'date' => CarbonImmutable::parse($entry->entry_day, 'UTC'),
                'label' => match ((int) $entry->source_order) {
                    0 => CareType::from($entry->event_type)->actionLabel(),
                    1 => 'Photo',
                    2 => 'Completed: '.$entry->event_type,
                    3 => PlantObservationCategory::from($entry->event_type)->label(),
                },
                'note' => $entry->note,
                'photoId' => (int) $entry->source_order === 1 ? $entry->id : null,
                'legacy' => (bool) $entry->legacy,
            ]);

        $watering = (clone $care)->where('type', CareType::Watering->value);
        $wateringDates = (clone $watering)->whereBetween('performed_on', [$start, $end])
            ->selectRaw('DISTINCT DATE(performed_on) AS calendar_day')->get()->pluck('calendar_day')->all();
        $previousWatering = $wateringDates === [] ? null : $watering->where('performed_on', '<', $start)
            ->latest('performed_on')->selectRaw('DATE(performed_on) AS calendar_day')->first()?->calendar_day;

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
