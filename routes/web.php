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


Route::get('/',[UserController::class, 'displayPlants'])->name('welcome');

Route::get('/login', function () {
    return view('login');
});
Route::get('/about', function () {
    return view('about');
})->name('about');



Route::get('/users',[UserController::class, 'displayUserData'])->name('users');

Route::get('plants/{id}',[PlantsController::class, 'displaySinglePlant'])->name('plants');

Route::get('/add-plant',[UserController::class,'addPlant'])->name('add-plant');


Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');


