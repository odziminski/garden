<?php

use App\Http\Controllers\PlantObservationController;
use Illuminate\Support\Facades\Route;

Route::post('/plants/{plant}/observations', [PlantObservationController::class, 'store'])
    ->middleware('can:view,plant')
    ->name('plants.observations.store');
