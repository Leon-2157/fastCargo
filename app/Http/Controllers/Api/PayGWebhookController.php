<?php

namespace App\Http\Controllers\Api;

use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Services\AuditLogService;
use App\Modules\Shared\Services\PayGPaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayGWebhookController extends Controller
{
    /**
     * Handle incoming signed settlement webhook from PayG microservice or MacroDroid listener.
     */
    public function handle(Request $request, PayGPaymentGatewayService $paygService): JsonResponse
    {
        $ref = trim((string) ($request->input('transaction_ref')
            ?? $request->input('partnerReferenceNo')
            ?? $request->input('referenceNo')
            ?? ''));

        $rawStatus = $request->input('status') ?? $request->input('transactionStatus');
        $status = $rawStatus !== null ? strtoupper(trim((string) $rawStatus)) : '';

        $rawEvent = $request->input('event');
        $event = $rawEvent !== null ? strtolower(trim((string) $rawEvent)) : '';

        // If neither is specified, default to settlement (backward compatibility)
        if ($status === '' && $event === '') {
            $status = 'SETTLEMENT';
            $event = 'payment.settled';
        }

        if ($ref === '') {
            $isNonSettlementStatus = in_array($status, ['FAILED', 'EXPIRED', 'CANCELLED', 'FAILURE', 'PENDING'], true)
                || in_array($event, ['payment.failed', 'payment.expired', 'payment.cancelled', 'payment.pending'], true);

            if ($isNonSettlementStatus) {
                Log::warning('PayG webhook rejected: non-settlement event missing transaction_ref', [
                    'status' => $status,
                    'event' => $event,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Missing transaction_ref parameter.',
                ], 400);
            }

            $hasNotificationKeys = $request->hasAny([
                'notification_text',
                'text',
                'message',
                'body',
                'title',
                'amount',
            ]);

            if (! $hasNotificationKeys) {
                Log::warning('PayG webhook rejected: missing transaction_ref', [
                    'payload' => $request->all(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Missing transaction_ref parameter.',
                ], 400);
            }

            return $this->handleNotificationAutoMatch($request, $paygService);
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

    /**
     * Auto-match an Android notification webhook (e.g. MacroDroid Free) to a unique 24h pending transaction by exact IDR amount.
     */
    protected function handleNotificationAutoMatch(Request $request, PayGPaymentGatewayService $paygService): JsonResponse
    {
        $notificationText = trim((string) (
            $request->input('notification_text')
            ?? $request->input('text')
            ?? $request->input('message')
            ?? $request->input('body')
            ?? ''
        ));
        $notificationTitle = trim((string) ($request->input('title') ?? ''));
        $appPackage = trim((string) ($request->input('app_package') ?? $request->input('package') ?? ''));
        $rawAmountInput = $request->input('amount');

        $matchedAmount = null;
        if ($rawAmountInput !== null && $rawAmountInput !== '') {
            if (is_numeric($rawAmountInput)) {
                $numeric = (float) $rawAmountInput;
                $matchedAmount = $numeric > 0 && abs($numeric - round($numeric)) < 0.001
                    ? (int) round($numeric)
                    : null;
            } elseif (is_string($rawAmountInput)) {
                $matchedAmount = PayGPaymentGatewayService::extractIdrAmount($rawAmountInput);
            }
        } else {
            if ($notificationText !== '') {
                $matchedAmount = PayGPaymentGatewayService::extractIdrAmount($notificationText);
            }

            if ($matchedAmount === null && $notificationTitle !== '') {
                $matchedAmount = PayGPaymentGatewayService::extractIdrAmount($notificationTitle);
            }
        }

        if ($matchedAmount === null || $matchedAmount <= 0) {
            Log::warning('PayG notification webhook rejected: could not parse positive IDR amount', [
                'notification_text' => $notificationText,
                'title' => $notificationTitle,
                'app_package' => $appPackage,
            ]);

            AuditLogService::record(
                'PAYG_WEBHOOK_UNPARSABLE_AMOUNT',
                'PaymentWebhook',
                null,
                null,
                [
                    'notification_text' => $notificationText,
                    'notification_title' => $notificationTitle,
                    'app_package' => $appPackage,
                ],
                $request,
                null,
                'SYSTEM'
            );

            return response()->json([
                'success' => false,
                'message' => 'Unable to extract a valid positive IDR amount from notification payload.',
            ], 422);
        }

        return DB::transaction(function () use (
            $request,
            $paygService,
            $matchedAmount,
            $notificationText,
            $notificationTitle,
            $appPackage
        ): JsonResponse {
            $candidates = PaymentTransaction::query()
                ->where('payment_status', PaymentTransaction::STATUS_PENDING)
                ->where('amount', (float) $matchedAmount)
                ->where('created_at', '>=', now()->subHours(24))
                ->whereHas('invoice', function ($query): void {
                    $query->where('payment_status', '!=', AdmInvoice::STATUS_PAID_SETTLED);
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($candidates->isEmpty()) {
                Log::info('PayG notification webhook unmatched', [
                    'matched_amount' => $matchedAmount,
                    'app_package' => $appPackage,
                ]);

                AuditLogService::record(
                    'PAYG_WEBHOOK_UNMATCHED',
                    'PaymentWebhook',
                    null,
                    null,
                    [
                        'matched_amount' => $matchedAmount,
                        'notification_text' => $notificationText,
                        'notification_title' => $notificationTitle,
                        'app_package' => $appPackage,
                    ],
                    $request,
                    null,
                    'SYSTEM'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'No matching pending transaction found.',
                    'matched_amount' => $matchedAmount,
                ], 404);
            }

            $distinctInvoiceIds = $candidates->pluck('invoice_id')->filter()->unique()->values();
            if ($distinctInvoiceIds->count() > 1) {
                Log::warning('PayG notification webhook ambiguous amount match across multiple invoices', [
                    'matched_amount' => $matchedAmount,
                    'invoice_ids' => $distinctInvoiceIds->all(),
                ]);

                AuditLogService::record(
                    'PAYG_WEBHOOK_AMBIGUOUS_MATCH',
                    'PaymentWebhook',
                    null,
                    null,
                    [
                        'matched_amount' => $matchedAmount,
                        'candidate_invoice_ids' => $distinctInvoiceIds->all(),
                        'candidate_transaction_refs' => $candidates->pluck('transaction_ref')->all(),
                        'notification_text' => $notificationText,
                        'app_package' => $appPackage,
                    ],
                    $request,
                    null,
                    'SYSTEM'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Ambiguous match: multiple unpaid invoices share the exact pending amount within 24 hours.',
                    'matched_amount' => $matchedAmount,
                    'candidate_count' => $distinctInvoiceIds->count(),
                ], 409);
            }

            $matchedTx = $candidates->first();
            $gatewayData = array_merge($request->all(), [
                'source' => 'MACRODROID_NOTIFICATION',
                'matched_amount' => $matchedAmount,
                'notification_text' => $notificationText,
                'notification_title' => $notificationTitle,
                'app_package' => $appPackage,
                'matched_at' => now()->toIso8601String(),
            ]);

            $result = $paygService->processSettlement($matchedTx->transaction_ref, $gatewayData);
            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 404);
            }

            AuditLogService::record(
                'PAYG_WEBHOOK_AUTO_MATCHED',
                'PaymentTransaction',
                (string) $matchedTx->id,
                ['payment_status' => PaymentTransaction::STATUS_PENDING],
                [
                    'payment_status' => PaymentTransaction::STATUS_SETTLEMENT,
                    'transaction_ref' => $matchedTx->transaction_ref,
                    'invoice_id' => $result['invoice']?->id,
                    'matched_amount' => $matchedAmount,
                    'app_package' => $appPackage,
                ],
                $request,
                null,
                'SYSTEM'
            );

            return response()->json([
                'success' => true,
                'message' => 'Notification matched, payment settled, and escrow funded successfully.',
                'transaction_ref' => $matchedTx->transaction_ref,
                'invoice_id' => $result['invoice']?->id,
                'matched_amount' => $matchedAmount,
                'settlement_status' => 'PAID_SETTLED',
            ]);
        });
    }
}
