<?php

use App\Http\Controllers\PlantTaskController;
use Illuminate\Support\Facades\Route;

Route::post('/plants/{plant}/tasks', [PlantTaskController::class, 'store'])
    ->middleware('can:manage,plant')
    ->name('plant-tasks.store');

Route::post('/plants/{plant}/tasks/{task}/complete', [PlantTaskController::class, 'complete'])
    ->middleware('can:view,plant')
    ->whereNumber('task')
    ->name('plant-tasks.complete');

Route::delete('/plants/{plant}/tasks/{task}', [PlantTaskController::class, 'archive'])
    ->middleware('can:manage,plant')
    ->whereNumber('task')
    ->name('plant-tasks.archive');
