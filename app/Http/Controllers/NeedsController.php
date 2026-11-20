<?php

namespace App\Http\Controllers;

use App\Enums\CareType;
use App\Models\CareLog;
use App\Models\Plant;
use App\Models\PlantTask;
use App\Models\User;
use App\Services\PlantCareAccess;
use App\Services\PlantCareSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
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
        );

        return redirect()->route('plants', $plant)->with('care_status', 'Care entry added.');
    }

    public function editCareLog(Request $request, Plant $plant, string $entry): View
    {
        $log = $plant->careLogs()->whereNull('voided_at')->findOrFail($entry);
        abort_unless($log->canBeCorrectedBy($request->user(), $plant), 404);

        return view('plants.edit-care-log', ['plant' => $plant, 'entry' => $log]);
    }

    public function correctCareLog(Request $request, Plant $plant, string $entry): RedirectResponse
    {
        $original = $plant->careLogs()->whereNull('voided_at')->findOrFail($entry);
        abort_unless($original->canBeCorrectedBy($request->user(), $plant), 404);
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
            $this->voidEntry($lockedPlant, $actor, $entry);
            PlantCareSchedule::syncFlags($lockedPlant);
        }, 3);

        $request->session()->forget('care_undo');

        return back(302, [], route('plants', $plant).'#care-history')->with('care_status', 'Care entry undone.');
    }

    private function voidEntry(Plant $plant, User $actor, string $entry): bool
    {
        $log = $plant->careLogs()->findOrFail($entry);
        abort_unless($log->canBeCorrectedBy($actor, $plant), 404);
        if ($log->voided_at !== null) {
            return false;
        }

        $log->voided_at = now();
        $log->save();

        $logs = $plant->careLogs()->where('type', $log->type->value);
        $original = (clone $logs)->oldest('id')->first(['previous_performed_at', 'previous_performed_on']);
        $active = $logs->whereNull('voided_at');
        $lastInstant = (clone $active)->max('performed_at');
        $lastDay = $active->where('is_legacy', false)->max('performed_on');
        $history = $plant->history()->firstOrNew();
        $history->fill([
            $log->type->lastCareColumn() => max($lastInstant, $original->previous_performed_at?->toDateTimeString()),
            $log->type->lastCareDateColumn() => max($lastDay, $original->previous_performed_on),
        ])->save();

        $plant->setRelation('history', $history);

        return true;
    }

    private function validateCare(Request $request, string $timezone, string $bag): array
    {
        return $request->validateWithBag($bag, [
            'type' => ['required', Rule::enum(CareType::class)],
            'care_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1970-01-02', 'before_or_equal:'.now($timezone)->toDateString()],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    public function today(Request $request): View
    {
        $asOf = now($request->user()->reminder_timezone ?? 'UTC');
        $cursor = $request->query('after');
        $after = is_string($cursor) && preg_match('/^\d{4}-\d{2}-\d{2}:\d+$/D', $cursor)
            ? [substr($cursor, 0, 10), (int) substr($cursor, 11)]
            : null;
        $plants = Plant::query()->visibleTo($request->user())->with([
            'owner', 'history', 'needs',
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

        return $scheduled->concat($custom)->sortBy(fn (array $action) => $action['date']->toDateString());
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
    ): ?CareLog {
        $performedOn ??= $date->toDateString();
        $performedAt = $date->copy()->utc();

        return DB::transaction(function () use ($actor, $plant, $type, $performedOn, $performedAt, $note, $onlyIfDue, $scheduledFor, $corrects) {
            $lockedPlant = PlantCareAccess::lock($plant, $actor);

            if ($corrects !== null) {
                abort_unless($this->voidEntry($lockedPlant, $actor, $corrects), 404);
            }
            if ($onlyIfDue) {
                $due = PlantCareSchedule::nextDate($lockedPlant, $type, $performedAt);
                if ($due === null || $due->gt($performedAt)) {
                    return null;
                }
            }

            $history = $lockedPlant->history()->firstOrNew();
            $column = $type->lastCareColumn();
            $dateColumn = $type->lastCareDateColumn();
            if ($scheduledFor !== null) {
                $due = PlantCareSchedule::nextDate($lockedPlant, $type, $performedAt);
                if ($due?->toDateString() !== $scheduledFor || $history->$dateColumn === $performedOn) {
                    return null;
                }
            }

            $firstLog = !$lockedPlant->careLogs()->where('type', $type->value)->exists();
            $entry = $lockedPlant->careLogs()->create([
                'type' => $type,
                'performed_at' => $performedAt,
                'performed_on' => $performedOn,
                'note' => $note,
                'performed_by_user_id' => $actor->getKey(),
                'previous_performed_at' => $firstLog ? $history->$column : null,
                'previous_performed_on' => $firstLog ? $history->$dateColumn : null,
                'corrects_entry_id' => $corrects,
            ]);

            $newerInstant = $history->$column === null || $performedAt->gt($history->$column);
            if ($newerInstant) {
                $history->$column = $performedAt;
            }
            if ($history->$dateColumn === null ? $newerInstant : $performedOn > $history->$dateColumn) {
                $history->$dateColumn = $performedOn;
            }
            if ($history->isDirty()) {
                $history->save();
            }

            $lockedPlant->setRelation('history', $history);
            if ($lockedPlant->needs && ($onlyIfDue || $scheduledFor !== null || $performedOn === now($lockedPlant->careTimezone())->toDateString())) {
                $lockedPlant->needs->setAttribute($type->snoozeColumn(), null);
            }
            PlantCareSchedule::syncFlags($lockedPlant);

            return $entry;
        }, 3);
    }
}
