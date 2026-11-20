<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantTaskRequest;
use App\Models\Plant;
use App\Models\PlantTask;
use App\Services\PlantCareAccess;
use App\Services\PlantTaskSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $recorded = DB::transaction(function () use ($request, $actor, $plant, $task): bool {
            $lockedPlant = PlantCareAccess::lock($plant, $actor);
            $lockedTask = PlantTask::query()->whereBelongsTo($lockedPlant)->lockForUpdate()->findOrFail($task);
            $scheduledFor = $request->validate(['scheduled_for' => ['required', 'date_format:Y-m-d']])['scheduled_for'];
            $asOf = now($lockedPlant->careTimezone());
            $completedOn = $asOf->toDateString();

            if (!$lockedTask->isActive() || $lockedTask->due_on !== $scheduledFor || $lockedTask->due_on > $completedOn) {
                return false;
            }

            $completedAt = $asOf->copy()->utc();
            $lockedTask->completions()->create([
                'scheduled_for' => $lockedTask->due_on,
                'completed_on' => $completedOn,
                'completed_at' => $completedAt,
                'completed_by_user_id' => $actor->getKey(),
            ]);

            $nextDueOn = PlantTaskSchedule::nextDueOn($asOf, $lockedTask->repeat_days);
            if ($nextDueOn === null) {
                $lockedTask->completed_at = $completedAt;
            } else {
                $lockedTask->due_on = $nextDueOn;
            }

            $lockedTask->save();

            return true;
        }, 3);

        $response = back(302, [], $this->taskListUrl($plant));

        return $recorded
            ? $response->with('custom_task_status', 'Task completed.')
            : $response->with('custom_task_notice', 'This task has already been completed or is no longer due.');
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
