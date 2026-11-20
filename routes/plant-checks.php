<?php

use App\Http\Controllers\PlantCheckController;
use Illuminate\Support\Facades\Route;

Route::patch('/plants/{plant}/check-schedule', [PlantCheckController::class, 'schedule'])
    ->middleware('can:manage,plant')->name('plant-checks.schedule');
Route::post('/plants/{plant}/checks', [PlantCheckController::class, 'store'])
    ->middleware('can:view,plant')->name('plant-checks.store');
Route::delete('/plants/{plant}/checks/skip', [PlantCheckController::class, 'undoSkip'])
    ->middleware('can:view,plant')->name('plant-checks.undo-skip');
Route::delete('/plants/{plant}/checks/{check}', [PlantCheckController::class, 'undo'])
    ->middleware('can:view,plant')->whereNumber('check')->name('plant-checks.undo');
