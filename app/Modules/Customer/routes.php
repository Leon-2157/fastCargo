<?php

use App\Modules\Customer\Controllers\Auth\LoginController;
use App\Modules\Customer\Controllers\Auth\RegisterController;
use App\Modules\Customer\Controllers\Auth\TwoFactorController;
use App\Modules\Customer\Controllers\BookingController;
use App\Modules\Customer\Controllers\DashboardController;
use App\Modules\Customer\Controllers\InvoiceController;
use App\Modules\Customer\Controllers\ProfileController;
use App\Modules\Customer\Controllers\TrackingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth routes — tanpa middleware auth
Route::middleware('guest:customer')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('customer.login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showForm'])->name('customer.register');
    Route::post('/register', [RegisterController::class, 'register']);
});

Route::get('/email/verify', fn () => view('customer.auth.verify-email'))
    ->middleware('auth:customer')
    ->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [RegisterController::class, 'verifyEmail'])
    ->middleware(['auth:customer', 'signed', 'throttle:6,1'])
    ->name('verification.verify');
Route::post('/email/verification-notification', function (Request $request) {
    if ($request->user('customer')->hasVerifiedEmail()) {
        return redirect()->route('customer.login');
    }

    $request->user('customer')->sendEmailVerificationNotification();

    return back()->with('status', 'Verification link sent.');
})->middleware(['auth:customer', 'throttle:6,1'])->name('customer.verification.send');

Route::post('/logout', [LoginController::class, 'logout'])->name('customer.logout');

// 2FA setup setelah email verify
Route::middleware(['auth:customer', 'verified'])->group(function () {
    Route::get('/two-factor/setup', [TwoFactorController::class, 'showSetup'])->name('customer.2fa.setup');
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('customer.2fa.enable');
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'showChallenge'])->name('customer.2fa.challenge');
    Route::post('/two-factor/verify', [TwoFactorController::class, 'verify'])->name('customer.2fa.verify');
});

// Dashboard routes — wajib auth + 2FA sudah verified
Route::middleware(['auth:customer', 'customer.2fa.verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('customer.dashboard');
    Route::get('/map-data', [DashboardController::class, 'mapData'])->name('customer.dashboard.map');

    Route::prefix('bookings')->name('customer.bookings.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::get('/create', [BookingController::class, 'create'])->name('create');
        Route::post('/', [BookingController::class, 'store'])->name('store');
        Route::get('/{booking}', [BookingController::class, 'show'])->name('show');
        Route::delete('/{booking}', [BookingController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('tracking')->name('customer.tracking.')->group(function () {
        Route::get('/', [TrackingController::class, 'index'])->name('index');
        Route::get('/{booking}', [TrackingController::class, 'show'])->name('show');
    });

    Route::prefix('invoices')->name('customer.invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
        Route::get('/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('pdf');
        Route::post('/{invoice}/pay', [InvoiceController::class, 'initPayment'])->name('pay');
    });

    Route::prefix('profile')->name('customer.profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::post('/upgrade-corporate', [ProfileController::class, 'requestCorporateUpgrade'])->name('upgrade');
    });
});
