<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PlantsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/',[PlantsController::class, 'getRandomPlant'])->name('welcome');


Route::get('/login', function () {
    return view('login');
});
Route::get('/about', function () {
    return view('about');
})->name('about');

Route::get('/browse',[PlantsController::class, 'displayPlants'])->name('browse');



Route::get('/users',[UserController::class, 'displayUserData'])->name('users');

Route::get('plants/{id}',[PlantsController::class, 'displaySinglePlant'])->name('plants');

Route::get('/updateW/{id}',[PlantsController::class, 'updateWatering'])->name('updateWatering');
Route::get('/updateF/{id}',[PlantsController::class, 'updateFertilizing'])->name('updateFertilizing');


Route::get('/add-plant', function () {
    return view('add-plant');
})->name('add-plant');

Route::post('/add-plant-query',[PlantsController::class, 'store'])->name('add-plant-query');


Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

Route::put('/users/edit-profile',[UserController::class, 'editUserProfile'])->name('editUserProfile');

Route::get('/delete-plant/{id}',[PlantsController::class, 'deletePlant'])->name('deletePlant');
Route::get('/edit-plant/{id}',[PlantsController::class, 'displayEditPlant'])->name('displayEditPlant');
Route::patch('/edit-plant-query/{id}',[PlantsController::class, 'editPlant'])->name('editPlant');

