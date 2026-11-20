<?php

use App\Http\Controllers\PlantHandoffController;
use Illuminate\Support\Facades\Route;

Route::prefix('handoff')->name('handoff.')->controller(PlantHandoffController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'invite')->middleware(['verified', 'throttle:5,1'])->name('store');
    Route::delete('/{handoff}', 'revoke')->whereNumber('handoff')->name('destroy');
    Route::get('/join/{token}', 'showInvitation')->middleware('verified')->where('token', '[a-f0-9]{64}')->name('invitation.show');
    Route::post('/join/{token}', 'accept')->middleware('verified')->where('token', '[a-f0-9]{64}')->name('invitation.accept');
});
