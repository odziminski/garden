<?php

use App\Http\Controllers\NeedsController;
use Illuminate\Support\Facades\Route;

Route::get('/today', [NeedsController::class, 'today'])->name('care.today');
Route::post('/plants/{plant}/care-logs', [NeedsController::class, 'storeCareLog'])
    ->middleware('can:view,plant')
    ->name('care-logs.store');
