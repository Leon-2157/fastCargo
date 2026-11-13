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

class PayGPaymentGatewayService
{
    public function __construct(
        protected EscrowSettlementService $escrowService
    ) {}

    /**
     * Get base URL for PayG microservice daemon.
     */
    public function getServiceUrl(): string
    {
        return rtrim((string) config('payg.service_url', 'http://payg:8085'), '/');
    }

    /**
     * Fetch real-time status of all PayG engines (SGpay, SoBpay, Simulator).
     *
     * @return array<string, mixed>
     */
    public function getGatewayStatus(): array
    {
        $url = $this->getServiceUrl();
        $timeout = (int) config('payg.timeout', 4);

        try {
            $response = Http::timeout($timeout)->get("{$url}/api/status");
            if ($response->successful()) {
                $data = $response->json();
                $data['reachable'] = true;

                return $data;
            }
        } catch (Throwable $e) {
            Log::debug('PayG daemon unreachable', ['error' => $e->getMessage()]);
        }

        // Graceful offline fallback snapshot
        $activeMode = (string) AdmSystemSetting::get('payg_active_mode', 'auto');
        $sobUsername = (string) AdmSystemSetting::get('payg_sobpay_username', '');
        $staticQr = (string) AdmSystemSetting::get('payg_sgpay_static_qr', '');

        return [
            'reachable' => false,
            'app' => 'FastCargo PayG Station (Standalone Sandbox)',
            'version' => '2.0.0',
            'active_mode' => $activeMode,
            'engines' => [
                'sgpay' => [
                    'engine' => 'sgpay',
                    'active' => true,
                    'connected' => ! empty($staticQr),
                    'details' => ! empty($staticQr) ? 'Static QRIS Configured' : 'Offline / Standalone',
                ],
                'sobpay' => [
                    'engine' => 'sobpay',
                    'active' => true,
                    'connected' => ! empty($sobUsername),
                    'details' => ! empty($sobUsername) ? "Connected (@{$sobUsername})" : 'Username not set',
                    'active_account' => $sobUsername,
                ],
                'simulator' => [
                    'engine' => 'simulator',
                    'active' => true,
                    'connected' => true,
                    'details' => 'Built-in Smart Sandbox Active',
                    'active_account' => 'SANDBOX-SIMULATOR',
                ],
            ],
            'active_pending' => PaymentTransaction::where('payment_status', 'PENDING')->count(),
            'total_settled' => PaymentTransaction::where('payment_status', 'SETTLEMENT')->count(),
        ];
    }

