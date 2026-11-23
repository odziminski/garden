<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantRequest;
use App\Models\GardenMembership;
use App\Models\History;
use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Services\PlantCareSchedule;
use App\Services\PlantChecks;
use App\Services\PlantIdentification;
use App\Services\PlantJournalPhotos;
use App\Services\PlantPhoto;
use App\Services\PlantPhotoStorageException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PlantsController extends Controller
{
    public function getRandomPlant(Request $request): View
    {
        $plant = $request->user()?->plants()
            ->with('owner', 'history', 'needs', 'plantData', 'checkSchedule')
            ->inRandomOrder()
            ->first();

        return view('welcome', $plant ? $this->plantDetails($plant) : ['plant' => null]);
    }

    public function displaySinglePlant(Request $request, Plant $plant, PlantIdentification $identification): View
    {
        $canManage = $request->user()->can('manage', $plant);
        $canObserve = $canManage || $request->user()->can('observe', $plant);
        $details = $this->plantDetails($plant->load('owner', 'history', 'needs', 'plantData', 'checkSchedule', 'latestPlantCheck.checkedBy:id,email')) + [
            'canManage' => $canManage,
            'canObserve' => $canObserve,
            'identificationStatus' => $identification->status($plant),
            'careLogs' => $plant->careLogs()->with('performedBy:id,email', 'plantCheck')->latest('performed_on')->latest('performed_at')->latest('id')
                ->simplePaginate(15, ['*'], 'care_page')->withQueryString()->fragment('care-history'),
            'plantChecks' => $plant->plantChecks()->whereNull('voided_at')->with('checkedBy:id,email')
                ->latest('checked_on')->latest('id')
                ->simplePaginate(10, ['*'], 'check_page')->withQueryString()->fragment('plant-checks'),
        ];

        $details['customTasks'] = $plant->careTasks()->active()->with('assignee')
            ->orderByRaw('CASE WHEN snoozed_until > due_on THEN snoozed_until ELSE due_on END')->orderBy('id')->get();
        $details['customTaskCompletions'] = $plant->taskCompletions()
            ->select('plant_task_completions.*')
            ->with('plantTask:id,name', 'completedBy:id,email')
            ->latest('plant_task_completions.completed_at')->latest('plant_task_completions.id')
            ->simplePaginate(10, ['*'], 'task_history_page')->withQueryString()->fragment('custom-tasks');

        if ($canObserve) {
            $details['observations'] = $plant->observations()
                ->with('actor:id,email', 'followUpTask:id,name,due_on,snoozed_until,archived_at,completed_at')
                ->latest('observed_on')->latest('id')->simplePaginate(15, ['*'], 'observation_page')
                ->withQueryString()->fragment('observations');
        }
        if ($canManage) {
            $details['observationPhotos'] = PlantPhotoEntry::query()->forPlant($plant)->latest('taken_on')->latest('id')
                ->simplePaginate(30, ['id', 'taken_on', 'note'], 'photo_choice_page')->withQueryString()->fragment('observations');
            $details['assignableMembers'] = GardenMembership::query()->forPlant($plant)
                ->with('member:id,email')->get()->pluck('member');
        }
        if ($canManage) {
            $details['photoJournalEntries'] = PlantPhotoEntry::query()->forPlant($plant)->latest('taken_on')->latest('id')
                ->simplePaginate(12, ['*'], 'journal_page')->withQueryString()->fragment('photo-journal');
            $firstPhoto = $request->integer('compare_first');
            $secondPhoto = $request->integer('compare_second');
            $comparison = $firstPhoto > 0
                ? PlantPhotoEntry::query()->forPlant($plant)->whereKey([$firstPhoto, $secondPhoto])
                    ->get(['id', 'taken_on', 'note'])->keyBy('id')
                : collect();
            $details['firstComparisonPhoto'] = $comparison->get($firstPhoto);
            $details['comparisonPhotos'] = $details['firstComparisonPhoto'] && $firstPhoto !== $secondPhoto
                && $comparison->has($secondPhoto) ? $comparison->sortBy([['taken_on', 'asc'], ['id', 'asc']]) : collect();
        }

        return view('plants', $details);
    }

    public function displayPlants(Request $request): View
    {
        $room = $request->query('room');
        $room = is_string($room) && mb_strlen($room) <= 80 ? $room : '';
        $visiblePlants = Plant::query()->visibleTo($request->user());
        $rooms = (clone $visiblePlants)->whereNotNull('room')->distinct()->orderBy('room')->pluck('room');
        $plants = $visiblePlants->when($room !== '', fn ($query) => $query->where('room', $room))
            ->with('owner', 'history', 'needs', 'checkSchedule')
            ->orderByDesc(History::query()->select('watered_at')
                ->whereColumn('history.plant_id', 'plants.id')->orderByDesc('watered_at')->limit(1))
            ->orderBy('plants.id')
            ->simplePaginate(24)->withQueryString();

        return view('browse', ['plants' => $plants, 'rooms' => $rooms, 'room' => $room]);
    }

    public function displayEditPlant(Plant $plant): View
    {
        return view('edit-plant', ['plant' => $plant->load('needs', 'checkSchedule')]);
    }

    public function showCover(Plant $plant, PlantJournalPhotos $photos): Response
    {
        abort_unless($plant->cover_public_id && $plant->cover_format, 404);

        try {
            return $photos->serve($plant->cover_public_id, $plant->cover_format, $plant->cover_provider, ttl: 60);
        } catch (PlantPhotoStorageException $exception) {
            abort(503, $exception->getMessage());
        }
    }

    public function store(StorePlantRequest $request, PlantPhoto $photos, PlantChecks $checks): RedirectResponse
    {
        $photo = $photos->fromRequest($request);

        DB::transaction(function () use ($request, $photo, $photos, $checks) {
            $now = now();
            $plant = $request->user()->plants()->create($request->validatedProfile() + [
                'avatar' => 'plant.png',
                'created_at' => $now,
            ]);

            $plant->history()->create();
            $plant->needs()->create($request->validatedSchedule());

            $firstCheck = $request->validated('first_check_on');
            $intervalDays = $request->validated('check_interval_days');
            if ($firstCheck !== null || $intervalDays !== null) {
                $checks->configureLockedPlant($plant, true, $firstCheck, $intervalDays === null ? null : (int) $intervalDays);
            }

            if ($photo) {
                $photos->attachCover($plant, $photo['public_id'], $photo['format'], provider: $photo['provider']);
            }
        }, 3);

        return redirect()->route('browse')->with('message', 'Plant added successfully');
    }

    public function editPlant(StorePlantRequest $request, Plant $plant, PlantPhoto $photos): RedirectResponse
    {
        $photo = $photos->fromRequest($request);

        DB::transaction(function () use ($request, $plant, $photo, $photos) {
            $lockedPlant = $request->user()->plants()->lockForUpdate()->findOrFail($plant->id);
            $lockedPlant->fill($request->validatedProfile());

            if ($photo) {
                $photos->discardCover($lockedPlant);
                $photos->attachCover($lockedPlant, $photo['public_id'], $photo['format'], provider: $photo['provider']);
            } else {
                $lockedPlant->save();
            }

            $needs = $lockedPlant->needs()->firstOrNew();
            $needs->fill($request->validatedSchedule());
            if ($lockedPlant->checkSchedule?->enabled) {
                $needs->watering_frequency = 0;
            }
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
