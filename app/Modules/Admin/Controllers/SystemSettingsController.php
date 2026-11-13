<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\AI\AiManager;
use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Services\MailSettingsService;
use App\Modules\Shared\Services\PayGPaymentGatewayService;
use App\Modules\Shared\WhatsApp\WaGatewayClient;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SystemSettingsController extends Controller
{
    private const SECRET_KEYS = [
        'custom_ai_api_key',
        'ninerouter_api_key',
        'gemini_api_key',
        'mail_password',
        'dana_client_secret',
        'payg_secret',
    ];

    public function __construct(
        private readonly WaGatewayClient $waClient,
        private readonly PayGPaymentGatewayService $paygService,
    ) {}

    public function index(): View
    {
        $settings = AdmSystemSetting::all()->keyBy('key');

        $customAiKey = AiManager::settingValue('custom_ai_api_key', AiManager::settingValue('ninerouter_api_key', (string) config('ai.providers.custom.api_key', '')));
        $geminiKey = AiManager::settingValue('gemini_api_key', (string) config('ai.providers.gemini.api_key', ''));

        $aiConfig = [
            'active_provider' => AiManager::activeProviderKey(),
            'active_label' => AiManager::activeProviderLabel(),
            'custom_ai_provider_name' => AiManager::settingValue('custom_ai_provider_name', (string) config('ai.providers.custom.name', 'Custom AI')),
            'custom_ai_base_url' => AiManager::settingValue('custom_ai_base_url', AiManager::settingValue('ninerouter_base_url', (string) config('ai.providers.custom.base_url', 'http://localhost:20128/v1'))),
            'custom_ai_api_key' => $customAiKey,
            'custom_ai_model' => AiManager::settingValue('custom_ai_model', AiManager::settingValue('ninerouter_model', (string) config('ai.providers.custom.model', 'ag/gemini-3.8-flash-high'))),
            'custom_ai_max_completion_tokens' => AiManager::settingValue('custom_ai_max_completion_tokens', '4096'),
            'custom_ai_max_output_tokens' => AiManager::settingValue('custom_ai_max_output_tokens', '2048'),
            'custom_ai_max_tokens' => AiManager::settingValue('custom_ai_max_tokens', '8192'),
            'custom_ai_key_configured' => $customAiKey !== '',
            'gemini_api_key' => $geminiKey,
            'gemini_model' => AiManager::settingValue('gemini_model', (string) config('ai.providers.gemini.model', 'gemini-2.0-flash')),
            'gemini_key_configured' => $geminiKey !== '',
        ];

        $waSnapshot = $this->resolveGatewaySnapshot();
        $mailConfig = MailSettingsService::getAdminSummary();

        $waAiProvider = (string) AdmSystemSetting::get('wa_ai_provider', 'default');
        $mailAiProvider = (string) AdmSystemSetting::get('mail_ai_provider', 'default');
        $aiConfig['wa_ai_provider'] = $waAiProvider;
        $aiConfig['mail_ai_provider'] = $mailAiProvider;

        $danaEnv = AdmSystemSetting::get('dana_env', config('dana.env', 'sandbox'));
        $danaMerchantId = AdmSystemSetting::get('dana_merchant_id', config('dana.merchant_id', ''));
        $danaClientId = AdmSystemSetting::get('dana_client_id', config('dana.client_id', ''));
        $danaClientSecret = AdmSystemSetting::get('dana_client_secret', config('dana.client_secret', ''));

        $isDanaConfigured = ! empty($danaClientId) && ! empty($danaClientSecret);

        $renderer = new ImageRenderer(
            new RendererStyle(135, 1),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $samplePayload = '00020101021226580014ID.DANA.WWW011893600911002233445551440014ID.LINKAJA.WWW01189360091100223344555204541153033605802ID5925FASTCARGO ASEAN LOGISTICS6007JAKARTA6304A1B2';
        $sampleQrSvg = $writer->writeString($samplePayload);

        $recentTransactions = PaymentTransaction::with(['invoice.booking', 'payer'])
            ->latest('created_at')
            ->take(5)
            ->get();

        $simulatorTransactions = PaymentTransaction::with(['invoice.booking.escrowLedger'])
            ->whereIn('payment_status', ['PENDING', 'EXPIRED', 'FAILED', 'CANCELLED', 'SETTLEMENT'])
            ->latest('created_at')
            ->take(10)
            ->get();

        $simulatorRecentLogs = DB::table('adm_immutable_audit_trails')
            ->whereIn('action', ['WEBHOOK_SIMULATOR_DISPATCHED', 'SIMULATOR_SANDBOX_TX_CREATED'])
            ->latest('created_at')
            ->take(10)
            ->get();

        $danaConfig = [
            'dana_env' => (string) $danaEnv,
            'dana_merchant_id' => (string) $danaMerchantId,
            'dana_client_id' => (string) $danaClientId,
            'dana_client_secret' => (string) $danaClientSecret,
            'is_configured' => $isDanaConfigured,
            'webhook_url' => url('/api/webhooks/dana'),
            'sample_qr_svg' => $sampleQrSvg,
            'recent_transactions' => $recentTransactions,
        ];

        $paygStatus = $this->paygService->getGatewayStatus();
        $paygActiveMode = AdmSystemSetting::get('payg_active_mode', 'auto');
        $paygSobUsername = AdmSystemSetting::get('payg_sobpay_username', '');
        $paygStaticQr = AdmSystemSetting::get('payg_sgpay_static_qr', '');
        $paygSecret = AdmSystemSetting::get('payg_secret', config('payg.secret'));

        $paygConfig = [
            'status' => $paygStatus,
            'active_mode' => (string) $paygActiveMode,
            'sobpay_username' => (string) $paygSobUsername,
            'sgpay_static_qr' => (string) $paygStaticQr,
            'secret' => (string) $paygSecret,
            'webhook_url' => url('/api/internal/payg/webhook'),
            'sample_qr_svg' => $sampleQrSvg,
            'recent_transactions' => $recentTransactions,
        ];

        return view('admin.settings.index', [
            'role' => 'admin',
            'settings' => $settings,
            'aiConfig' => $aiConfig,
            'mailConfig' => $mailConfig,
            'waConfig' => $waSnapshot,
            'danaConfig' => $danaConfig,
            'paygConfig' => $paygConfig,
            'simulatorTransactions' => $simulatorTransactions,
            'simulatorRecentLogs' => $simulatorRecentLogs,
            'transactions' => $simulatorTransactions,
            'recentLogs' => $simulatorRecentLogs,
            'waAiProvider' => $waAiProvider,
            'mailAiProvider' => $mailAiProvider,
            'wa_ai_provider' => $waAiProvider,
            'mail_ai_provider' => $mailAiProvider,
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'wa_ai_provider' => ['nullable', 'string', 'in:default,gemini,custom,disabled'],
            'mail_ai_provider' => ['nullable', 'string', 'in:default,gemini,custom,disabled'],
            'custom_ai_provider_name' => ['nullable', 'string', 'max:120'],
            'custom_ai_base_url' => ['nullable', 'url', 'max:255'],
            'custom_ai_api_key' => ['nullable', 'string', 'max:255'],
            'custom_ai_model' => ['nullable', 'string', 'max:120'],
            'custom_ai_max_completion_tokens' => ['nullable', 'integer', 'min:1'],
            'custom_ai_max_output_tokens' => ['nullable', 'integer', 'min:1'],
            'custom_ai_max_tokens' => ['nullable', 'integer', 'min:1'],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'gemini_model' => ['nullable', 'string', 'max:120'],
            'ninerouter_base_url' => ['nullable', 'url', 'max:255'],
            'ninerouter_api_key' => ['nullable', 'string', 'max:255'],
            'ninerouter_model' => ['nullable', 'string', 'max:120'],
            'operator_vpn_whitelist' => ['nullable', 'string', 'max:2000'],
            'platform_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'escrow_milestone_1_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'escrow_milestone_2_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'escrow_milestone_3_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'mail_mailer' => ['nullable', 'string', 'in:smtp,log,resend'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'dana_env' => ['nullable', 'string', 'in:sandbox,production'],
            'dana_merchant_id' => ['nullable', 'string', 'max:100'],
            'dana_client_id' => ['nullable', 'string', 'max:150'],
            'dana_client_secret' => ['nullable', 'string', 'max:255'],
            'payg_active_mode' => ['nullable', 'string', 'in:auto,sgpay,sobpay,simulator'],
            'payg_sgpay_static_qr' => ['nullable', 'string'],
            'payg_sobpay_username' => ['nullable', 'string', 'max:100'],
            'payg_secret' => ['nullable', 'string', 'max:255'],
        ]);

        // Auto-sync custom_ai values with legacy ninerouter keys for backward compatibility
        if (! empty($validated['custom_ai_base_url'])) {
            $validated['ninerouter_base_url'] = $validated['custom_ai_base_url'];
        }
        if (! empty($validated['custom_ai_api_key'])) {
            $validated['ninerouter_api_key'] = $validated['custom_ai_api_key'];
        }
        if (! empty($validated['custom_ai_model'])) {
            $validated['ninerouter_model'] = $validated['custom_ai_model'];
        }

        if (isset($validated['operator_vpn_whitelist'])) {
            $decoded = json_decode($validated['operator_vpn_whitelist'], true);
            if (! is_array($decoded)) {
                return back()->withErrors(['operator_vpn_whitelist' => 'VPN whitelist must be a valid JSON CIDR array.'])->withInput();
            }
            $validated['operator_vpn_whitelist'] = json_encode($decoded);
        }

        $auditChanges = [];

        foreach ($validated as $key => $value) {
            if ($value === null) {
                continue;
            }

            $stringValue = trim((string) $value);
            if (in_array($key, self::SECRET_KEYS, true) && ($stringValue === '' || str_starts_with($stringValue, '••••'))) {
                continue;
            }

            AdmSystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $stringValue,
                    'type' => str_contains($key, 'percent') ? 'integer' : ($key === 'operator_vpn_whitelist' ? 'json' : 'string'),
                ]
            );

            $auditChanges[$key] = in_array($key, self::SECRET_KEYS, true) ? '[REDACTED]' : $stringValue;
        }

        Cache::forget('operator_vpn_whitelist');
        Cache::forget('adm_mail_settings');
        Cache::forget('adm_dana_settings');
        Cache::forget('adm_payg_settings');
        AiManager::reset();

        $daemonConfig = [];
        if (isset($validated['payg_active_mode'])) {
            $daemonConfig['active_mode'] = (string) $validated['payg_active_mode'];
        }
        if (isset($validated['payg_sgpay_static_qr'])) {
            $this->paygService->configureStaticQr((string) $validated['payg_sgpay_static_qr']);
            $daemonConfig['sgpay_static_qr'] = (string) $validated['payg_sgpay_static_qr'];
        }
        if (isset($validated['payg_sobpay_username'])) {
            $this->paygService->configureSobpay((string) $validated['payg_sobpay_username']);
            $daemonConfig['sobpay_username'] = (string) $validated['payg_sobpay_username'];
        }
        if (isset($validated['payg_secret']) && ! empty($validated['payg_secret']) && ! str_starts_with((string) $validated['payg_secret'], '••••')) {
            $daemonConfig['shared_secret'] = (string) $validated['payg_secret'];
        }
        if (! empty($daemonConfig)) {
            $this->paygService->updateDaemonConfig($daemonConfig);
        }

        $this->recordAudit($request, 'SYSTEM_SETTINGS_UPDATED', $auditChanges);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Konfigurasi pengaturan AI berhasil disimpan.',
            ]);
        }

        return back()->with('success', 'System settings have been updated successfully.');
    }

    /**
     * Dispatch an immediate live test email to verify SMTP/Resend configuration.
     */
    public function sendTestEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            MailSettingsService::apply();

            $recipient = $validated['test_email'];
            $from = config('mail.from.address', 'noreply@leonproject.my.id');
            $fromName = config('mail.from.name', 'FastCargo');

            $activeMailer = config('mail.default', 'smtp');

            Mail::raw("Hello from FastCargo Logistics!\n\nThis is a verified test email sent via ".strtoupper($activeMailer)." engine.\nTimestamp: ".now()->toIso8601String()."\nSender: {$from} ({$fromName})\n\nIf you received this message, your FastCargo email delivery and 2FA reset dispatch are properly configured and operational.", function ($message) use ($recipient, $from, $fromName) {
                $message->to($recipient)
                    ->from($from, $fromName)
                    ->subject('FastCargo — Email Delivery Test ('.now()->format('H:i:s').')');
            });

            $this->recordAudit($request, 'TEST_EMAIL_DISPATCHED', [
                'recipient' => $recipient,
                'status' => 'SUCCESS',
                'mailer' => $activeMailer,
            ]);

            AdmSystemSetting::updateOrCreate(
                ['key' => 'mail_last_test'],
                [
                    'value' => json_encode([
                        'recipient' => $recipient,
                        'mailer' => $activeMailer,
                        'status' => 'SUCCESS',
                        'timestamp' => now()->toIso8601String(),
                        'formatted_time' => now()->format('d M Y, H:i').' WIB',
                    ]),
                    'type' => 'json',
                    'description' => 'Last email delivery test audit result',
                ]
            );

            if ($activeMailer === 'log') {
                return back()->with('success', 'Test email berhasil dicatat ke file log server (storage/logs/laravel.log). Mode Local Log aktif, email tidak dikirim ke internet.');
            }

            return back()->with('success', "Test email was sent successfully to {$recipient}. Please check your inbox (and spam/promotions tab).");
        } catch (Throwable $e) {
            Log::error('Send test email failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            AdmSystemSetting::updateOrCreate(
                ['key' => 'mail_last_test'],
                [
                    'value' => json_encode([
                        'recipient' => $validated['test_email'] ?? 'unknown',
                        'mailer' => $activeMailer ?? 'unknown',
                        'status' => 'FAILED',
                        'error' => $e->getMessage(),
                        'timestamp' => now()->toIso8601String(),
                        'formatted_time' => now()->format('d M Y, H:i').' WIB',
                    ]),
                    'type' => 'json',
                    'description' => 'Last email delivery test audit result',
                ]
            );

            return back()->with('error', 'Failed to dispatch test email: '.$e->getMessage());
        }
    }

    public function switchAiProvider(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_provider' => ['required', 'in:custom,9router,gemini'],
        ]);

        $provider = $validated['ai_provider'] === '9router' ? 'custom' : $validated['ai_provider'];
        $previous = AdmSystemSetting::where('key', 'ai_provider')->value('value');

        AdmSystemSetting::updateOrCreate(
            ['key' => 'ai_provider'],
            [
                'value' => $provider,
                'type' => 'string',
                'description' => 'Active AI provider: custom or gemini',
            ]
        );

        AiManager::reset();

        $this->recordAudit($request, 'AI_PROVIDER_SWITCHED', [
            'from' => $previous,
            'to' => $provider,
        ]);

        return back()->with('success', "Active AI intelligence engine switched to {$provider}.");
    }

    /**
     * Real-time AJAX verification of AI engine credentials and live endpoint ping.
     */
    public function testAiConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:gemini,custom,9router'],
            'gemini_api_key' => ['nullable', 'string'],
            'gemini_model' => ['nullable', 'string'],
            'custom_ai_provider_name' => ['nullable', 'string', 'max:120'],
            'custom_ai_base_url' => ['nullable', 'string'],
            'custom_ai_api_key' => ['nullable', 'string'],
            'custom_ai_model' => ['nullable', 'string'],
        ]);

        $provider = $validated['provider'] === 'gemini' ? 'gemini' : 'custom';
        $startTime = microtime(true);

        try {
            if ($provider === 'gemini') {
                $apiKey = trim((string) ($validated['gemini_api_key'] ?? ''));
                if ($apiKey === '') {
                    $apiKey = AiManager::settingValue('gemini_api_key', (string) config('ai.providers.gemini.api_key', ''));
                }
                if ($apiKey === '') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gemini API Key is required to perform connection test.',
                    ]);
                }

                $model = trim((string) ($validated['gemini_model'] ?? 'gemini-2.0-flash'));
                if ($model === '') {
                    $model = 'gemini-2.0-flash';
                }

                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $response = Http::timeout(8)->post($url, [
                    'contents' => [
                        [
                            'parts' => [['text' => 'FastCargo health ping. Reply with only: OK']],
                        ],
                    ],
                ]);

                if ($response->failed()) {
                    $errorMsg = $response->json('error.message') ?? "HTTP {$response->status()} {$response->reason()}";

                    return response()->json([
                        'success' => false,
                        'message' => "Gemini API rejected request: {$errorMsg}",
                    ]);
                }

                $latency = round((microtime(true) - $startTime) * 1000);

                return response()->json([
                    'success' => true,
                    'message' => "Verified successfully with Google Gemini ({$model}). Latency: {$latency}ms.",
                    'latency_ms' => $latency,
                ]);
            } else {
                $baseUrl = rtrim(trim((string) ($validated['custom_ai_base_url'] ?? '')), '/');
                if ($baseUrl === '') {
                    $baseUrl = rtrim(AiManager::settingValue('custom_ai_base_url', AiManager::settingValue('ninerouter_base_url', (string) config('ai.providers.custom.base_url', 'http://localhost:20128/v1'))), '/');
                }

                $apiKey = trim((string) ($validated['custom_ai_api_key'] ?? ''));
                if ($apiKey === '') {
                    $apiKey = AiManager::settingValue('custom_ai_api_key', AiManager::settingValue('ninerouter_api_key', ''));
                }

                $model = trim((string) ($validated['custom_ai_model'] ?? ''));
                if ($model === '') {
                    $model = AiManager::settingValue('custom_ai_model', AiManager::settingValue('ninerouter_model', (string) config('ai.providers.custom.model', 'ag/gemini-3.8-flash-high')));
                }

                $providerName = trim((string) ($validated['custom_ai_provider_name'] ?? ''));
                if ($providerName === '') {
                    $providerName = AiManager::settingValue('custom_ai_provider_name', 'Custom AI');
                }

                if ($baseUrl === '') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Base URL is required to test Custom AI.',
                    ]);
                }

                $resolvedUrl = AiManager::resolveNetworkUrl($baseUrl);

                $client = Http::timeout(8);
                if ($apiKey !== '') {
                    $client = $client->withToken($apiKey);
                }

                $response = $client->post("{$resolvedUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'FastCargo health ping. Reply with only: OK'],
                    ],
                    'stream' => false,
                ]);

                if ($response->failed()) {
                    $errorMsg = $response->json('error.message') ?? "HTTP {$response->status()} {$response->reason()}";

                    return response()->json([
                        'success' => false,
                        'message' => "Custom AI request rejected: {$errorMsg}",
                    ]);
                }

                $latency = round((microtime(true) - $startTime) * 1000);

                return response()->json([
                    'success' => true,
                    'message' => "Verified successfully with {$providerName} ({$model}). Latency: {$latency}ms.",
                    'latency_ms' => $latency,
                ]);
            }
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{gatewayStatus: array<string, mixed>, isConnected: bool, qrSvg: ?string, gatewayBaseUrl: string, pairedAt: string}
     */
    private function resolveGatewaySnapshot(): array
    {
        $gatewayStatus = $this->waClient->status();
        $wagSetting = AdmSystemSetting::where('key', 'wag_connected')->first();

        if ($gatewayStatus['reachable']) {
            $wasConnected = (bool) ($wagSetting?->value ?? false);
            $nowConnected = (bool) ($gatewayStatus['connected'] ?? false);

            if ($nowConnected && ! $wasConnected) {
                AdmSystemSetting::updateOrCreate(
                    ['key' => 'wag_paired_at'],
                    [
                        'value' => now()->toIso8601String(),
                        'type' => 'string',
                        'description' => 'WhatsApp pairing timestamp',
                    ]
                );
            } elseif (! $nowConnected) {
                AdmSystemSetting::where('key', 'wag_paired_at')->delete();
            }

            AdmSystemSetting::updateOrCreate(
                ['key' => 'wag_connected'],
                [
                    'value' => $nowConnected ? '1' : '0',
                    'type' => 'boolean',
                    'description' => 'WhatsApp gateway connection status',
                ]
            );
        }

        $isConnected = $gatewayStatus['reachable']
            ? (bool) ($gatewayStatus['connected'] ?? false)
            : (bool) ($wagSetting?->value ?? false);

        $pairedAtRaw = AdmSystemSetting::where('key', 'wag_paired_at')->value('value');
        $pairedAtFormatted = $pairedAtRaw
            ? Carbon::parse($pairedAtRaw)->format('d M Y, H:i').' WIB'
            : ($isConnected && $wagSetting ? $wagSetting->updated_at?->format('d M Y, H:i').' WIB' : 'Menunggu scan');

        $qrSvg = null;
        if (($gatewayStatus['reachable'] ?? false) && ! $isConnected) {
            $qrPayload = $this->waClient->getQrCode();
            if ($qrPayload !== null) {
                $renderer = new ImageRenderer(new RendererStyle(120), new SvgImageBackEnd);
                $qrSvg = (new Writer($renderer))->writeString($qrPayload);
            }
        }

        return [
            'gatewayStatus' => $gatewayStatus,
            'isConnected' => $isConnected,
            'qrSvg' => $qrSvg,
            'gatewayBaseUrl' => (string) config('wagateway.base_url', 'http://wag:8080'),
            'pairedAt' => $pairedAtFormatted,
        ];
    }

    /**
     * Request OTP for SGpay merchant login (ShopeePay or GoPay).
     */
    public function requestPaygOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:shopee,gopay'],
            'phone' => ['required', 'string', 'max:25'],
        ]);

        $result = $this->paygService->requestSgpayOtp($validated['provider'], $validated['phone']);

        return response()->json($result);
    }

    /**
     * Verify OTP for SGpay merchant login.
     */
    public function verifyPaygOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:shopee,gopay'],
            'phone' => ['required', 'string', 'max:25'],
            'otp' => ['required', 'string', 'max:10'],
            'challenge' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->paygService->verifySgpayOtp(
            $validated['provider'],
            $validated['phone'],
            $validated['otp'],
            $validated['challenge'] ?? ''
        );

        return response()->json($result);
    }

    /**
     * Disconnect active SGpay session.
     */
    public function disconnectPayg(Request $request): JsonResponse
    {
        $provider = $request->input('provider');
        $result = $this->paygService->disconnectSgpay($provider ? (string) $provider : null);

        return response()->json($result);
    }

    /**
     * Get multi-channel fee calculation for SoBpay.
     */
    public function getPaygFees(Request $request): JsonResponse
    {
        $country = (string) $request->input('country', 'Indonesia');
        $amount = (float) $request->input('amount', 100000);

        $result = $this->paygService->getSobpayFees($country, $amount);

        return response()->json($result);
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function recordAudit(Request $request, string $action, array $after): void
    {
        $admin = Auth::guard('admin')->user();

        DB::table('adm_immutable_audit_trails')->insert([
            'actor_id' => $admin?->id,
            'actor_type' => 'ADMIN',
            'action' => $action,
            'target_type' => 'adm_system_settings',
            'target_id' => 'ai_config',
            'before' => null,
            'after' => json_encode($after),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
