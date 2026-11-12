<?php

use App\Http\Controllers\Api\PaymentWebhookController;
use App\Modules\Landing\Controllers\TrackingPublicController;
use Illuminate\Support\Facades\Route;

/*
 * Entry point utama — dispatch ke module routes berdasarkan subdomain.
 * Setiap module punya routes.php sendiri dengan middleware yang sesuai.
 */

$host = request()->getHost();

// Landing page — fastcargo.local atau fastcargo.my.id
Route::domain(config('app.domains.landing', 'fastcargo.local'))
    ->group(base_path('app/Modules/Landing/routes.php'));

// Public tracking — semua subdomain bisa akses /track/{resi}
Route::get('/track/{trackingNumber}', [TrackingPublicController::class, 'show'])
    ->name('tracking.public');

// Customer dashboard — customer.fastcargo.local
Route::domain(config('app.domains.customer', 'customer.fastcargo.local'))
    ->group(base_path('app/Modules/Customer/routes.php'));

// Operator dashboard — operator.fastcargo.local (VPN enforced via middleware)
Route::domain(config('app.domains.operator', 'operator.fastcargo.local'))
    ->middleware(['operator.vpn'])
    ->group(base_path('app/Modules/Operator/routes.php'));

// Admin dashboard — admin.fastcargo.local (private)
Route::domain(config('app.domains.admin', 'admin.fastcargo.local'))
    ->group(base_path('app/Modules/Admin/routes.php'));

// Internal API — webhook payment & waG callbacks (auth middleware per route group)
Route::prefix('api/internal')
    ->group(base_path('routes/internal.php'));

// Public external payment webhook (e.g. Dana Snap / QRIS Callback)
Route::post('/api/webhooks/dana', [PaymentWebhookController::class, 'handle']);

// Public external payment webhook (e.g. Dana Snap / QRIS Callback)
Route::post('/api/webhooks/dana', [PaymentWebhookController::class, 'handle']);
