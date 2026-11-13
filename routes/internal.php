<?php

use App\Http\Controllers\Api\PayGWebhookController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\WaGatewayWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Internal API routes — diverifikasi per endpoint dengan secret header.
 */

Route::post('/payment/callback', [PaymentWebhookController::class, 'handle'])
    ->middleware('api.internal.secret');

Route::post('/payg/webhook', [PayGWebhookController::class, 'handle'])
    ->middleware('api.payg.webhook');

Route::middleware('api.wa.webhook')->group(function (): void {
    Route::post('/wag/status', [WaGatewayWebhookController::class, 'handle']);
    Route::post('/wag/webhook', [WaGatewayWebhookController::class, 'handleIncoming']);
});