    /**
     * Generate multi-channel checkout via PayG (or standalone smart sandbox fallback).
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     transaction_ref: string,
     *     amount: float,
     *     payable_amount: float,
     *     unique_code: int,
     *     currency: string,
     *     qr_string: string,
     *     qr_svg: string,
     *     payment_url?: string,
     *     expires_at: Carbon,
     *     is_mock: bool,
     *     merchant_name: string,
     *     engine: string,
     *     channel: string,
     *     instructions?: array<string>
     * }
     */
    public function generateCheckout(AdmInvoice $invoice, User $customer, array $options = []): array
    {
        $amount = (float) $invoice->total_invoice_amount;
        $currency = $invoice->currency ?? 'IDR';
        $country = $options['country'] ?? 'Indonesia';
        $enginePref = $options['engine'] ?? AdmSystemSetting::get('payg_active_mode', 'auto');
        $channelPref = $options['channel'] ?? 'qris';
        $merchantName = 'FASTCARGO ASEAN LOGISTICS';

        $url = $this->getServiceUrl();
        $timeout = (int) config('payg.timeout', 5);

        // Try calling PayG microservice
        try {
            $resp = Http::timeout($timeout)->post("{$url}/api/checkout/create", [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $amount,
                'currency' => $currency,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone ?? '',
                'country' => $country,
                'engine_preference' => $enginePref,
                'channel_preference' => $channelPref,
                'merchant_name' => $merchantName,
            ]);

            if ($resp->successful() && $resp->json('success')) {
                $data = $resp->json('data');

                $ref = (string) $data['transaction_ref'];
                $payableAmount = (float) ($data['payable_amount'] ?? $amount);
                $uniqueCode = (int) ($data['unique_code'] ?? 0);
                $engine = (string) ($data['engine'] ?? 'simulator');
                $channel = (string) ($data['channel'] ?? 'QRIS');
                $qrString = (string) ($data['qr_string'] ?? '');
                $qrSvg = (string) ($data['qr_svg'] ?? '');
                $paymentUrl = (string) ($data['payment_url'] ?? '');
                $isMock = (bool) ($data['is_mock'] ?? false);
                $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : now()->addMinutes(15);

                if (empty($qrSvg) && ! empty($qrString)) {
                    $qrSvg = $this->renderQrSvg($qrString);
                }

                $this->recordPendingTransaction(
                    $invoice,
                    $customer,
                    $ref,
                    $payableAmount,
                    $engine,
                    $channel,
                    [
                        'merchant_name' => $merchantName,
                        'unique_code' => $uniqueCode,
                        'qr_string' => $qrString,
                        'payment_url' => $paymentUrl,
                        'is_mock' => $isMock,
                        'expires_at' => $expiresAt->toIso8601String(),
                    ]
                );

                return [
                    'transaction_ref' => $ref,
                    'amount' => $amount,
                    'payable_amount' => $payableAmount,
                    'unique_code' => $uniqueCode,
                    'currency' => $currency,
                    'qr_string' => $qrString,
                    'qr_svg' => $qrSvg,
                    'payment_url' => $paymentUrl,
                    'expires_at' => $expiresAt,
                    'is_mock' => $isMock,
                    'merchant_name' => $merchantName,
                    'engine' => $engine,
                    'channel' => $channel,
                    'instructions' => $data['instructions'] ?? [],
                ];
            }
        } catch (Throwable $e) {
            Log::info('PayG service checkout call failed, using built-in smart fallback', ['error' => $e->getMessage()]);
        }

        // Fallback Standalone Generator (Instant Simulator)
        $ref = 'PAYG-SIM-'.date('Ymd').'-'.strtoupper(Str::random(8));
        $expiresAt = now()->addMinutes(15);
        $qrString = $this->buildEmvcoPayload($ref, $amount, $merchantName);
        $qrSvg = $this->renderQrSvg($qrString);

        $this->recordPendingTransaction(
            $invoice,
            $customer,
            $ref,
            $amount,
            'SIMULATOR',
            'QRIS',
            [
                'merchant_name' => $merchantName,
                'gateway_mode' => 'SMART_SANDBOX',
                'qr_string' => $qrString,
                'expires_at' => $expiresAt->toIso8601String(),
            ]
        );

        return [
            'transaction_ref' => $ref,
            'amount' => $amount,
            'payable_amount' => $amount,
            'unique_code' => 0,
            'currency' => $currency,
            'qr_string' => $qrString,
            'qr_svg' => $qrSvg,
            'expires_at' => $expiresAt,
            'is_mock' => true,
            'merchant_name' => $merchantName,
            'engine' => 'simulator',
            'channel' => 'QRIS',
            'instructions' => [
                'Smart Simulator Mode: Simulated transaction ready for instant testing.',
                'Click "Simulate Instant Pay" to trigger escrow funding and booking confirmation.',
            ],
        ];
    }

