<?php

namespace App\Http\Controllers;

use App\Enums\CareType;
use App\Models\CareLog;
use App\Models\Plant;
use App\Models\PlantTask;
use App\Models\User;
use App\Services\PlantCareAccess;
use App\Services\PlantCareHistory;
use App\Services\PlantCareSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NeedsController extends Controller
{
    public function updateWatering(Request $request, Plant $plant): RedirectResponse
    {
        return $this->completeTask($request->user(), $plant, CareType::Watering);
    }

    public function updateFertilizing(Request $request, Plant $plant): RedirectResponse
    {
        return $this->completeTask($request->user(), $plant, CareType::Fertilizing);
    }

    public function storeCareLog(Request $request, Plant $plant): RedirectResponse
    {
        $timezone = $plant->careTimezone();
        $care = $this->validateCare($request, $timezone, 'care');

        $this->recordCare(
            $request->user(),
            $plant,
            CareType::from($care['type']),
            Carbon::parse($care['care_date'], $timezone),
            $care['note'] ?? null,
            $care['care_date'],
            fertilizer: $care['fertilizer'] ?? null,
            dose: $care['dose'] ?? null,
        );

        return redirect()->route('plants', $plant)->with('care_status', 'Care entry added.');
    }

    public function editCareLog(Request $request, Plant $plant, string $entry): View
    {
        $log = $plant->careLogs()->whereNull('voided_at')->findOrFail($entry);
        abort_unless($log->canBeCorrectedBy($request->user(), $plant), 404);
        $log->assertCanChangeIndependently();

        return view('plants.edit-care-log', ['plant' => $plant, 'entry' => $log]);
    }

    public function correctCareLog(Request $request, Plant $plant, string $entry): RedirectResponse
    {
        $original = $plant->careLogs()->whereNull('voided_at')->findOrFail($entry);
        abort_unless($original->canBeCorrectedBy($request->user(), $plant), 404);
        $original->assertCanChangeIndependently();
        $timezone = $plant->careTimezone();
        try {
            $care = $this->validateCare($request, $timezone, 'careCorrection');
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('care-logs.edit', [$plant, $entry]));
        }

        $this->recordCare(
            $request->user(),
            $plant,
            CareType::from($care['type']),
            $care['care_date'] === $original->performed_on ? $original->performed_at : Carbon::parse($care['care_date'], $timezone),
            $care['note'] ?? null,
            $care['care_date'],
            corrects: $entry,
            fertilizer: $care['fertilizer'] ?? null,
            dose: $care['dose'] ?? null,
        );

        return to_route('plants', $plant)->withFragment('care-history')->with('care_status', 'Care entry corrected.');
    }

    public function logEarly(Request $request, Plant $plant, CareType $type): RedirectResponse
    {
        $scheduledFor = $request->validate(['scheduled_for' => ['required', 'date_format:Y-m-d']])['scheduled_for'];
        $entry = $this->recordCare(
            $request->user(), $plant, $type, now($plant->careTimezone()), scheduledFor: $scheduledFor,
        );

        return $this->completionResponse($plant, $entry);
    }

    public function snooze(Request $request, Plant $plant, CareType $type): RedirectResponse
    {
        $changed = DB::transaction(function () use ($request, $plant, $type): bool {
            $lockedPlant = PlantCareAccess::lock($plant, $request->user());
            $now = now($lockedPlant->careTimezone());
            $due = PlantCareSchedule::nextDate($lockedPlant, $type, $now);
            if ($due === null || $due->gt($now)) {
                return false;
            }

            $lockedPlant->needs->setAttribute($type->snoozeColumn(), $now->copy()->addDay()->toDateString());
            PlantCareSchedule::syncFlags($lockedPlant, $type, $now);

            return true;
        }, 3);

        return $changed
            ? back()->with('care_status', 'Care snoozed until tomorrow.')
            : back()->with('care_notice', 'This care task is no longer due.');
    }

    public function undo(Request $request, Plant $plant, string $entry): RedirectResponse
    {
        DB::transaction(function () use ($request, $plant, $entry) {
            $actor = $request->user();
            $lockedPlant = PlantCareAccess::lock($plant, $actor);
            PlantCareHistory::void($lockedPlant, $actor, $entry);
            PlantCareSchedule::syncFlags($lockedPlant);
        }, 3);

        $request->session()->forget('care_undo');

        return back(302, [], route('plants', $plant).'#care-history')->with('care_status', 'Care entry undone.');
    }

    private function validateCare(Request $request, string $timezone, string $bag): array
    {
        return $request->validateWithBag($bag, [
            'type' => ['required', Rule::enum(CareType::class)],
            'care_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1970-01-02', 'before_or_equal:'.now($timezone)->toDateString()],
            'note' => ['nullable', 'string', 'max:1000'],
            'fertilizer' => ['exclude_unless:type,fertilizing', 'nullable', 'string', 'max:120'],
            'dose' => ['exclude_unless:type,fertilizing', 'nullable', 'string', 'max:80'],
        ]);
    }

    public function today(Request $request): View
    {
        $asOf = now($request->user()->reminder_timezone ?? 'UTC');
        $cursor = $request->query('after');
        $after = is_string($cursor) && preg_match('/^\d{4}-\d{2}-\d{2}:\d+$/D', $cursor)
            ? [substr($cursor, 0, 10), (int) substr($cursor, 11)]
            : null;
        $room = $request->query('room');
        $room = is_string($room) && mb_strlen($room) <= 80 ? $room : '';
        $visiblePlants = Plant::query()->visibleTo($request->user());
        $rooms = (clone $visiblePlants)->whereNotNull('room')->distinct()->orderBy('room')->pluck('room');
        $plants = $visiblePlants->when($room !== '', fn (Builder $query) => $query->where('room', $room))
            ->where(fn (Builder $query) => $query
                ->whereHas('needs', fn (Builder $needs) => $needs->where('watering_frequency', '>', 0)
                    ->orWhere('fertilizing_frequency', '>', 0))
                ->orWhereHas('checkSchedule', fn (Builder $schedule) => $schedule->where('enabled', true)
                    ->whereNotNull('next_check_on'))
                ->orWhereHas('careTasks', fn (Builder $tasks) => $tasks->active()))
            ->with([
                'owner', 'history', 'needs', 'checkSchedule', 'latestPlantCheck.checkedBy:id,email',
                'careTasks' => fn ($tasks) => $tasks->active()
                    ->orderByRaw('CASE WHEN snoozed_until > due_on THEN snoozed_until ELSE due_on END')
                    ->orderBy('id')->limit(13),
            ])->lazyById(100);

        $pageSize = 20;
        $duePlants = collect();
        $nextCare = null;
        foreach ($plants as $plant) {
            $today = $asOf->copy()->setTimezone($plant->careTimezone())->toDateString();
            $actions = $this->plantActions($plant, $today);
            $due = $actions->filter(fn (array $action) => $action['date']->toDateString() <= $today);
            $upcoming = $actions->first(fn (array $action) => $action['date']->toDateString() > $today);
            if ($upcoming !== null && ($nextCare === null || $upcoming['date']->toDateString() < $nextCare['date']->toDateString())) {
                $nextCare = ['plant' => $plant] + $upcoming;
            }
            if ($due->isEmpty()) {
                continue;
            }

            $key = [$due->first()['date']->toDateString(), (int) $plant->getKey()];
            if ($after !== null && $key <= $after) {
                continue;
            }

            $duePlants->push(['plant' => $plant, 'actions' => $due->take(12), 'hasMore' => $due->count() > 12, 'key' => $key]);
            $duePlants = $duePlants->sortBy('key', SORT_REGULAR)->take($pageSize + 1)->values();
        }

        $nextPage = $duePlants->count() > $pageSize ? implode(':', $duePlants[$pageSize - 1]['key']) : null;

        return view('today', [
            'duePlants' => $duePlants->take($pageSize), 'today' => $asOf,
            'nextCare' => $nextCare, 'nextPage' => $nextPage, 'isFirstPage' => $after === null,
            'room' => $room, 'rooms' => $rooms,
        ]);
    }

    private function plantActions(Plant $plant, string $today): \Illuminate\Support\Collection
    {
        $scheduled = collect(CareType::cases())->map(function (CareType $type) use ($plant, $today) {
            $date = PlantCareSchedule::nextDate($plant, $type);

            return $date === null ? null : ['type' => $type, 'date' => $date, 'dueToday' => $date->toDateString() === $today];
        })->filter();

        $custom = $plant->careTasks->map(function (PlantTask $task) use ($today) {
            $date = max($task->due_on, $task->snoozed_until);

            return ['task' => $task, 'date' => CarbonImmutable::parse($date, 'UTC'), 'dueToday' => $date === $today];
        });

        $checks = [];
        $checkDate = $plant->checkSchedule?->enabled ? $plant->checkSchedule->next_check_on : null;
        if ($checkDate !== null) {
            $checks[] = [
                'soilCheck' => true,
                'date' => CarbonImmutable::parse($checkDate, 'UTC'),
                'dueToday' => $checkDate === $today,
            ];
        }

        return $scheduled->concat($checks)->concat($custom)->sortBy(fn (array $action) => $action['date']->toDateString());
    }

    private function completeTask(User $actor, Plant $plant, CareType $type): RedirectResponse
    {
        $entry = $this->recordCare($actor, $plant, $type, now($plant->careTimezone()), onlyIfDue: true);

        return $this->completionResponse($plant, $entry);
    }

    private function completionResponse(Plant $plant, ?CareLog $entry): RedirectResponse
    {
        return $entry
            ? back()->with('care_status', 'Care recorded.')->with('care_undo', [$plant->getKey(), $entry->getKey()])
            : back()->with('care_notice', 'This task has already been completed or is no longer due.');
    }

    private function recordCare(
        User $actor,
        Plant $plant,
        CareType $type,
        CarbonInterface $date,
        ?string $note = null,
        ?string $performedOn = null,
        bool $onlyIfDue = false,
        ?string $scheduledFor = null,
        ?string $corrects = null,
        ?string $fertilizer = null,
        ?string $dose = null,
    ): ?CareLog {
        $performedOn ??= $date->toDateString();
        $performedAt = $date->copy()->utc();

        return DB::transaction(function () use ($actor, $plant, $type, $performedOn, $performedAt, $note, $onlyIfDue, $scheduledFor, $corrects, $fertilizer, $dose) {
            $lockedPlant = PlantCareAccess::lock($plant, $actor);

            if ($corrects !== null) {
                abort_unless(PlantCareHistory::void($lockedPlant, $actor, $corrects), 404);
            }
            if ($onlyIfDue) {
                $due = PlantCareSchedule::nextDate($lockedPlant, $type, $performedAt);
                if ($due === null || $due->gt($performedAt)) {
                    return null;
                }
            }

            if ($scheduledFor !== null) {
                $due = PlantCareSchedule::nextDate($lockedPlant, $type, $performedAt);
                $dateColumn = $type->lastCareDateColumn();
                if ($due?->toDateString() !== $scheduledFor || $lockedPlant->history?->$dateColumn === $performedOn) {
                    return null;
                }
            }

            return PlantCareHistory::append(
                $lockedPlant, $actor, $type, $performedAt, $performedOn, $note, $corrects,
                clearSnooze: $onlyIfDue || $scheduledFor !== null || $performedOn === now($lockedPlant->careTimezone())->toDateString(),
                fertilizer: $fertilizer,
                dose: $dose,
            );
        }, 3);
    }
}
