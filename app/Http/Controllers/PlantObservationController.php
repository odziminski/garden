<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Services\PlantObservations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlantObservationController extends Controller
{
    public function store(Request $request, Plant $plant, PlantObservations $observations): RedirectResponse
    {
        $photoPage = $request->integer('photo_choice_page', 1);
        $returnToObservations = to_route('plants', [$plant, 'photo_choice_page' => $photoPage > 1 ? $photoPage : null])
            ->withFragment('observations');
        try {
            $observations->record($request->user(), $plant, $request->all());
        } catch (ValidationException $exception) {
            throw $exception->errorBag('plantObservation')->redirectTo($returnToObservations->getTargetUrl());
        }

        return $returnToObservations->with('plantObservationStatus', 'Observation added.');
    }

    public function edit(Request $request, Plant $plant, string $entry): View
    {
        $observation = $plant->observations()->findOrFail($entry);
        abort_unless($observation->canBeCorrectedBy($request->user(), $plant), 404);

        return view('plants.edit-observation', ['plant' => $plant, 'entry' => $observation]);
    }

    public function update(Request $request, Plant $plant, string $entry, PlantObservations $observations): RedirectResponse
    {
        try {
            $observations->update($request->user(), $plant, $entry, $request->all());
        } catch (ValidationException $exception) {
            throw $exception->errorBag('plantObservation')->redirectTo(route('plants.observations.edit', [$plant, $entry]));
        }

        return to_route('plants', $plant)->withFragment('observations')
            ->with('plantObservationStatus', 'Observation corrected.');
    }
}
