<?php

use App\Modules\Landing\Controllers\LandingController;
use App\Modules\Landing\Controllers\OperatorApplicationController;
use App\Modules\Landing\Controllers\TrackingPublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');

// Form aplikasi operator dari landing page
Route::post('/apply/operator', [OperatorApplicationController::class, 'store'])->name('operator.apply');
