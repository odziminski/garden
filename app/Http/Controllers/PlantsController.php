<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantRequest;
use App\Models\Plant;
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
            ->with('history', 'needs', 'plantData')
            ->inRandomOrder()
            ->first();

        return view('welcome', $plant ? $this->plantDetails($plant) : ['plant' => null]);
    }

    public function displaySinglePlant(Plant $plant): View
    {
        return view('plants', $this->plantDetails($plant->load('history', 'needs', 'plantData')));
    }

    public function displayPlants(Request $request): View
    {
        $plants = $request->user()->plants()
            ->with('history', 'needs')
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

            $plant->history()->create(['watered_at' => $now, 'fertilized_at' => $now]);
            $plant->needs()->create($request->safe()->only(['watering_frequency', 'fertilizing_frequency']));

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
            $lockedPlant->needs()->updateOrCreate([], $request->safe()->only([
                'watering_frequency',
                'fertilizing_frequency',
            ]));
        }, 3);

        return redirect()->route('browse');
    }

    public function deletePlant(Plant $plant): RedirectResponse
    {
        DB::transaction(function () use ($plant) {
            $lockedPlant = Plant::query()->lockForUpdate()->findOrFail($plant->id);
            $lockedPlant->plantData()->delete();
            $lockedPlant->delete();
        }, 3);

        return redirect()->route('browse');
    }

    private function plantDetails(Plant $plant): array
    {
        $lastWatering = $plant->history?->watered_at;
        $lastFertilizing = $plant->history?->fertilized_at;
        $nextWatering = $plant->nextWatering();
        $nextFertilizing = $plant->nextFertilizing();

        return [
            'plant' => $plant,
            'nextWatering' => $nextWatering,
            'nextFertilizing' => $nextFertilizing,
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
