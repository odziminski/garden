<?php

namespace App\Http\Controllers;

use App\Enums\CareType;
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
        $today = now($timezone)->toDateString();
        $care = $request->validateWithBag('care', [
            'type' => ['required', Rule::enum(CareType::class)],
            'care_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1970-01-02', 'before_or_equal:'.$today],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

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

    public function today(Request $request): View
    {
        $asOf = now($request->user()->reminder_timezone ?? 'UTC');
        $latestPossibleDueDate = $asOf->copy()->utc()->addDay()->toDateString();
        $plants = Plant::query()->visibleTo($request->user())->with([
            'owner', 'history', 'needs',
            'careTasks' => fn ($tasks) => $tasks->active()->where('due_on', '<=', $latestPossibleDueDate),
        ])->orderBy('name')->get();
        $duePlants = $plants->map(function (Plant $plant) use ($asOf) {
            $gardenTime = $asOf->copy()->setTimezone($plant->careTimezone());
            $actions = collect(CareType::cases())->map(function (CareType $type) use ($plant, $gardenTime) {
                $date = PlantCareSchedule::nextDate($plant, $type, $gardenTime);

                return $date !== null && $date->lte($gardenTime) ? [
                    'type' => $type,
                    'date' => $date,
                    'dueToday' => $date->isSameDay($gardenTime),
                ] : null;
            })->filter()->sortBy('date');

            $today = $gardenTime->toDateString();
            $customActions = $plant->careTasks
                ->filter(fn (PlantTask $task) => $task->due_on <= $today)
                ->map(fn (PlantTask $task) => [
                    'task' => $task,
                    'date' => CarbonImmutable::parse($task->due_on, 'UTC'),
                    'dueToday' => $task->due_on === $today,
                ]);

            return ['plant' => $plant, 'actions' => $actions->concat($customActions)->sortBy(fn (array $action) => $action['date']->toDateString())];
        })->filter(fn (array $item) => $item['actions']->isNotEmpty())
            ->sortBy(fn (array $item) => $item['actions']->first()['date']->toDateString());

        return view('today', ['duePlants' => $duePlants, 'today' => $asOf]);
    }

    private function completeTask(User $actor, Plant $plant, CareType $type): RedirectResponse
    {
        $recorded = $this->recordCare($actor, $plant, $type, now($plant->careTimezone()), onlyIfDue: true);

        return $recorded
            ? back()
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
    ): bool {
        $performedOn ??= $date->toDateString();
        $performedAt = $date->copy()->utc();

        return DB::transaction(function () use ($actor, $plant, $type, $performedOn, $performedAt, $note, $onlyIfDue) {
            $lockedPlant = PlantCareAccess::lock($plant, $actor);

            if ($onlyIfDue) {
                $due = PlantCareSchedule::nextDate($lockedPlant, $type, $performedAt);
                if ($due === null || $due->gt($performedAt)) {
                    return false;
                }
            }

            $history = $lockedPlant->history()->firstOrNew();
            $column = $type->lastCareColumn();
            $dateColumn = $type->lastCareDateColumn();

            $lockedPlant->careLogs()->create([
                'type' => $type,
                'performed_at' => $performedAt,
                'performed_on' => $performedOn,
                'note' => $note,
                'performed_by_user_id' => $actor->getKey(),
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
            PlantCareSchedule::syncFlags($lockedPlant);

            return true;
        }, 3);
    }
}
