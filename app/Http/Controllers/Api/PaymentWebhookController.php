<?php

namespace App\Http\Controllers\Api;

use App\Modules\Shared\Services\DanaPaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Handle payment notification & settlement callbacks from Dana Business QRIS or payment gateways.
     */
    public function handle(Request $request, DanaPaymentGatewayService $danaService): JsonResponse
    {
        $ref = (string) ($request->input('partnerReferenceNo')
            ?? $request->input('originalPartnerReferenceNo')
            ?? $request->input('transaction_ref')
            ?? $request->input('referenceNo')
            ?? $request->input('order_id')
            ?? '');

        $status = strtoupper((string) ($request->input('transactionStatus')
            ?? $request->input('status')
            ?? $request->input('latestTransactionStatus')
            ?? 'SETTLEMENT'));

        if (empty($ref)) {
            Log::warning('Payment webhook rejected: missing partnerReferenceNo / transaction_ref', [
                'payload' => $request->all(),
            ]);

            return response()->json([
                'responseCode' => '4002500',
                'responseMessage' => 'Missing partnerReferenceNo or transaction_ref parameter.',
            ], 400);
        }

        Log::info('Payment webhook received', [
            'reference' => $ref,
            'status' => $status,
        ]);

        if (in_array($status, ['SUCCESS', 'SETTLEMENT', 'PAID', '00'], true)) {
            $result = $danaService->processSettlement($ref, $request->all());

            if (! $result['success']) {
                return response()->json([
                    'responseCode' => '4042500',
                    'responseMessage' => $result['message'],
                ], 404);
            }

            return response()->json([
                'responseCode' => '2002500',
                'responseMessage' => 'Successful',
                'partnerReferenceNo' => $ref,
                'data' => [
                    'invoice_id' => $result['invoice']?->id,
                    'payment_status' => 'SETTLEMENT',
                ],
            ]);
        }

        return response()->json([
            'responseCode' => '2002501',
            'responseMessage' => "Payment status {$status} noted without settlement.",
            'partnerReferenceNo' => $ref,
        ]);
    }
}
