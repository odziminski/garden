<?php

use App\Http\Controllers\NeedsController;
use Illuminate\Support\Facades\Route;

Route::get('/today', [NeedsController::class, 'today'])->name('care.today');
Route::post('/plants/{plant}/care-logs', [NeedsController::class, 'storeCareLog'])
    ->middleware('can:view,plant')
    ->name('care-logs.store');
Route::delete('/plants/{plant}/care-logs/{entry}', [NeedsController::class, 'undo'])
    ->middleware('can:view,plant')->whereNumber('entry')->name('care-logs.undo');
Route::get('/plants/{plant}/care-logs/{entry}/edit', [NeedsController::class, 'editCareLog'])
    ->middleware('can:view,plant')->whereNumber('entry')->name('care-logs.edit');
Route::patch('/plants/{plant}/care-logs/{entry}', [NeedsController::class, 'correctCareLog'])
    ->middleware('can:view,plant')->whereNumber('entry')->name('care-logs.update');
Route::post('/plants/{plant}/care-actions/{type}/snooze', [NeedsController::class, 'snooze'])
    ->middleware('can:view,plant')->name('care-actions.snooze');
Route::post('/plants/{plant}/care-actions/{type}/early', [NeedsController::class, 'logEarly'])
    ->middleware('can:view,plant')->name('care-actions.early');
