<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantRequest;
use App\Models\Needs;
use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Services\PlantCareSchedule;
use App\Services\PlantJournalPhotos;
use App\Services\PlantPhoto;
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

    public function displaySinglePlant(Plant $plant): View
    {
        return view('plants', $this->plantDetails($plant->load('owner', 'history', 'needs', 'plantData')) + [
            'careLogs' => $plant->careLogs()->latest('performed_on')->latest('performed_at')->latest('id')
                ->simplePaginate(15, ['*'], 'care_page')->withQueryString()->fragment('care-history'),
            'photoJournalEntries' => PlantPhotoEntry::query()->forPlant($plant)->latest('taken_on')->latest('id')
                ->simplePaginate(12, ['*'], 'journal_page')->withQueryString()->fragment('photo-journal'),
        ]);
    }

    public function displayPlants(Request $request): View
    {
        $plants = $request->user()->plants()
            ->with('owner', 'history', 'needs')
            ->get()
            ->sortByDesc('history.watered_at');

        return view('browse', ['plants' => $plants]);
    }

    public function displayEditPlant(Plant $plant): View
    {
        return view('edit-plant', ['plant' => $plant->load('needs')]);
    }

    public function store(StorePlantRequest $request, PlantPhoto $photos): RedirectResponse
    {
        $photo = $photos->fromRequest($request);

        DB::transaction(function () use ($request, $photo) {
            $now = now();
            $plant = $request->user()->plants()->create([
                'avatar' => $photo['url'] ?? asset('images/plant.png'),
                'name' => $request->validated('name'),
                'created_at' => $now,
            ]);

            $plant->history()->create();
            $plant->needs()->create($request->safe()->only(Needs::SCHEDULE_FIELDS));

            if (!empty($photo['identification'])) {
                $plant->plantData()->create($photo['identification']);
            }
        }, 3);

        return redirect()->route('browse')->with('message', 'Plant added successfully');
    }

    public function editPlant(StorePlantRequest $request, Plant $plant, PlantPhoto $photos): RedirectResponse
    {
        $photo = $photos->fromRequest($request);

        DB::transaction(function () use ($request, $plant, $photo) {
            $lockedPlant = Plant::query()->lockForUpdate()->findOrFail($plant->id);
            $lockedPlant->name = $request->validated('name');

            if ($photo) {
                $lockedPlant->avatar = $photo['url'];
                if ($photo['identification']) {
                    $lockedPlant->plantData()->updateOrCreate([], $photo['identification']);
                } else {
                    $lockedPlant->plantData()->delete();
                }
            }

            $lockedPlant->save();
            $needs = $lockedPlant->needs()->firstOrNew();
            $needs->fill($request->safe()->only(Needs::SCHEDULE_FIELDS));
            $lockedPlant->setRelation('needs', $needs);
            PlantCareSchedule::syncFlags($lockedPlant);
        }, 3);

        return redirect()->route('browse');
    }

    public function deletePlant(Plant $plant, PlantJournalPhotos $photos): RedirectResponse
    {
        DB::transaction(function () use ($plant, $photos) {
            $lockedPlant = Plant::query()->lockForUpdate()->findOrFail($plant->id);
            $photos->discardAfterCommit($lockedPlant);
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
