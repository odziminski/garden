<?php

use App\Http\Controllers\MonthlyPlantReportController;
use Illuminate\Support\Facades\Route;

Route::get('/monthly-report', MonthlyPlantReportController::class)->name('monthly-report.index');
