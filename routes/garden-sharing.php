<?php

use App\Http\Controllers\GardenSharingController;
use Illuminate\Support\Facades\Route;

Route::prefix('garden-sharing')->name('garden-sharing.')->controller(GardenSharingController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/invitations', 'invite')->middleware(['verified', 'throttle:5,1'])->name('invitations.store');
    Route::delete('/invitations/{invitation}', 'revoke')->whereNumber('invitation')->name('invitations.destroy');
    Route::delete('/memberships/{membership}', 'removeMembership')->whereNumber('membership')->name('memberships.destroy');
    Route::get('/join/{token}', 'showInvitation')->middleware('verified')->where('token', '[a-f0-9]{64}')->name('invitations.show');
    Route::post('/join/{token}', 'accept')->middleware('verified')->where('token', '[a-f0-9]{64}')->name('invitations.accept');
});
