<?php

namespace App\Http\Controllers;

use App\Models\GardenMembership;
use App\Models\Plant;
use App\Services\PlantCareAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlantTaskAssignmentController extends Controller
{
    public function __invoke(Request $request, Plant $plant, string $task): RedirectResponse
    {
        $actor = $request->user();
        $assigneeId = $request->validate(['assignee_id' => ['present', 'nullable', 'integer']])['assignee_id'];

        DB::transaction(function () use ($actor, $plant, $task, $assigneeId) {
            $lockedPlant = PlantCareAccess::lock($plant, $actor);
            abort_unless($lockedPlant->isOwnedBy($actor), 404);
            $lockedTask = $lockedPlant->careTasks()->active()->lockForUpdate()->findOrFail($task);

            if ($assigneeId !== null && (int) $assigneeId !== $actor->id) {
                $membership = GardenMembership::query()->forPlant($lockedPlant)
                    ->where('member_id', $assigneeId)->lockForUpdate()->first();
                if ($membership === null) {
                    throw ValidationException::withMessages(['assignee_id' => 'Choose someone who has access to this garden.']);
                }
            }

            $lockedTask->forceFill(['assignee_id' => $assigneeId])->save();
        }, 3);

        return redirect()->to(route('plants', $plant).'#custom-tasks')->with('custom_task_status', 'Assignment saved.');
    }
}