    /**
     * Process settlement of a transaction (called by webhook or simulator).
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

                // WhatsApp notification
                $payer = User::find($transaction->payer_user_id);
                if ($payer && ! empty($payer->phone)) {
                    $channelLabel = $transaction->payment_channel ?? 'QRIS';
                    $waMsg = "*[FastCargo Payment Settlement]*\n\n"
                        ."Hello {$payer->name},\n"
                        .'Your payment of *IDR '.number_format((float) $transaction->amount, 0, ',', '.').'* '
                        ."via *PayG ({$channelLabel})* for invoice *#{$invoice->invoice_number}* has been confirmed!\n\n"
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
     * Request OTP for ShopeePay or GoPay in SGpay.
     */
    public function requestSgpayOtp(string $provider, string $phone): array
    {
        $url = $this->getServiceUrl();

        try {
            $resp = Http::timeout(10)->post("{$url}/api/sgpay/otp/request", [
                'provider' => $provider,
                'phone' => $phone,
            ]);

            return (array) $resp->json();
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => 'PayG daemon connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Verify OTP for ShopeePay or GoPay in SGpay.
     */
    public function verifySgpayOtp(string $provider, string $phone, string $otp, string $challenge): array
    {
        $url = $this->getServiceUrl();

        try {
            $resp = Http::timeout(10)->post("{$url}/api/sgpay/otp/verify", [
                'provider' => $provider,
                'phone' => $phone,
                'otp' => $otp,
                'challenge' => $challenge,
            ]);

            return (array) $resp->json();
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => 'PayG daemon connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Disconnect SGpay sessions.
     */
    public function disconnectSgpay(?string $provider = null): array
    {
        $url = $this->getServiceUrl();

        try {
            $resp = Http::timeout(10)->post("{$url}/api/sgpay/disconnect", [
                'provider' => $provider,
            ]);

            return (array) $resp->json();
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => 'PayG daemon connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Configure Static QRIS for SGpay Tag 54 injection.
     */
    public function configureStaticQr(string $staticQr): array
    {
        AdmSystemSetting::set('payg_sgpay_static_qr', $staticQr);
        $url = $this->getServiceUrl();

        try {
            $resp = Http::timeout(10)->post("{$url}/api/sgpay/static-qr", [
                'static_qris' => $staticQr,
            ]);

            return (array) $resp->json();
        } catch (Throwable $e) {
            return [
                'success' => true,
                'message' => 'Static QRIS saved locally in system settings.',
            ];
        }
    }

    /**
     * Configure SociaBuzz creator username in SoBpay.
     */
    public function configureSobpay(string $username): array
    {
        AdmSystemSetting::set('payg_sobpay_username', $username);
        $url = $this->getServiceUrl();

        try {
            $resp = Http::timeout(10)->post("{$url}/api/sobpay/config", [
                'username' => $username,
            ]);

            return (array) $resp->json();
        } catch (Throwable $e) {
            return [
                'success' => true,
                'message' => 'Username saved locally in system settings.',
            ];
        }
    }

    /**
     * Get fee estimates for a country and nominal in SoBpay.
     */
    public function getSobpayFees(string $country = 'Indonesia', float $amount = 100000): array
    {
        $url = $this->getServiceUrl();

        try {
            $resp = Http::timeout(10)->get("{$url}/api/sobpay/fees", [
                'country' => $country,
                'amount' => $amount,
            ]);

            if ($resp->successful()) {
                return (array) $resp->json();
            }
        } catch (Throwable $e) {
            Log::debug('Failed to get fees from PayG, calculating locally', ['error' => $e->getMessage()]);
        }

        // Fallback local fee calculation
        $qrisFee = round($amount * 0.007);

        return [
            'success' => true,
            'country' => $country,
            'amount' => $amount,
            'estimates' => [
                [
                    'method_name' => 'QRIS (Xendit / Midtrans)',
                    'channel' => 'qris',
                    'fee_percent' => 0.7,
                    'estimated_fee' => $qrisFee,
                    'total_amount' => $amount + $qrisFee,
                    'currency' => 'IDR',
                ],
                [
                    'method_name' => 'Virtual Account (BCA, Mandiri, BRI, BNI)',
                    'channel' => 'bank_transfer',
                    'fee_fixed' => 4000,
                    'estimated_fee' => 4000,
                    'total_amount' => $amount + 4000,
                    'currency' => 'IDR',
                ],
            ],
        ];
    }

    /**
     * Update daemon runtime configuration.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function updateDaemonConfig(array $params): array
    {
        $url = $this->getServiceUrl();

        try {
            $resp = Http::timeout(5)->post("{$url}/api/config", $params);
            if ($resp->successful()) {
                return (array) $resp->json();
            }
        } catch (Throwable $e) {
            Log::debug('Failed to update PayG daemon config', ['error' => $e->getMessage()]);
        }

        return ['success' => true, 'message' => 'Config saved locally.'];
    }

    /**
     * Simulate 1-click settlement or terminal failure for sandbox testbed.
     *
     * @return array{success: bool, message: string, transaction?: PaymentTransaction}
     */
    public function simulateSettlement(string $transactionRef, string $scenario = 'SETTLEMENT'): array
    {
        $url = $this->getServiceUrl();
        $scenario = strtoupper(trim($scenario));

        // Non-settlement scenarios (FAILED, EXPIRED, CANCELLED)
        if (in_array($scenario, ['FAILED', 'EXPIRED', 'CANCELLED'], true)) {
            try {
                $resp = Http::timeout(5)->post("{$url}/api/simulator/settle", [
                    'transaction_ref' => $transactionRef,
                    'scenario' => $scenario,
                ]);

                if ($resp->successful() && $resp->json('success')) {
                    // Update transaction locally
                    $transaction = PaymentTransaction::where('transaction_ref', $transactionRef)->first();
                    if ($transaction && $transaction->payment_status !== 'SETTLEMENT') {
                        $transaction->update([
                            'payment_status' => $scenario,
                            'raw_gateway_payload' => array_merge($transaction->raw_gateway_payload ?? [], [
                                'source' => 'PAYG_SIMULATOR_DAEMON',
                                'scenario' => $scenario,
                                'terminal_status_at' => now()->toIso8601String(),
                            ]),
                        ]);
                    }

                    return [
                        'success' => true,
                        'message' => "Simulated {$scenario} scenario dispatched successfully via PayG daemon.",
                        'transaction' => $transaction,
                    ];
                }
            } catch (Throwable $e) {
                Log::debug('PayG daemon simulator call skipped, processing locally', ['error' => $e->getMessage()]);
            }

            // Local fallback for failure simulation
            $transaction = PaymentTransaction::where('transaction_ref', $transactionRef)->first();
            if (! $transaction) {
                return ['success' => false, 'message' => "Transaction {$transactionRef} not found."];
            }

            if ($transaction->payment_status !== 'SETTLEMENT') {
                $transaction->update([
                    'payment_status' => $scenario,
                    'raw_gateway_payload' => array_merge($transaction->raw_gateway_payload ?? [], [
                        'source' => 'PAYG_LOCAL_SIMULATOR',
                        'scenario' => $scenario,
                        'terminal_status_at' => now()->toIso8601String(),
                    ]),
                ]);
            }

            return [
                'success' => true,
                'message' => "Simulated {$scenario} status applied locally.",
                'transaction' => $transaction,
            ];
        }

        // 1. Try triggering daemon settlement
        try {
            $resp = Http::timeout(5)->post("{$url}/api/simulator/settle", [
                'transaction_ref' => $transactionRef,
                'scenario' => $scenario,
            ]);

            if ($resp->successful() && $resp->json('success')) {
                // Daemon dispatches signed webhook; also process locally to guarantee immediate sync
                $this->processSettlement($transactionRef, [
                    'source' => 'PAYG_SIMULATOR_DAEMON',
                    'scenario' => $scenario,
                ]);

                return [
                    'success' => true,
                    'message' => 'Simulated settlement dispatched successfully via PayG daemon.',
                ];
            }
        } catch (Throwable $e) {
            Log::debug('PayG daemon simulator call skipped, processing locally', ['error' => $e->getMessage()]);
        }

        // 2. Direct local settlement
        return $this->processSettlement($transactionRef, [
            'source' => 'PAYG_LOCAL_SIMULATOR',
            'scenario' => $scenario,
        ]);
    }

    /**
     * Create or update pending PaymentTransaction record.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    protected function recordPendingTransaction(
        AdmInvoice $invoice,
        User $customer,
        string $ref,
        float $payableAmount,
        string $engine,
        string $channel,
        array $rawPayload
    ): PaymentTransaction {
        return PaymentTransaction::updateOrCreate(
            ['transaction_ref' => $ref],
            [
                'invoice_id' => $invoice->id,
                'payer_user_id' => $customer->id,
                'payment_gateway' => strtoupper($engine),
                'payment_channel' => strtoupper($channel),
                'amount' => $payableAmount,
                'payment_status' => 'PENDING',
                'raw_gateway_payload' => $rawPayload,
            ]
        );
    }

    /**
     * Render QR Code SVG string.
     */
    public function renderQrSvg(string $qrString): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(280, 2),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);

        return $writer->writeString($qrString);
    }

    /**
     * Standard EMVCo QRIS payload builder with authentic CRC-16/CCITT-FALSE.
     */
    protected function buildEmvcoPayload(string $ref, float $amount, string $merchantName): string
    {
        $amountStr = number_format($amount, 2, '.', '');

        $body = '000201010212'
            .'26580014ID.DANA.WWW0118936009110022334455'
            .'51440014ID.LINKAJA.WWW0118936009110022334455'
            .'52045411'
            .'5303360'
            .'54'.sprintf('%02d', strlen($amountStr)).$amountStr
            .'5802ID'
            .'59'.sprintf('%02d', strlen($merchantName)).$merchantName
            .'6007JAKARTA'
            .'62'.sprintf('%02d', strlen($ref) + 4).'07'.sprintf('%02d', strlen($ref)).$ref
            .'6304';

        return $body.self::calculateCrc16Ccitt($body);
    }

    /**
     * Compute EMVCo standard CRC-16/CCITT-FALSE checksum.
     */
    public static function calculateCrc16Ccitt(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return sprintf('%04X', $crc);
    }
}
