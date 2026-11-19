<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class NeedsController extends Controller
{
    public function updateWatering(Plant $plant): RedirectResponse
    {
        return $this->recordCare($plant, 'need_watering', 'watered_at');
    }

    public function updateFertilizing(Plant $plant): RedirectResponse
    {
        return $this->recordCare($plant, 'need_fertilizing', 'fertilized_at');
    }

    private function recordCare(Plant $plant, string $flag, string $timestamp): RedirectResponse
    {
        DB::transaction(function () use ($plant, $flag, $timestamp) {
            $lockedPlant = Plant::query()->lockForUpdate()->findOrFail($plant->id);
            $needs = $lockedPlant->needs()->firstOrFail();
            $history = $lockedPlant->history()->firstOrNew();

            $needs->update([$flag => false]);
            $history->fill([$timestamp => now()])->save();
        }, 3);

        return back();
    }
}
