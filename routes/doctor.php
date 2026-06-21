<?php

use Illuminate\Support\Facades\Route;
use SajjadHossain\Doctor\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('doctor.dashboard');
Route::get('/history', [DashboardController::class, 'history'])->name('doctor.history');
Route::post('/scan', [DashboardController::class, 'triggerScan'])->name('doctor.scan.trigger');
