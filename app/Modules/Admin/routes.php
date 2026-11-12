<?php

use App\Modules\Admin\Controllers\AuditTrailController;
use App\Modules\Admin\Controllers\Auth\LoginController;
use App\Modules\Admin\Controllers\Auth\TwoFactorController;
use App\Modules\Admin\Controllers\CorporateCreditController;
use App\Modules\Admin\Controllers\DashboardController;
use App\Modules\Admin\Controllers\FinanceController;
use App\Modules\Admin\Controllers\IncidentReviewController;
use App\Modules\Admin\Controllers\InvoiceAdminController;
use App\Modules\Admin\Controllers\KycController;
use App\Modules\Admin\Controllers\OperatorInvitationController;
use App\Modules\Admin\Controllers\SystemSettingsController;
use App\Modules\Admin\Controllers\UserManagementController;
use App\Modules\Admin\Controllers\WaGatewayController;
use App\Modules\Shared\Controllers\TwoFactorResetController;
use Illuminate\Support\Facades\Route;

// Auth — tidak ada landing page, langsung /login
Route::middleware('guest:admin')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.login'));
    Route::get('/login', [LoginController::class, 'showForm'])->name('admin.login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('admin.logout');

Route::middleware('auth:admin')->group(function () {
    Route::get('/two-factor/setup', [TwoFactorController::class, 'showSetup'])->name('admin.2fa.setup');
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('admin.2fa.enable');
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'showChallenge'])->name('admin.2fa.challenge');
    Route::post('/two-factor/verify', [TwoFactorController::class, 'verify'])->name('admin.2fa.verify');
    Route::get('/two-factor/reset', [TwoFactorResetController::class, 'showResetForm'])->name('admin.2fa.reset');
    Route::post('/two-factor/reset/send', [TwoFactorResetController::class, 'sendOtp'])->name('admin.2fa.reset.send');
    Route::get('/two-factor/reset/verify', [TwoFactorResetController::class, 'showVerifyForm'])->name('admin.2fa.reset.verify.form');
    Route::post('/two-factor/reset/verify', [TwoFactorResetController::class, 'verifyOtp'])->name('admin.2fa.reset.verify');
});

// Dashboard — wajib auth admin + 2FA terverifikasi
Route::middleware(['auth:admin', 'admin.2fa.verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/dashboard/map-data', [DashboardController::class, 'mapData'])->name('admin.dashboard.map');

    Route::prefix('users')->name('admin.users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/{user}', [UserManagementController::class, 'show'])->name('show');
        Route::post('/{user}/suspend', [UserManagementController::class, 'suspend'])->name('suspend');
        Route::post('/{user}/restore', [UserManagementController::class, 'restore'])->name('restore');
        Route::post('/{user}/block', [UserManagementController::class, 'block'])->name('block');
        Route::post('/{user}/reset-2fa', [UserManagementController::class, 'resetTwoFactor'])->name('reset-2fa');
    });

    Route::prefix('invitations')->name('admin.invitations.')->group(function () {
        Route::get('/', [OperatorInvitationController::class, 'index'])->name('index');
        Route::get('/{invitation}', [OperatorInvitationController::class, 'show'])->name('show');
        Route::post('/{invitation}/approve', [OperatorInvitationController::class, 'approve'])->name('approve');
        Route::post('/{invitation}/reject', [OperatorInvitationController::class, 'reject'])->name('reject');
        Route::post('/{invitation}/resend', [OperatorInvitationController::class, 'resend'])->name('resend');
    });

    Route::prefix('kyc')->name('admin.kyc.')->group(function () {
        Route::get('/', [KycController::class, 'index'])->name('index');
        Route::get('/{user}', [KycController::class, 'show'])->name('show');
        Route::post('/{user}/approve', [KycController::class, 'approve'])->name('approve');
        Route::post('/{user}/reject', [KycController::class, 'reject'])->name('reject');
    });

    Route::prefix('finance')->name('admin.finance.')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        Route::get('/escrow', [FinanceController::class, 'escrow'])->name('escrow');
        Route::post('/escrow/{escrow}/release', [FinanceController::class, 'releaseEscrow'])->name('escrow.release');
    });

    Route::prefix('invoices')->name('admin.invoices.')->group(function () {
        Route::get('/', [InvoiceAdminController::class, 'index'])->name('index');
        Route::get('/{invoice}', [InvoiceAdminController::class, 'show'])->name('show');
        Route::post('/{invoice}/mark-paid', [InvoiceAdminController::class, 'markPaid'])->name('markPaid');
    });

    Route::prefix('incidents')->name('admin.incidents.')->group(function () {
        Route::get('/', [IncidentReviewController::class, 'index'])->name('index');
        Route::get('/{incident}', [IncidentReviewController::class, 'show'])->name('show');
        Route::post('/{incident}/approve', [IncidentReviewController::class, 'approve'])->name('approve');
        Route::post('/{incident}/reject', [IncidentReviewController::class, 'reject'])->name('reject');
    });

    Route::prefix('audit')->name('admin.audit.')->group(function () {
        Route::get('/', [AuditTrailController::class, 'index'])->name('index');
    });

    Route::prefix('settings')->name('admin.settings.')->group(function () {
        Route::get('/', [SystemSettingsController::class, 'index'])->name('index');
        Route::put('/', [SystemSettingsController::class, 'update'])->name('update');
        Route::post('/ai/switch', [SystemSettingsController::class, 'switchAiProvider'])->name('ai.switch');
        Route::post('/mail/test', [SystemSettingsController::class, 'sendTestEmail'])->name('mail.test');
    });

    Route::prefix('wa-gateway')->name('admin.wa.')->group(function () {
        Route::get('/', [WaGatewayController::class, 'index'])->name('index');
        Route::get('/status', [WaGatewayController::class, 'status'])->name('status');
        Route::get('/qr', [WaGatewayController::class, 'getQr'])->name('qr');
        Route::post('/test', [WaGatewayController::class, 'testSend'])->name('test');
        Route::post('/reset', [WaGatewayController::class, 'resetSession'])->name('reset');
    });

    Route::prefix('credits')->name('admin.credits.')->group(function () {
        Route::get('/', [CorporateCreditController::class, 'index'])->name('index');
        Route::get('/{credit}', [CorporateCreditController::class, 'show'])->name('show');
        Route::put('/{credit}', [CorporateCreditController::class, 'update'])->name('update');
    });
});
