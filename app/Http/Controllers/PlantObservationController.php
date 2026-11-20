<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Services\PlantObservations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlantObservationController extends Controller
{
    public function store(Request $request, Plant $plant, PlantObservations $observations): RedirectResponse
    {
        $returnToObservations = to_route('plants', $plant)->withFragment('observations');
        try {
            $observations->record($request->user(), $plant, $request->all());
        } catch (ValidationException $exception) {
            throw $exception->errorBag('plantObservation')->redirectTo($returnToObservations->getTargetUrl());
        }

        return $returnToObservations->with('plantObservationStatus', 'Observation added.');
    }
}
