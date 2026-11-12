<?php

namespace App\Modules\Shared\Services;

use App\Jobs\SendWhatsAppNotification;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DanaPaymentGatewayService
{
    public function __construct(
        protected EscrowSettlementService $escrowService
    ) {}

    /**
     * Cek apakah API key produksi atau sandbox Dana Business sudah dikonfigurasi.
     */
    public function isLiveConfigured(): bool
    {
        $clientId = AdmSystemSetting::get('dana_client_id', config('dana.client_id'));
        $secret = AdmSystemSetting::get('dana_client_secret', config('dana.client_secret'));

        return ! empty($clientId) && ! empty($secret);
    }

    /**
     * Generate checkout transaksi QRIS Dana (Live Dana Snap API atau Smart Simulator).
     *
     * @return array{
     *     transaction_ref: string,
     *     amount: float,
     *     currency: string,
     *     qr_string: string,
     *     qr_svg: string,
     *     expires_at: Carbon,
     *     is_mock: bool,
     *     merchant_name: string
     * }
     */
    public function generateQrisCheckout(AdmInvoice $invoice, User $customer): array
    {
        $amount = (float) $invoice->total_invoice_amount;
        $currency = $invoice->currency ?? 'IDR';
        $transactionRef = 'DANA-QRIS-'.date('Ymd').'-'.strtoupper(Str::random(8));
        $expiresAt = now()->addMinutes(15);
        $merchantName = 'FASTCARGO ASEAN LOGISTICS';

        // Format EMVCo Standardized QRIS Payload
        $qrString = $this->buildEmvcoPayload($transactionRef, $amount, $merchantName);

        // Jika kredensial resmi Dana sudah aktif, hubungi endpoint resmi Dana
        $isMock = true;
        if ($this->isLiveConfigured()) {
            try {
                $liveResult = $this->callDanaGenerateQrApi($invoice, $transactionRef, $amount);
                if (! empty($liveResult['qr_content'])) {
                    $qrString = $liveResult['qr_content'];
                    $isMock = false;
                }
            } catch (Throwable $e) {
                Log::warning('Dana live API call failed, falling back to Smart Sandbox', ['error' => $e->getMessage()]);
            }
        }

        // Render QR Code SVG resolusi tinggi
        $renderer = new ImageRenderer(
            new RendererStyle(280, 2),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($qrString);

        // Buat record PaymentTransaction awal (PENDING)
        PaymentTransaction::create([
            'id' => Str::uuid()->toString(),
            'invoice_id' => $invoice->id,
            'payer_user_id' => $customer->id,
            'payment_gateway' => 'DANA',
            'payment_channel' => 'QRIS',
            'transaction_ref' => $transactionRef,
            'amount' => $amount,
            'payment_status' => 'PENDING',
            'raw_gateway_payload' => [
                'merchant_name' => $merchantName,
                'gateway_mode' => $isMock ? 'SMART_SANDBOX' : 'DANA_LIVE_SNAP',
                'qr_string' => $qrString,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);

        return [
            'transaction_ref' => $transactionRef,
            'amount' => $amount,
            'currency' => $currency,
            'qr_string' => $qrString,
            'qr_svg' => $qrSvg,
            'expires_at' => $expiresAt,
            'is_mock' => $isMock,
            'merchant_name' => $merchantName,
        ];
    }

    /**
     * Proses pelunasan pembayaran Dana (dipanggil oleh Webhook resmi atau tombol simulasi sandbox).
     *
     * @param  array<string, mixed>  $gatewayData
     * @return array{success: bool, message: string, invoice?: AdmInvoice, transaction?: PaymentTransaction}
     */
    public function processSettlement(string $transactionRef, array $gatewayData = []): array
    {
        return DB::transaction(function () use ($transactionRef, $gatewayData) {
            $transaction = PaymentTransaction::where('transaction_ref', $transactionRef)->first();
            if (! $transaction) {
                return ['success' => false, 'message' => "Transaction {$transactionRef} not found."];
            }

            if ($transaction->payment_status === 'SETTLEMENT') {
                return ['success' => true, 'message' => 'Transaction already settled.', 'transaction' => $transaction];
            }

            $now = now();
            $transaction->update([
                'payment_status' => 'SETTLEMENT',
                'paid_at' => $now,
                'raw_gateway_payload' => array_merge($transaction->raw_gateway_payload ?? [], $gatewayData, [
                    'settled_at' => $now->toIso8601String(),
                ]),
            ]);

            // Update Invoice
            $invoice = AdmInvoice::with('booking')->find($transaction->invoice_id);
            if ($invoice) {
                $invoice->update([
                    'payment_status' => 'PAID_SETTLED',
                    'paid_at' => $now,
                ]);

                // Fund Escrow Ledger secara otomatis
                $this->escrowService->fundEscrowFromPaidInvoice($invoice);

                // Kirim notifikasi WhatsApp ke Customer jika nomor telepon tersedia
                $payer = User::find($transaction->payer_user_id);
                if ($payer && ! empty($payer->phone)) {
                    $waMsg = "*[FastCargo Payment Settlement]*\n\n"
                        ."Hello {$payer->name},\n"
                        .'Your Dana Business QRIS payment of *IDR '.number_format((float) $transaction->amount, 0, ',', '.').'* '
                        ."for invoice *#{$invoice->invoice_number}* has been confirmed!\n\n"
                        ."Funds are securely held in the FastCargo ASEAN Escrow Ledger and will be released to the operating hub across shipment milestones.\n\n"
                        .'Track shipment: '.url('/track/'.($invoice->booking?->tracking_number ?? ''));

                    SendWhatsAppNotification::dispatch($payer->phone, $waMsg, 'payment_settlement');
                }
            }

            return [
                'success' => true,
                'message' => 'Payment settled and escrow funded successfully.',
                'invoice' => $invoice,
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * Susun string payload QRIS sesuai standar EMVCo Indonesia (ASPI).
     */
    protected function buildEmvcoPayload(string $ref, float $amount, string $merchantName): string
    {
        $amountStr = number_format($amount, 2, '.', '');

        return '000201010212'
            .'26580014ID.DANA.WWW0118936009110022334455'
            .'51440014ID.LINKAJA.WWW0118936009110022334455'
            .'52045411'
            .'5303360'
            .'54'.sprintf('%02d', strlen($amountStr)).$amountStr
            .'5802ID'
            .'59'.sprintf('%02d', strlen($merchantName)).$merchantName
            .'6007JAKARTA'
            .'62'.sprintf('%02d', strlen($ref) + 4).'07'.sprintf('%02d', strlen($ref)).$ref
            .'6304'.strtoupper(substr(md5($ref.$amountStr), 0, 4));
    }

    /**
     * Panggilan HTTP ke endpoint resmi Dana Business Snap API (jika mode LIVE).
     *
     * @return array<string, mixed>
     */
    protected function callDanaGenerateQrApi(AdmInvoice $invoice, string $ref, float $amount): array
    {
        $baseUrl = AdmSystemSetting::get('dana_env', config('dana.env')) === 'production'
            ? 'https://api.dana.id'
            : 'https://api-sandbox.dana.id';

        $clientId = AdmSystemSetting::get('dana_client_id', config('dana.client_id'));
        $secret = AdmSystemSetting::get('dana_client_secret', config('dana.client_secret'));

        $response = Http::withHeaders([
            'X-CLIENT-KEY' => $clientId,
            'X-TIMESTAMP' => now()->toIso8601String(),
            'Content-Type' => 'application/json',
        ])->timeout(8)->post("{$baseUrl}/v1.0/qr/qr-mpm-generate", [
            'partnerReferenceNo' => $ref,
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'merchantId' => AdmSystemSetting::get('dana_merchant_id', config('dana.merchant_id')),
        ]);

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw new \RuntimeException('Dana API returned non-success response: '.$response->body());
    }
}
