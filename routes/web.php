<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\NeedsController;
use App\Http\Controllers\PlantsController;
use App\Http\Controllers\ReminderSettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlantsController::class, 'getRandomPlant'])->name('welcome');

Auth::routes();

Route::middleware('auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/browse', [PlantsController::class, 'displayPlants'])->name('browse');
    Route::view('/add-plant', 'add-plant')->name('add-plant');
    Route::post('/add-plant-query', [PlantsController::class, 'store'])->name('add-plant-query');

    Route::get('/users', [UserController::class, 'displayUserData'])->name('users');
    Route::put('/users/edit-profile', [UserController::class, 'editUserProfile'])->name('editUserProfile');
    Route::put('/users/reminders', [ReminderSettingsController::class, 'update'])->name('reminders.update');

    require __DIR__.'/care.php';
    require __DIR__.'/plant-tasks.php';
    require __DIR__.'/plant-observations.php';
    require __DIR__.'/garden-sharing.php';
    require __DIR__.'/monthly-report.php';

    Route::middleware('can:view,plant')->group(function () {
        Route::get('/plants/{plant}', [PlantsController::class, 'displaySinglePlant'])->name('plants');
        Route::post('/updateW/{plant}', [NeedsController::class, 'updateWatering'])->name('updateWatering');
        Route::post('/updateF/{plant}', [NeedsController::class, 'updateFertilizing'])->name('updateFertilizing');
    });

    Route::middleware('can:manage,plant')->group(function () {
        Route::get('/edit-plant/{plant}', [PlantsController::class, 'displayEditPlant'])->name('displayEditPlant');
        Route::patch('/edit-plant-query/{plant}', [PlantsController::class, 'editPlant'])->name('editPlant');
        Route::delete('/delete-plant/{plant}', [PlantsController::class, 'deletePlant'])->name('deletePlant');

        require __DIR__.'/photo-journal.php';
    });
});
