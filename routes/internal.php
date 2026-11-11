<?php

use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\WaGatewayWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Internal API routes — diverifikasi per endpoint dengan secret header.
 */

Route::post('/payment/callback', [PaymentWebhookController::class, 'handle'])
    ->middleware('api.internal.secret');

Route::middleware('api.wa.webhook')->group(function (): void {
    Route::post('/wag/status', [WaGatewayWebhookController::class, 'handle']);
    Route::post('/wag/webhook', [WaGatewayWebhookController::class, 'handleIncoming']);
});
