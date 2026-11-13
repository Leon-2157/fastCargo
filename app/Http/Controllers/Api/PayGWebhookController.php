<?php

namespace App\Http\Controllers\Api;

use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Services\PayGPaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class PayGWebhookController extends Controller
{
    /**
     * Handle incoming signed settlement webhook from PayG microservice.
     */
    public function handle(Request $request, PayGPaymentGatewayService $paygService): JsonResponse
    {
        $ref = (string) ($request->input('transaction_ref')
            ?? $request->input('partnerReferenceNo')
            ?? $request->input('referenceNo')
            ?? '');

        $rawStatus = $request->input('status') ?? $request->input('transactionStatus');
        $status = $rawStatus !== null ? strtoupper(trim((string) $rawStatus)) : '';

        $rawEvent = $request->input('event');
        $event = $rawEvent !== null ? strtolower(trim((string) $rawEvent)) : '';

        // If neither is specified, default to settlement (backward compatibility)
        if ($status === '' && $event === '') {
            $status = 'SETTLEMENT';
            $event = 'payment.settled';
        }

        if (empty($ref)) {
            Log::warning('PayG webhook rejected: missing transaction_ref', [
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Missing transaction_ref parameter.',
            ], 400);
        }

        Log::info('PayG webhook received', [
            'reference' => $ref,
            'status' => $status,
            'event' => $event,
        ]);

        // 1. Failure / Expiry / Cancellation Handling
        if (in_array($status, ['EXPIRED', 'FAILED', 'CANCELLED'], true)
            || in_array($event, ['payment.expired', 'payment.failed', 'payment.cancelled'], true)) {
            $resolvedStatus = in_array($status, ['EXPIRED', 'FAILED', 'CANCELLED'], true)
                ? $status
                : strtoupper(explode('.', $event)[1] ?? 'FAILED');

            $transaction = PaymentTransaction::where('transaction_ref', $ref)->first();
            if (! $transaction) {
                return response()->json([
                    'success' => false,
                    'message' => "Transaction {$ref} not found.",
                ], 404);
            }

            if ($transaction->payment_status !== 'SETTLEMENT') {
                $transaction->update([
                    'payment_status' => $resolvedStatus,
                    'raw_gateway_payload' => array_merge($transaction->raw_gateway_payload ?? [], $request->all(), [
                        'terminal_status_at' => now()->toIso8601String(),
                    ]),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Payment status {$resolvedStatus} recorded successfully.",
                'transaction_ref' => $ref,
                'status' => $resolvedStatus,
            ]);
        }

        // 2. Settlement Handling
        if (in_array($status, ['SUCCESS', 'SETTLEMENT', 'PAID', '00'], true) || $event === 'payment.settled') {
            $result = $paygService->processSettlement($ref, $request->all());

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment settled and escrow funded successfully.',
                'transaction_ref' => $ref,
                'invoice_id' => $result['invoice']?->id,
                'settlement_status' => 'PAID_SETTLED',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Payment status {$status} noted.",
            'transaction_ref' => $ref,
        ]);
    }
}
