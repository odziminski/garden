<?php

use App\Http\Controllers\PlantTaskController;
use App\Http\Controllers\PlantTaskAssignmentController;
use Illuminate\Support\Facades\Route;

Route::post('/plants/{plant}/tasks', [PlantTaskController::class, 'store'])
    ->middleware('can:manage,plant')
    ->name('plant-tasks.store');

Route::post('/plants/{plant}/tasks/{task}/complete', [PlantTaskController::class, 'complete'])
    ->middleware('can:view,plant')
    ->whereNumber('task')
    ->name('plant-tasks.complete');

Route::post('/plants/{plant}/tasks/{task}/snooze', [PlantTaskController::class, 'snooze'])
    ->middleware('can:view,plant')->whereNumber('task')->name('plant-tasks.snooze');
Route::delete('/plants/{plant}/tasks/{task}/completions/{completion}', [PlantTaskController::class, 'undo'])
    ->middleware('can:view,plant')->whereNumber(['task', 'completion'])->name('plant-tasks.undo');

Route::get('/plants/{plant}/tasks/{task}/edit', [PlantTaskController::class, 'edit'])
    ->middleware('can:manage,plant')->whereNumber('task')->name('plant-tasks.edit');
Route::patch('/plants/{plant}/tasks/{task}', [PlantTaskController::class, 'update'])
    ->middleware('can:manage,plant')->whereNumber('task')->name('plant-tasks.update');

Route::delete('/plants/{plant}/tasks/{task}', [PlantTaskController::class, 'archive'])
    ->middleware('can:manage,plant')
    ->whereNumber('task')
    ->name('plant-tasks.archive');
Route::put('/plants/{plant}/tasks/{task}/assignment', PlantTaskAssignmentController::class)
    ->middleware('can:manage,plant')->whereNumber('task')->name('plant-tasks.assignment');
