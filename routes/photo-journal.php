<?php

use App\Http\Controllers\PlantPhotoJournalController;
use Illuminate\Support\Facades\Route;

Route::prefix('/plants/{plant}/photo-journal')->name('plants.photo-journal.')->group(function () {
    Route::post('/', [PlantPhotoJournalController::class, 'store'])->name('store');
    Route::get('/{entry}', [PlantPhotoJournalController::class, 'show'])->whereNumber('entry')->name('show');
    Route::delete('/{entry}', [PlantPhotoJournalController::class, 'destroy'])->whereNumber('entry')->name('destroy');
});
