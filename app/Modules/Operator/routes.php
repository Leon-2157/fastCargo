<?php

use App\Modules\Operator\Controllers\Auth\ForgotPasswordController;
use App\Modules\Operator\Controllers\Auth\LoginController;
use App\Modules\Operator\Controllers\Auth\ResetPasswordController;
use App\Modules\Operator\Controllers\Auth\TwoFactorController;
use App\Modules\Operator\Controllers\BookingOpsController;
use App\Modules\Operator\Controllers\DashboardController;
use App\Modules\Operator\Controllers\EpodController;
use App\Modules\Operator\Controllers\FleetTelemetryController;
use App\Modules\Operator\Controllers\IncidentController;
use App\Modules\Operator\Controllers\ManifestController;
use App\Modules\Operator\Controllers\ProfileController;
use App\Modules\Operator\Controllers\TrackingOpsController;
use App\Modules\Shared\Controllers\TwoFactorResetController;
use Illuminate\Support\Facades\Route;

// Auth — IP whitelist sudah di-enforce oleh middleware group di web.php
Route::middleware('guest:operator')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('operator.login');
    Route::post('/login', [LoginController::class, 'login']);
    // Link registrasi dari undangan admin
    Route::get('/register/{token}', [LoginController::class, 'showRegister'])->name('operator.register');
    Route::post('/register/{token}', [LoginController::class, 'register']);

    // Password reset routes
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('operator.password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:6,1')->name('operator.password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('operator.password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('operator.password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('operator.logout');

// 2FA challenge — setelah login tapi belum verified
Route::middleware('auth:operator')->group(function () {
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'showChallenge'])->name('operator.2fa.challenge');
    Route::post('/two-factor/verify', [TwoFactorController::class, 'verify'])->name('operator.2fa.verify');
    Route::get('/two-factor/setup', [TwoFactorController::class, 'showSetup'])->name('operator.2fa.setup');
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('operator.2fa.enable');
    Route::get('/two-factor/reset', [TwoFactorResetController::class, 'showResetForm'])->name('operator.2fa.reset');
    Route::post('/two-factor/reset/send', [TwoFactorResetController::class, 'sendOtp'])->name('operator.2fa.reset.send');
    Route::get('/two-factor/reset/verify', [TwoFactorResetController::class, 'showVerifyForm'])->name('operator.2fa.reset.verify.form');
    Route::post('/two-factor/reset/verify', [TwoFactorResetController::class, 'verifyOtp'])->name('operator.2fa.reset.verify');
});

// Dashboard — wajib auth + 2FA verified
Route::middleware(['auth:operator', 'operator.2fa.verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('operator.dashboard');
    Route::get('/map-data', [DashboardController::class, 'mapData'])->name('operator.dashboard.map');

    Route::prefix('manifests')->name('operator.manifests.')->group(function () {
        Route::get('/', [ManifestController::class, 'index'])->name('index');
        Route::get('/create', [ManifestController::class, 'create'])->name('create');
        Route::post('/', [ManifestController::class, 'store'])->name('store');
        Route::get('/{manifest}', [ManifestController::class, 'show'])->name('show');
        Route::put('/{manifest}', [ManifestController::class, 'update'])->name('update');
        Route::post('/{manifest}/seal', [ManifestController::class, 'seal'])->name('seal');
    });

    Route::prefix('bookings')->name('operator.bookings.')->group(function () {
        Route::get('/', [BookingOpsController::class, 'index'])->name('index');
        Route::get('/{booking}', [BookingOpsController::class, 'show'])->name('show');
        Route::post('/{booking}/approve', [BookingOpsController::class, 'approve'])->name('approve');
        Route::post('/{booking}/reject', [BookingOpsController::class, 'reject'])->name('reject');
        Route::post('/{booking}/assign', [BookingOpsController::class, 'assignToManifest'])->name('assign');
    });

    Route::prefix('tracking')->name('operator.tracking.')->group(function () {
        Route::get('/', [TrackingOpsController::class, 'index'])->name('index');
        Route::post('/{booking}/event', [TrackingOpsController::class, 'addEvent'])->name('event');
    });

    Route::prefix('epod')->name('operator.epod.')->group(function () {
        Route::get('/{booking}', [EpodController::class, 'showForm'])->name('form');
        Route::post('/{booking}', [EpodController::class, 'store'])->name('store');
    });

    Route::prefix('incidents')->name('operator.incidents.')->group(function () {
        Route::get('/', [IncidentController::class, 'index'])->name('index');
        Route::get('/create/{booking}', [IncidentController::class, 'create'])->name('create');
        Route::post('/', [IncidentController::class, 'store'])->name('store');
        Route::get('/{incident}', [IncidentController::class, 'show'])->name('show');
    });

    Route::prefix('fleet')->name('operator.fleet.')->group(function () {
        Route::get('/', [FleetTelemetryController::class, 'index'])->name('index');
    });

    Route::prefix('profile')->name('operator.profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::post('/duty', [ProfileController::class, 'toggleDuty'])->name('duty');
    });
});
