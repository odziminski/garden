<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantRequest;
use App\Models\GardenMembership;
use App\Models\History;
use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Services\PlantCareSchedule;
use App\Services\PlantIdentification;
use App\Services\PlantJournalPhotos;
use App\Services\PlantPhoto;
use App\Services\PlantPhotoStorageException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlantsController extends Controller
{
    public function getRandomPlant(Request $request): View
    {
        $plant = $request->user()?->plants()
            ->with('owner', 'history', 'needs', 'plantData')
            ->inRandomOrder()
            ->first();

        return view('welcome', $plant ? $this->plantDetails($plant) : ['plant' => null]);
    }

    public function displaySinglePlant(Request $request, Plant $plant, PlantIdentification $identification): View
    {
        $canManage = $request->user()->can('manage', $plant);
        $details = $this->plantDetails($plant->load('owner', 'history', 'needs', 'plantData')) + [
            'canManage' => $canManage,
            'identificationStatus' => $canManage ? $identification->status($plant) : null,
            'careLogs' => $plant->careLogs()->with('performedBy:id,email')->latest('performed_on')->latest('performed_at')->latest('id')
                ->simplePaginate(15, ['*'], 'care_page')->withQueryString()->fragment('care-history'),
        ];

        $details['customTasks'] = $plant->careTasks()->active()->with('assignee')
            ->orderByRaw('CASE WHEN snoozed_until > due_on THEN snoozed_until ELSE due_on END')->orderBy('id')->get();
        $details['customTaskCompletions'] = $plant->taskCompletions()
            ->select('plant_task_completions.*')
            ->with('plantTask:id,name', 'completedBy:id,email')
            ->latest('plant_task_completions.completed_at')->latest('plant_task_completions.id')
            ->simplePaginate(10, ['*'], 'task_history_page')->withQueryString()->fragment('custom-tasks');

        $observations = $plant->observations()->with('actor:id,email', 'followUpTask:id,name,due_on,snoozed_until,archived_at,completed_at')
            ->latest('observed_on')->latest('id');
        if ($canManage) {
            $details['observationPhotos'] = PlantPhotoEntry::query()->forPlant($plant)->latest('taken_on')->latest('id')
                ->simplePaginate(30, ['id', 'taken_on', 'note'], 'photo_choice_page')->withQueryString()->fragment('observations');
            $details['assignableMembers'] = GardenMembership::query()->forPlant($plant)
                ->with('member:id,email')->get()->pluck('member');
        }
        $details['observations'] = $observations->simplePaginate(15, ['*'], 'observation_page')
            ->withQueryString()->fragment('observations');

        if ($canManage) {
            $details['photoJournalEntries'] = PlantPhotoEntry::query()->forPlant($plant)->latest('taken_on')->latest('id')
                ->simplePaginate(12, ['*'], 'journal_page')->withQueryString()->fragment('photo-journal');
        }

        return view('plants', $details);
    }

    public function displayPlants(Request $request): View
    {
        $plants = Plant::query()->visibleTo($request->user())
            ->with('owner', 'history', 'needs')
            ->orderByDesc(History::query()->select('watered_at')
                ->whereColumn('history.plant_id', 'plants.id')->orderByDesc('watered_at')->limit(1))
            ->orderBy('plants.id')
            ->simplePaginate(24)->withQueryString();

        return view('browse', ['plants' => $plants]);
    }

    public function displayEditPlant(Plant $plant): View
    {
        return view('edit-plant', ['plant' => $plant->load('needs')]);
    }

    public function showCover(Plant $plant, PlantJournalPhotos $photos): RedirectResponse
    {
        abort_unless($plant->cover_public_id && $plant->cover_format, 404);

        try {
            $url = $photos->downloadUrl($plant->cover_public_id, $plant->cover_format, now(), ttl: 60);
        } catch (PlantPhotoStorageException $exception) {
            abort(503, $exception->getMessage());
        }

        return redirect()->away($url, 302, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function store(StorePlantRequest $request, PlantPhoto $photos): RedirectResponse
    {
        $photo = $photos->fromRequest($request);

        DB::transaction(function () use ($request, $photo, $photos) {
            $now = now();
            $plant = $request->user()->plants()->create([
                'avatar' => 'plant.png',
                'name' => $request->validated('name'),
                'created_at' => $now,
            ]);

            $plant->history()->create();
            $plant->needs()->create($request->validatedSchedule());

            if ($photo) {
                $photos->attachCover($plant, $photo['public_id'], $photo['format']);
            }
        }, 3);

        return redirect()->route('browse')->with('message', 'Plant added successfully');
    }

    public function editPlant(StorePlantRequest $request, Plant $plant, PlantPhoto $photos): RedirectResponse
    {
        $photo = $photos->fromRequest($request);

        DB::transaction(function () use ($request, $plant, $photo, $photos) {
            $lockedPlant = $request->user()->plants()->lockForUpdate()->findOrFail($plant->id);
            $lockedPlant->name = $request->validated('name');

            if ($photo) {
                $photos->discardCover($lockedPlant);
                $photos->attachCover($lockedPlant, $photo['public_id'], $photo['format']);
            } else {
                $lockedPlant->save();
            }

            $needs = $lockedPlant->needs()->firstOrNew();
            $needs->fill($request->validatedSchedule());
            $lockedPlant->setRelation('needs', $needs);
            PlantCareSchedule::syncFlags($lockedPlant);
        }, 3);

        return redirect()->route('browse');
    }

    public function deletePlant(Request $request, Plant $plant, PlantPhoto $photos): RedirectResponse
    {
        DB::transaction(function () use ($request, $plant, $photos) {
            $lockedPlant = $request->user()->plants()->lockForUpdate()->findOrFail($plant->id);
            $photos->discardPlantMedia($lockedPlant);
            $lockedPlant->plantData()->delete();
            $lockedPlant->delete();
        }, 3);

        return redirect()->route('browse');
    }

    private function plantDetails(Plant $plant): array
    {
        $asOf = now($plant->careTimezone());
        $lastWatering = $plant->history?->watered_at ?? $plant->created_at;
        $lastFertilizing = $plant->history?->fertilized_at ?? $plant->created_at;
        $nextWatering = $plant->nextWatering($asOf);
        $nextFertilizing = $plant->nextFertilizing($asOf);

        return [
            'plant' => $plant,
            'nextWatering' => $nextWatering,
            'nextFertilizing' => $nextFertilizing,
            'caretakingTime' => $asOf,
            'wateringPercentage' => $this->getPercentageOfCareDate($lastWatering?->valueOf(), $nextWatering?->valueOf()),
            'fertilizingPercentage' => $this->getPercentageOfCareDate($lastFertilizing?->valueOf(), $nextFertilizing?->valueOf()),
            'wordWatering' => $nextWatering?->isPast() ? 'was' : 'in',
            'wordFertilizing' => $nextFertilizing?->isPast() ? 'was' : 'in',
        ];
    }

    public function getPercentageOfCareDate(?float $from, ?float $to): int
    {
        if ($from === null || $to === null || $to <= $from) {
            return 0;
        }

        return (int) max(0, min(100, (now()->valueOf() - $from) * 100 / ($to - $from)));
    }
}
