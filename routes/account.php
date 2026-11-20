<?php

use App\Http\Controllers\AccountDataController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/account/export', [AccountDataController::class, 'export'])->name('account.export');
Route::delete('/account', [AccountDataController::class, 'destroy'])->middleware('throttle:5,1')->name('account.destroy');

Route::middleware(['verified', 'throttle:10,1'])->group(function () {
    Route::put('/account/push', [PushSubscriptionController::class, 'store'])->name('account.push.store');
    Route::delete('/account/push', [PushSubscriptionController::class, 'destroy'])->name('account.push.destroy');
});
