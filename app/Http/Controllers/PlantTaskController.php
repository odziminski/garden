<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantTaskRequest;
use App\Models\Plant;
use App\Models\PlantTask;
use App\Models\PlantTaskCompletion;
use App\Services\PlantCareAccess;
use App\Services\PlantTaskSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlantTaskController extends Controller
{
    public function store(StorePlantTaskRequest $request, Plant $plant): RedirectResponse
    {
        $actor = $request->user();

        DB::transaction(function () use ($request, $plant, $actor) {
            $lockedPlant = PlantCareAccess::lock($plant, $actor);
            abort_unless($lockedPlant->isOwnedBy($actor), 404);

            PlantTask::query()->create($request->validated() + ['plant_id' => $lockedPlant->getKey()]);
        }, 3);

        return redirect()->to($this->taskListUrl($plant))->with('custom_task_status', 'Task added.');
    }

    public function complete(Request $request, Plant $plant, string $task): RedirectResponse
    {
        $actor = $request->user();

        $completion = DB::transaction(function () use ($request, $actor, $plant, $task): ?PlantTaskCompletion {
            $lockedPlant = PlantCareAccess::lock($plant, $actor);
            $lockedTask = PlantTask::query()->whereBelongsTo($lockedPlant)->lockForUpdate()->findOrFail($task);
            $details = $request->validate([
                'scheduled_for' => ['required', 'date_format:Y-m-d'],
                'complete_early' => ['sometimes', 'boolean'],
            ]);
            $scheduledFor = $details['scheduled_for'];
            $asOf = now($lockedPlant->careTimezone());
            $completedOn = $asOf->toDateString();

            if (!$lockedTask->isActive() || $lockedTask->due_on !== $scheduledFor
                || ($lockedTask->effectiveDueOn() > $completedOn && !($details['complete_early'] ?? false))) {
                return null;
            }

            $completedAt = $asOf->copy()->utc();
            $base = $scheduledFor > $completedOn ? CarbonImmutable::parse($scheduledFor, $asOf->getTimezone()) : $asOf;
            $nextDueOn = PlantTaskSchedule::nextDueOn($base, $lockedTask->repeat_days);
            $completion = $lockedTask->completions()->create([
                'scheduled_for' => $lockedTask->due_on,
                'completed_on' => $completedOn,
                'completed_at' => $completedAt,
                'completed_by_user_id' => $actor->getKey(),
                'resulting_due_on' => $nextDueOn,
                'previous_snoozed_until' => $lockedTask->snoozed_until,
            ]);

            if ($nextDueOn === null) {
                $lockedTask->completed_at = $completedAt;
            } else {
                $lockedTask->due_on = $nextDueOn;
            }

            $lockedTask->snoozed_until = null;
            $lockedTask->save();

            return $completion;
        }, 3);

        $response = back(302, [], $this->taskListUrl($plant));

        return $completion
            ? $response->with('custom_task_status', 'Task completed.')
                ->with('custom_task_undo', [$plant->getKey(), $task, $completion->getKey()])
            : $response->with('custom_task_notice', 'This task has already been completed or is no longer due.');
    }

    public function undo(Request $request, Plant $plant, string $task, string $completion): RedirectResponse
    {
        $undone = DB::transaction(function () use ($request, $plant, $task, $completion): bool {
            $actor = $request->user();
            $lockedPlant = PlantCareAccess::lock($plant, $actor);
            $lockedTask = $lockedPlant->careTasks()->lockForUpdate()->findOrFail($task);
            $entry = $lockedTask->completions()->findOrFail($completion);
            abort_unless($lockedPlant->isOwnedBy($actor) || (int) $entry->completed_by_user_id === (int) $actor->getKey(), 404);

            $unchanged = $entry->resulting_due_on === null
                ? $lockedTask->completed_at?->eq($entry->completed_at)
                : $lockedTask->completed_at === null && $lockedTask->due_on === $entry->resulting_due_on;
            if (!$unchanged || $lockedTask->archived_at !== null || $lockedTask->snoozed_until !== null
                || $lockedTask->completions()->where('id', '>', $entry->getKey())->exists()) {
                return false;
            }

            $lockedTask->due_on = $entry->scheduled_for;
            $lockedTask->completed_at = null;
            $lockedTask->snoozed_until = $entry->previous_snoozed_until;
            $lockedTask->save();
            $entry->delete();

            return true;
        }, 3);

        $response = back(302, [], $this->taskListUrl($plant));

        return $undone
            ? $response->with('custom_task_status', 'Task completion undone.')
            : $response->with('custom_task_notice', 'The schedule has changed since this completion.');
    }

    public function snooze(Request $request, Plant $plant, string $task): RedirectResponse
    {
        $scheduledFor = $request->validate(['scheduled_for' => ['required', 'date_format:Y-m-d']])['scheduled_for'];
        $changed = DB::transaction(function () use ($request, $plant, $task, $scheduledFor): bool {
            $lockedPlant = PlantCareAccess::lock($plant, $request->user());
            $lockedTask = $lockedPlant->careTasks()->lockForUpdate()->findOrFail($task);
            $today = now($lockedPlant->careTimezone());
            if (!$lockedTask->isActive() || $lockedTask->due_on !== $scheduledFor || $lockedTask->effectiveDueOn() > $today->toDateString()) {
                return false;
            }

            $lockedTask->snoozed_until = $today->addDay()->toDateString();
            $lockedTask->save();

            return true;
        }, 3);

        return $changed
            ? back()->with('custom_task_status', 'Task snoozed until tomorrow.')
            : back()->with('custom_task_notice', 'This task is no longer due.');
    }

    public function edit(Plant $plant, string $task): View
    {
        return view('plants.edit-task', [
            'plant' => $plant,
            'task' => $plant->careTasks()->active()->findOrFail($task),
        ]);
    }

    public function update(StorePlantTaskRequest $request, Plant $plant, string $task): RedirectResponse
    {
        DB::transaction(function () use ($request, $plant, $task) {
            $actor = $request->user();
            $lockedPlant = PlantCareAccess::lock($plant, $actor);
            abort_unless($lockedPlant->isOwnedBy($actor), 404);
            $lockedTask = $lockedPlant->careTasks()->active()->lockForUpdate()->findOrFail($task);
            $details = $request->validated();
            if ($lockedTask->completions()->where('scheduled_for', $details['due_on'])->exists()) {
                throw ValidationException::withMessages(['due_on' => 'A completion is already recorded for that scheduled date.'])
                    ->errorBag('customTask');
            }

            if ($lockedTask->due_on !== $details['due_on']) {
                $lockedTask->snoozed_until = null;
            }
            $lockedTask->fill($details)->save();
        }, 3);

        return to_route('plants', $plant)->withFragment('custom-tasks')->with('custom_task_status', 'Task updated.');
    }

    public function archive(Request $request, Plant $plant, string $task): RedirectResponse
    {
        $actor = $request->user();

        DB::transaction(function () use ($actor, $plant, $task) {
            $lockedPlant = PlantCareAccess::lock($plant, $actor);
            abort_unless($lockedPlant->isOwnedBy($actor), 404);

            $lockedTask = PlantTask::query()->whereBelongsTo($lockedPlant)->lockForUpdate()->findOrFail($task);
            if ($lockedTask->isActive()) {
                $lockedTask->archived_at = now();
                $lockedTask->save();
            }
        }, 3);

        return redirect()->to($this->taskListUrl($plant))->with('custom_task_status', 'Task archived.');
    }

    private function taskListUrl(Plant $plant): string
    {
        return route('plants', $plant).'#custom-tasks';
    }
}
