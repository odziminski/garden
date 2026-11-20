<?php

use App\Http\Controllers\PlantObservationController;
use Illuminate\Support\Facades\Route;

Route::post('/plants/{plant}/observations', [PlantObservationController::class, 'store'])
    ->middleware('can:view,plant')
    ->name('plants.observations.store');

Route::get('/plants/{plant}/observations/{entry}/edit', [PlantObservationController::class, 'edit'])
    ->middleware('can:view,plant')->whereNumber('entry')->name('plants.observations.edit');
Route::patch('/plants/{plant}/observations/{entry}', [PlantObservationController::class, 'update'])
    ->middleware('can:view,plant')->whereNumber('entry')->name('plants.observations.update');
