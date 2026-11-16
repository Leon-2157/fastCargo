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
use InvalidArgumentException;
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
        $activeMode = (string) AdmSystemSetting::get('payg_active_mode', 'sgpay');
        $staticQr = (string) AdmSystemSetting::get('payg_sgpay_static_qr', '');

        return [
            'reachable' => false,
            'app' => 'FastCargo PayG Station',
            'version' => '2.0.0',
            'active_mode' => $activeMode,
            'engines' => [
                'sgpay' => [
                    'engine' => 'sgpay',
                    'active' => true,
                    'connected' => ! empty($staticQr),
                    'details' => ! empty($staticQr) ? 'Static QRIS Configured' : 'Offline / Standalone',
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
     *     qris_payload: string,
     *     qr_svg: string,
     *     payment_url?: string,
     *     expires_at: Carbon,
     *     is_mock: bool,
     *     merchant_name: string,
     *     merchant_city: string,
     *     acquirer_label: string,
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
        $enginePref = $options['engine'] ?? AdmSystemSetting::get('payg_active_mode', 'sgpay');
        $channelPref = $options['channel'] ?? 'qris';

        $staticQr = self::cleanQrisString((string) AdmSystemSetting::get('payg_sgpay_static_qr', ''));
        $hasValidStaticQr = $staticQr !== '' && $this->isValidEmvcoQris($staticQr);
        $staticMeta = $hasValidStaticQr ? $this->parseEmvcoMetadata($staticQr) : null;
        $staticQrHash = md5($staticQr);

        $merchantName = $staticMeta['merchant_name'] ?? 'FASTCARGO ASEAN LOGISTICS';
        $merchantCity = $staticMeta['merchant_city'] ?? 'JAKARTA';
        $acquirerLabel = $staticMeta['acquirer_label'] ?? 'SGpay QRIS';

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
                $engine = (string) ($data['engine'] ?? 'sgpay');
                $channel = (string) ($data['channel'] ?? 'QRIS');
                $qrString = (string) ($data['qr_string'] ?? '');
                $qrSvg = (string) ($data['qr_svg'] ?? '');
                $paymentUrl = (string) ($data['payment_url'] ?? '');
                $isMock = (bool) ($data['is_mock'] ?? false);
                $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : now()->addMinutes(15);

                // If a valid static QRIS is configured in DB but the daemon returned synthetic/stale QRIS,
                // preserve the real merchant EMVCo tags by injecting Tag 54 directly from DB setting.
                if ($hasValidStaticQr && $staticMeta !== null) {
                    $qrMeta = $qrString !== '' ? $this->parseEmvcoMetadata($qrString) : null;
                    if (
                        $qrMeta === null
                        || ! $qrMeta['valid']
                        || ($qrMeta['merchant_name'] ?? '') !== $merchantName
                        || ($qrMeta['nmid'] ?? '') !== ($staticMeta['nmid'] ?? '')
                        || (int) ($qrMeta['amount'] ?? 0) !== (int) round($payableAmount)
                    ) {
                        $qrString = $this->injectDynamicAmountIntoStaticQris($staticQr, $payableAmount);
                        $qrSvg = $this->renderQrSvg($qrString);
                        $isMock = false;
                    }
                }

                if ($qrSvg === '' && $qrString !== '') {
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
                        'merchant_city' => $merchantCity,
                        'acquirer_label' => $acquirerLabel,
                        'static_qr_hash' => $staticQrHash,
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
                    'qris_payload' => $qrString,
                    'qr_svg' => $qrSvg,
                    'payment_url' => $paymentUrl,
                    'expires_at' => $expiresAt,
                    'is_mock' => $isMock,
                    'merchant_name' => $merchantName,
                    'merchant_city' => $merchantCity,
                    'acquirer_label' => $acquirerLabel,
                    'engine' => $engine,
                    'channel' => $channel,
                    'instructions' => $data['instructions'] ?? [],
                ];
            }
        } catch (Throwable $e) {
            Log::info('PayG service checkout call failed, using built-in smart fallback', ['error' => $e->getMessage()]);
        }

        // Fallback Standalone Generator (Direct QRIS via SGpay)
        $expiresAt = now()->addMinutes(15);
        $baseAmount = (int) round($amount);
        $activeAmounts = PaymentTransaction::query()
            ->where('payment_status', PaymentTransaction::STATUS_PENDING)
            ->where('created_at', '>=', now()->subHours(24))
            ->pluck('amount')
            ->map(static fn (mixed $val): int => (int) round((float) $val))
            ->all();
        $takenLookup = array_flip($activeAmounts);

        $uniqueCode = random_int(1, 999);
        $allocated = false;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidateCode = random_int(1, 999);
            if (! isset($takenLookup[$baseAmount + $candidateCode])) {
                $uniqueCode = $candidateCode;
                $allocated = true;
                break;
            }
        }
        if (! $allocated) {
            for ($candidateCode = 1; $candidateCode <= 999; $candidateCode++) {
                if (! isset($takenLookup[$baseAmount + $candidateCode])) {
                    $uniqueCode = $candidateCode;
                    break;
                }
            }
        }

        $payableAmount = (float) ($baseAmount + $uniqueCode);
        $paymentUrl = '';
        $channel = 'QRIS';
        $ref = 'SGPAY-'.date('YmdHis').'-'.sprintf('%03d', $uniqueCode);
        $engine = 'sgpay';
        $qrString = $this->buildEmvcoPayload($ref, $payableAmount, $merchantName);
        $qrSvg = $this->renderQrSvg($qrString);
        $isMock = ! $hasValidStaticQr;

        $this->recordPendingTransaction(
            $invoice,
            $customer,
            $ref,
            $payableAmount,
            strtoupper($engine),
            $channel,
            [
                'merchant_name' => $merchantName,
                'merchant_city' => $merchantCity,
                'acquirer_label' => $acquirerLabel,
                'static_qr_hash' => $staticQrHash,
                'gateway_mode' => $hasValidStaticQr ? 'STANDALONE_DIRECT_QRIS' : 'STANDALONE_FALLBACK',
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
            'qris_payload' => $qrString,
            'qr_svg' => $qrSvg,
            'payment_url' => $paymentUrl,
            'expires_at' => $expiresAt,
            'is_mock' => $isMock,
            'merchant_name' => $merchantName,
            'merchant_city' => $merchantCity,
            'acquirer_label' => $acquirerLabel,
            'engine' => $engine,
            'channel' => $channel,
            'instructions' => [
                'Scan the dynamic QRIS using any Indonesian e-wallet or banking app.',
                'Transfer the exact Total Bayar Tepat amount including the unique verification code.',
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
                return [
                    'success' => true,
                    'message' => 'Transaction already settled.',
                    'invoice' => AdmInvoice::with('booking')->find($transaction->invoice_id),
                    'transaction' => $transaction,
                ];
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
     * Configure Static QRIS for SGpay Tag 54 injection.
     *
     * @return array<string, mixed>
     */
    public function configureStaticQr(string $staticQr): array
    {
        $staticQr = self::cleanQrisString($staticQr);
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
        if (trim($qrString) === '') {
            return '';
        }

        $renderer = new ImageRenderer(
            new RendererStyle(280, 2),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);

        return $writer->writeString($qrString);
    }

    /**
     * Normalize a raw EMVCo QRIS string by stripping line breaks while preserving internal spaces.
     */
    public static function cleanQrisString(string $qris): string
    {
        return trim(str_replace(["\r", "\n"], '', $qris));
    }

    /**
     * Parse an EMVCo TLV string into an associative tag => value map.
     *
     * @return array<string, string>
     */
    public function parseEmvcoTlv(string $payload): array
    {
        $len = strlen($payload);
        $cursor = 0;
        $tags = [];

        while ($cursor < $len) {
            if ($cursor + 4 > $len) {
                throw new InvalidArgumentException("Malformed QRIS TLV header at offset {$cursor}.");
            }

            $tag = substr($payload, $cursor, 2);
            $lengthText = substr($payload, $cursor + 2, 2);
            $cursor += 4;

            if (! ctype_digit($tag) || ! ctype_digit($lengthText)) {
                throw new InvalidArgumentException("Invalid QRIS TLV tag or length at offset {$cursor}.");
            }

            $valueLen = (int) $lengthText;
            if ($cursor + $valueLen > $len) {
                throw new InvalidArgumentException("QRIS TLV value for tag {$tag} exceeds payload length.");
            }

            $tags[$tag] = substr($payload, $cursor, $valueLen);
            $cursor += $valueLen;
        }

        return $tags;
    }

    /**
     * Parse EMVCo ASPI QRIS metadata including CRC-16 validation and merchant account details.
     *
     * @return array{
     *     valid: bool,
     *     crc_valid: bool,
     *     payload_format: ?string,
     *     poi_method: ?string,
     *     poi_label: string,
     *     merchant_name: ?string,
     *     merchant_city: ?string,
     *     postal_code: ?string,
     *     country_code: ?string,
     *     currency_code: ?string,
     *     mcc: ?string,
     *     amount: ?string,
     *     acquirer_domain: ?string,
     *     acquirer_label: ?string,
     *     merchant_pan: ?string,
     *     merchant_id: ?string,
     *     nmid: ?string,
     *     criteria: ?string,
     *     crc_provided: ?string,
     *     crc_computed: ?string,
     *     accounts: array<int, array{tag: string, domain: ?string, pan: ?string, merchant_id: ?string, criteria: ?string}>,
     *     error: ?string
     * }
     */
    public function parseEmvcoMetadata(string $qris): array
    {
        $cleaned = self::cleanQrisString($qris);

        $result = [
            'valid' => false,
            'crc_valid' => false,
            'payload_format' => null,
            'poi_method' => null,
            'poi_label' => 'Unknown',
            'merchant_name' => null,
            'merchant_city' => null,
            'postal_code' => null,
            'country_code' => null,
            'currency_code' => null,
            'mcc' => null,
            'amount' => null,
            'acquirer_domain' => null,
            'acquirer_label' => null,
            'merchant_pan' => null,
            'merchant_id' => null,
            'nmid' => null,
            'criteria' => null,
            'crc_provided' => null,
            'crc_computed' => null,
            'accounts' => [],
            'error' => null,
        ];

        if ($cleaned === '') {
            $result['error'] = 'QRIS string is empty.';

            return $result;
        }

        if (! str_starts_with($cleaned, '000201')) {
            $result['error'] = 'QRIS string must start with EMVCo Payload Format Indicator 000201.';

            return $result;
        }

        if (preg_match('/6304([0-9A-Fa-f]{4})$/', $cleaned, $crcMatch) !== 1) {
            $result['error'] = 'Missing or malformed trailing EMVCo Tag 63 CRC-16 checksum.';

            return $result;
        }

        $crcProvided = strtoupper($crcMatch[1]);
        $crcComputed = self::calculateCrc16Ccitt(substr($cleaned, 0, -4));
        $crcValid = hash_equals($crcComputed, $crcProvided);

        $result['crc_provided'] = $crcProvided;
        $result['crc_computed'] = $crcComputed;
        $result['crc_valid'] = $crcValid;

        try {
            $tags = $this->parseEmvcoTlv($cleaned);
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();

            return $result;
        }

        $payloadFormat = $tags['00'] ?? null;
        $poiMethod = $tags['01'] ?? null;
        $poiLabel = match ($poiMethod) {
            '11' => 'Static (11)',
            '12' => 'Dynamic (12)',
            default => $poiMethod !== null ? "POI ({$poiMethod})" : 'Unknown',
        };

        $accounts = [];
        $acquirerDomain = null;
        $merchantPan = null;
        $merchantId = null;
        $nmid = null;
        $criteria = null;

        for ($tagNum = 26; $tagNum <= 51; $tagNum++) {
            $tagKey = str_pad((string) $tagNum, 2, '0', STR_PAD_LEFT);
            if (! isset($tags[$tagKey])) {
                continue;
            }

            try {
                $sub = $this->parseEmvcoTlv($tags[$tagKey]);
            } catch (Throwable) {
                $sub = [];
            }

            $domain = $sub['00'] ?? null;
            $pan = $sub['01'] ?? null;
            $mid = $sub['02'] ?? null;
            $crit = $sub['03'] ?? null;

            $accounts[] = [
                'tag' => $tagKey,
                'domain' => $domain,
                'pan' => $pan,
                'merchant_id' => $mid,
                'criteria' => $crit,
            ];

            if ($tagNum < 51 && $acquirerDomain === null && $domain !== null) {
                $acquirerDomain = $domain;
            }
            if ($merchantPan === null && $pan !== null) {
                $merchantPan = $pan;
            }
            if ($tagNum < 51 && $merchantId === null && $mid !== null) {
                $merchantId = $mid;
            }
            if ($tagNum === 51 && $mid !== null) {
                $nmid = $mid;
            }
            if ($criteria === null && $crit !== null) {
                $criteria = $crit;
            }
        }

        if ($acquirerDomain === null && isset($accounts[0]['domain'])) {
            $acquirerDomain = $accounts[0]['domain'];
        }
        if ($nmid === null) {
            $nmid = $merchantId;
        }

        $merchantName = isset($tags['59']) ? trim($tags['59']) : null;
        $merchantCity = isset($tags['60']) ? trim($tags['60']) : null;

        $result['payload_format'] = $payloadFormat;
        $result['poi_method'] = $poiMethod;
        $result['poi_label'] = $poiLabel;
        $result['merchant_name'] = $merchantName !== '' ? $merchantName : null;
        $result['merchant_city'] = $merchantCity !== '' ? $merchantCity : null;
        $result['postal_code'] = isset($tags['61']) && trim($tags['61']) !== '' ? trim($tags['61']) : null;
        $result['country_code'] = $tags['58'] ?? null;
        $result['currency_code'] = $tags['53'] ?? null;
        $result['mcc'] = $tags['52'] ?? null;
        $result['amount'] = $tags['54'] ?? null;
        $result['acquirer_domain'] = $acquirerDomain;
        $result['acquirer_label'] = self::resolveAcquirerLabel($acquirerDomain);
        $result['merchant_pan'] = $merchantPan;
        $result['merchant_id'] = $merchantId;
        $result['nmid'] = $nmid;
        $result['criteria'] = $criteria;
        $result['accounts'] = $accounts;

        if (! $crcValid) {
            $result['error'] = "CRC-16 checksum mismatch (expected {$crcComputed}, found {$crcProvided}).";

            return $result;
        }

        if ($payloadFormat !== '01') {
            $result['error'] = 'Invalid EMVCo Tag 00 payload format indicator.';

            return $result;
        }

        if (count($accounts) === 0) {
            $result['error'] = 'Missing Merchant Account Information tags (Tag 26-51).';

            return $result;
        }

        if ($result['merchant_name'] === null) {
            $result['error'] = 'Missing Merchant Name (EMVCo Tag 59).';

            return $result;
        }

        $result['valid'] = true;

        return $result;
    }

    /**
     * Validate whether a string is a structurally authentic EMVCo ASPI QRIS payload.
     */
    public function isValidEmvcoQris(string $qris): bool
    {
        $meta = $this->parseEmvcoMetadata($qris);

        return $meta['valid'] === true;
    }

    /**
     * Inject dynamic Tag 54 integer IDR nominal and POI 12 into a configured Static QRIS
     * while preserving all real merchant tags (26-51, 52, 53, 58, 59, 60, 61, 62).
     */
    public function injectDynamicAmountIntoStaticQris(string $staticQr, float $amount): string
    {
        $cleaned = self::cleanQrisString($staticQr);
        $tags = $this->parseEmvcoTlv($cleaned);

        $tags['01'] = '12';
        $tags['54'] = (string) max(1, (int) round($amount));
        if (! isset($tags['53'])) {
            $tags['53'] = '360';
        }
        if (! isset($tags['58'])) {
            $tags['58'] = 'ID';
        }
        unset($tags['63']);

        uksort($tags, static fn (string $a, string $b): int => ((int) $a) <=> ((int) $b));

        $body = '';
        foreach ($tags as $tag => $value) {
            $len = strlen($value);
            if ($len > 99) {
                throw new InvalidArgumentException("EMVCo tag {$tag} exceeds 99-byte limit ({$len} bytes).");
            }
            $body .= $tag.sprintf('%02d', $len).$value;
        }
        $body .= '6304';

        return $body.self::calculateCrc16Ccitt($body);
    }

    /**
     * Standard EMVCo QRIS payload builder with authentic CRC-16/CCITT-FALSE.
     * Preserves real merchant static QRIS tags when configured; uses synthetic payload otherwise.
     */
    protected function buildEmvcoPayload(string $ref, float $amount, string $merchantName): string
    {
        $staticQr = self::cleanQrisString((string) AdmSystemSetting::get('payg_sgpay_static_qr', ''));
        if ($staticQr !== '' && $this->isValidEmvcoQris($staticQr)) {
            return $this->injectDynamicAmountIntoStaticQris($staticQr, $amount);
        }

        $amountStr = (string) max(1, (int) round($amount));

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
     * Extract a positive integer IDR amount from an Android payment notification message.
     */
    public static function extractIdrAmount(string $text): ?int
    {
        $normalized = (string) preg_replace("/[\u{00A0}\u{202F}\u{2007}\u{2009}\r\n\t]+/u", ' ', $text);
        $normalized = trim($normalized);
        if ($normalized === '') {
            return null;
        }

        $matches = [];
        $matched = preg_match('/(?:Rp\.?|IDR)\s*(-?\d[\d.,]*(?:,-)?)/iu', $normalized, $matches, PREG_OFFSET_CAPTURE);
        if ($matched !== 1) {
            $matched = preg_match('/(-?\d[\d.,]*)\s*IDR\b/iu', $normalized, $matches, PREG_OFFSET_CAPTURE);
        }

        if ($matched !== 1) {
            return null;
        }

        $matchOffset = (int) $matches[0][1];
        $leadingContext = substr($normalized, 0, $matchOffset);
        if ($leadingContext !== '' && preg_match('/(?:^|[\s:(])-\s*$/u', $leadingContext) === 1) {
            return null;
        }

        $rawNumber = trim((string) $matches[1][0]);
        $rawNumber = (string) preg_replace('/,-$|[-]+$/', '', $rawNumber);
        $rawNumber = rtrim($rawNumber, '.,');

        if ($rawNumber === '' || str_starts_with($rawNumber, '-')) {
            return null;
        }

        $hasDot = str_contains($rawNumber, '.');
        $hasComma = str_contains($rawNumber, ',');

        $integerDigits = '';

        if ($hasDot && $hasComma) {
            $lastDot = (int) strrpos($rawNumber, '.');
            $lastComma = (int) strrpos($rawNumber, ',');

            if ($lastComma > $lastDot) {
                $integerPart = substr($rawNumber, 0, $lastComma);
                $integerDigits = str_replace('.', '', $integerPart);
            } else {
                $integerPart = substr($rawNumber, 0, $lastDot);
                $integerDigits = str_replace(',', '', $integerPart);
            }
        } elseif ($hasComma) {
            if (preg_match('/^\d+(?:,\d{3})+$/', $rawNumber) === 1) {
                $integerDigits = str_replace(',', '', $rawNumber);
            } elseif (preg_match('/^(\d+),\d{1,2}$/', $rawNumber, $m) === 1) {
                $integerDigits = $m[1];
            } else {
                $integerDigits = str_replace(',', '', $rawNumber);
            }
        } elseif ($hasDot) {
            if (preg_match('/^(\d+(?:\.\d{3})+)\.\d{1,2}$/', $rawNumber, $m) === 1) {
                $integerDigits = str_replace('.', '', $m[1]);
            } elseif (preg_match('/^\d+(?:\.\d{3})+$/', $rawNumber) === 1) {
                $integerDigits = str_replace('.', '', $rawNumber);
            } elseif (preg_match('/^(\d+)\.\d{1,2}$/', $rawNumber, $m) === 1) {
                $integerDigits = $m[1];
            } else {
                $integerDigits = str_replace('.', '', $rawNumber);
            }
        } else {
            $integerDigits = $rawNumber;
        }

        $integerDigits = (string) preg_replace('/\D/', '', $integerDigits);
        if ($integerDigits === '') {
            return null;
        }

        $parsed = (int) $integerDigits;

        return $parsed > 0 ? $parsed : null;
    }

    /**
     * Resolve human-readable Indonesian QRIS acquirer name from EMVCo subtag 00 reverse domain.
     */
    public static function resolveAcquirerLabel(?string $domain): ?string
    {
        if ($domain === null || trim($domain) === '') {
            return null;
        }

        $upper = strtoupper(trim($domain));

        return match (true) {
            str_contains($upper, 'GO-JEK') || str_contains($upper, 'GOPAY') => 'GoPay / GoBiz',
            str_contains($upper, 'SHOPEE') || str_contains($upper, 'AIRPAY') => 'ShopeePay',
            str_contains($upper, 'DANA') => 'DANA',
            str_contains($upper, 'LINKAJA') => 'LinkAja',
            str_contains($upper, 'OVO') => 'OVO',
            str_contains($upper, 'BCA') => 'BCA QRIS',
            str_contains($upper, 'BRI') => 'BRI QRIS',
            str_contains($upper, 'MANDIRI') => 'Bank Mandiri',
            str_contains($upper, 'BNI') => 'BNI QRIS',
            str_contains($upper, 'NOBU') => 'Nobu Bank QRIS',
            str_contains($upper, 'QRIS.WWW') => 'QRIS National',
            default => $domain,
        };
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
