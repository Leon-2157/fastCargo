<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\AI\AiManager;
use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Models\PaymentTransaction;
use App\Modules\Shared\Services\AuditLogService;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class SystemSettingsController extends Controller
{
    private const SECRET_KEYS = [
        'custom_ai_api_key',
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

        $customAiKey = AiManager::settingValue('custom_ai_api_key', (string) config('ai.providers.custom.api_key', ''));
        $geminiKey = AiManager::settingValue('gemini_api_key', (string) config('ai.providers.gemini.api_key', ''));
        $aiModelsRegistry = AiManager::getAiModelsRegistry();

        $defaultGeminiModel = $aiModelsRegistry['google_gemini_api'][0]['model'] ?? 'gemini-flash-lite-latest';
        $defaultCustomModel = $aiModelsRegistry['openai_compatible'][0]['model'] ?? 'ag/gemini-3.8-flash-low';

        $waAiProvider = (string) AdmSystemSetting::get('wa_ai_provider', 'default');
        $mailAiProvider = (string) AdmSystemSetting::get('mail_ai_provider', 'default');

        $aiConfig = [
            'active_provider' => AiManager::activeProviderKey(),
            'active_label' => AiManager::activeProviderLabel(),
            'custom_ai_provider_name' => AiManager::settingValue('custom_ai_provider_name', (string) config('ai.providers.custom.name', 'Custom AI')),
            'custom_ai_base_url' => AiManager::settingValue('custom_ai_base_url', (string) config('ai.providers.custom.base_url', 'http://localhost:20128/v1')),
            'custom_ai_api_key' => $customAiKey !== '' ? '••••••••••••••••' : '',
            'custom_ai_model' => AiManager::settingValue('custom_ai_model', (string) config('ai.providers.custom.model', $defaultCustomModel)),
            'custom_ai_max_completion_tokens' => AiManager::settingValue('custom_ai_max_completion_tokens', '4096'),
            'custom_ai_max_output_tokens' => AiManager::settingValue('custom_ai_max_output_tokens', '2048'),
            'custom_ai_max_tokens' => AiManager::settingValue('custom_ai_max_tokens', '8192'),
            'custom_ai_key_configured' => $customAiKey !== '',
            'gemini_api_key' => $geminiKey !== '' ? '••••••••••••••••' : '',
            'gemini_model' => AiManager::settingValue('gemini_model', (string) config('ai.providers.gemini.model', $defaultGeminiModel)),
            'gemini_key_configured' => $geminiKey !== '',
            'models_registry' => $aiModelsRegistry,
            'wa_ai_provider' => $waAiProvider,
            'mail_ai_provider' => $mailAiProvider,
        ];

        $waSnapshot = $this->resolveGatewaySnapshot();
        $mailConfig = MailSettingsService::getAdminSummary();

        $paygStatus = $this->paygService->getGatewayStatus();
        $paygActiveMode = AdmSystemSetting::get('payg_active_mode', 'sgpay');
        $paygStaticQr = PayGPaymentGatewayService::cleanQrisString((string) AdmSystemSetting::get('payg_sgpay_static_qr', ''));
        $paygSecret = (string) AdmSystemSetting::get('payg_secret', config('payg.secret', ''));
        $merchantPreview = $paygStaticQr !== '' ? $this->paygService->parseEmvcoMetadata($paygStaticQr) : null;
        $staticQrSvg = ($paygStaticQr !== '' && ($merchantPreview['valid'] ?? false))
            ? $this->paygService->renderQrSvg($paygStaticQr)
            : null;

        $paygConfig = [
            'status' => $paygStatus,
            'active_mode' => (string) $paygActiveMode,
            'sgpay_static_qr' => $paygStaticQr,
            'merchant_preview' => $merchantPreview,
            'static_qr_svg' => $staticQrSvg,
            'secret' => $paygSecret !== '' ? '••••••••••••••••' : '',
            'is_secret_configured' => $paygSecret !== '',
            'webhook_url' => url('/api/internal/payg/webhook'),
        ];

        return view('admin.settings.index', [
            'role' => 'admin',
            'settings' => $settings,
            'aiConfig' => $aiConfig,
            'aiModelsRegistry' => $aiModelsRegistry,
            'mailConfig' => $mailConfig,
            'waConfig' => $waSnapshot,
            'paygConfig' => $paygConfig,
            'merchantPreview' => $merchantPreview,
            'staticQrSvg' => $staticQrSvg,
            'waAiProvider' => $waAiProvider,
            'mailAiProvider' => $mailAiProvider,
            'wa_ai_provider' => $waAiProvider,
            'mail_ai_provider' => $mailAiProvider,
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'wa_ai_provider' => ['nullable', 'string', 'regex:/^(default|gemini|custom|disabled|(google_gemini_api|openai_compatible)::.+)$/'],
            'mail_ai_provider' => ['nullable', 'string', 'regex:/^(default|gemini|custom|disabled|(google_gemini_api|openai_compatible)::.+)$/'],
            'custom_ai_provider_name' => ['nullable', 'string', 'max:120'],
            'custom_ai_base_url' => ['nullable', 'url', 'max:255'],
            'custom_ai_api_key' => ['nullable', 'string', 'max:255'],
            'custom_ai_model' => ['nullable', 'string', 'max:120'],
            'custom_ai_max_completion_tokens' => ['nullable', 'integer', 'min:1'],
            'custom_ai_max_output_tokens' => ['nullable', 'integer', 'min:1'],
            'custom_ai_max_tokens' => ['nullable', 'integer', 'min:1'],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'gemini_model' => ['nullable', 'string', 'max:120'],
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
            'payg_active_mode' => ['nullable', 'string', 'in:auto,sgpay'],
            'payg_sgpay_static_qr' => ['nullable', 'string'],
            'payg_secret' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['operator_vpn_whitelist'])) {
            $decoded = json_decode($validated['operator_vpn_whitelist'], true);
            if (! is_array($decoded)) {
                return back()->withErrors(['operator_vpn_whitelist' => 'VPN whitelist must be a valid JSON CIDR array.'])->withInput();
            }
            $validated['operator_vpn_whitelist'] = json_encode($decoded);
        }

        if (array_key_exists('payg_sgpay_static_qr', $validated)) {
            if ($validated['payg_sgpay_static_qr'] === null) {
                $validated['payg_sgpay_static_qr'] = '';
            } else {
                $cleanedQr = PayGPaymentGatewayService::cleanQrisString((string) $validated['payg_sgpay_static_qr']);
                if ($cleanedQr !== '' && ! $this->paygService->isValidEmvcoQris($cleanedQr)) {
                    $meta = $this->paygService->parseEmvcoMetadata($cleanedQr);
                    $reason = $meta['error'] ?? 'Format QRIS EMVCo ASPI atau checksum CRC-16 tidak valid.';

                    throw ValidationException::withMessages([
                        'payg_sgpay_static_qr' => "Static QRIS tidak valid: {$reason}",
                    ]);
                }
                $validated['payg_sgpay_static_qr'] = $cleanedQr;
            }
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

        if (! empty($validated['custom_ai_model'])) {
            $currentCustomModel = trim((string) $validated['custom_ai_model']);
            $registry = AiManager::getAiModelsRegistry();
            $customModels = $registry['openai_compatible'] ?? [];
            $found = false;

            foreach ($customModels as $idx => $entry) {
                if (($entry['model'] ?? '') === $currentCustomModel) {
                    if (isset($validated['custom_ai_max_completion_tokens'])) {
                        $customModels[$idx]['completion_tokens'] = (int) $validated['custom_ai_max_completion_tokens'];
                    }
                    if (isset($validated['custom_ai_max_output_tokens'])) {
                        $customModels[$idx]['output_tokens'] = (int) $validated['custom_ai_max_output_tokens'];
                    }
                    if (isset($validated['custom_ai_max_tokens'])) {
                        $customModels[$idx]['max_tokens'] = (int) $validated['custom_ai_max_tokens'];
                    }
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $customModels[] = [
                    'model' => $currentCustomModel,
                    'completion_tokens' => (int) ($validated['custom_ai_max_completion_tokens'] ?? 4096),
                    'output_tokens' => (int) ($validated['custom_ai_max_output_tokens'] ?? 2048),
                    'max_tokens' => (int) ($validated['custom_ai_max_tokens'] ?? 8192),
                ];
            }

            $registry['openai_compatible'] = $customModels;
            AiManager::saveAiModelsRegistry($registry);
        }

        if (! empty($validated['gemini_model'])) {
            $currentGeminiModel = trim((string) $validated['gemini_model']);
            $registry = AiManager::getAiModelsRegistry();
            $geminiModels = $registry['google_gemini_api'] ?? [];
            if (! collect($geminiModels)->contains('model', $currentGeminiModel)) {
                $geminiModels[] = [
                    'model' => $currentGeminiModel,
                    'completion_tokens' => 4096,
                    'output_tokens' => 2048,
                    'max_tokens' => 8192,
                ];
                $registry['google_gemini_api'] = $geminiModels;
                AiManager::saveAiModelsRegistry($registry);
            }
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
        if (isset($validated['payg_secret']) && ! empty($validated['payg_secret']) && ! str_starts_with((string) $validated['payg_secret'], '••••')) {
            $daemonConfig['shared_secret'] = (string) $validated['payg_secret'];
        }
        if (! empty($daemonConfig)) {
            $this->paygService->updateDaemonConfig($daemonConfig);
        }

        AuditLogService::record(
            'SYSTEM_SETTINGS_UPDATED',
            'adm_system_settings',
            'platform_config',
            null,
            $auditChanges,
            $request
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'AI configuration settings saved successfully.',
            ]);
        }

        return back()->with('success', 'System settings have been updated successfully.');
    }

    /**
     * Reveal the active PayG shared secret for authenticated 2FA admins configuring MacroDroid.
     */
    public function revealPaymentSecret(): JsonResponse
    {
        $configured = AdmSystemSetting::get('payg_secret');
        $secret = (is_string($configured) && $configured !== '')
            ? $configured
            : (string) config('payg.secret', 'fastcargo-payg-secret-2026');

        return response()->json([
            'success' => true,
            'secret' => $secret,
        ]);
    }

    /**
     * Render a live SVG QR preview and parsed EMVCo ASPI merchant metadata for Admin Settings.
     */
    public function previewPaymentQr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qris' => ['nullable', 'string', 'max:2048'],
        ]);

        $cleaned = PayGPaymentGatewayService::cleanQrisString((string) ($validated['qris'] ?? ''));
        if ($cleaned === '') {
            return response()->json([
                'success' => true,
                'valid' => false,
                'svg' => null,
                'metadata' => null,
            ]);
        }

        $metadata = $this->paygService->parseEmvcoMetadata($cleaned);
        $svg = $metadata['valid'] ? $this->paygService->renderQrSvg($cleaned) : null;

        return response()->json([
            'success' => true,
            'valid' => $metadata['valid'],
            'svg' => $svg,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Dry-run IDR extraction & pending-invoice lookup for MacroDroid setup testing without mutating state.
     */
    public function testWebhookExtraction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_text' => ['required', 'string', 'max:1000'],
        ]);

        $amount = PayGPaymentGatewayService::extractIdrAmount($validated['notification_text']);
        if ($amount === null) {
            return response()->json([
                'success' => false,
                'amount' => null,
                'matching_invoices' => 0,
                'message' => 'Tidak ditemukan nominal IDR positif pada teks notifikasi tersebut.',
            ], 422);
        }

        $matchingInvoices = PaymentTransaction::where('payment_status', PaymentTransaction::STATUS_PENDING)
            ->where('amount', $amount)
            ->where('created_at', '>=', now()->subHours(24))
            ->whereHas('invoice', static function ($query): void {
                $query->where('payment_status', '!=', 'PAID_SETTLED');
            })
            ->distinct('invoice_id')
            ->count('invoice_id');

        return response()->json([
            'success' => true,
            'amount' => $amount,
            'formatted_amount' => 'Rp '.number_format($amount, 0, ',', '.'),
            'matching_invoices' => $matchingInvoices,
            'message' => $matchingInvoices === 1
                ? 'Nominal terdeteksi dan cocok unik dengan 1 invoice pending.'
                : ($matchingInvoices > 1
                    ? "Peringatan: ditemukan {$matchingInvoices} invoice pending dengan nominal sama (409 Conflict)."
                    : 'Nominal berhasil diekstrak; belum ada invoice pending dengan nominal tersebut dalam 24 jam terakhir.'),
        ]);
    }

    public function switchAiProvider(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_provider' => ['required', 'in:custom,gemini'],
        ]);

        $provider = $validated['ai_provider'];
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

        AuditLogService::record(
            'AI_PROVIDER_SWITCHED',
            'adm_system_settings',
            'ai_provider',
            ['ai_provider' => $previous],
            ['ai_provider' => $provider],
            $request
        );

        return back()->with('success', "Active AI intelligence engine switched to {$provider}.");
    }

    /**
     * Real-time AJAX verification of AI engine credentials and live endpoint ping.
     */
    public function testAiConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:gemini,custom'],
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
                if ($apiKey === '' || str_starts_with($apiKey, '••••')) {
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
                    $baseUrl = rtrim(AiManager::settingValue('custom_ai_base_url', (string) config('ai.providers.custom.base_url', 'http://localhost:20128/v1')), '/');
                }

                $apiKey = trim((string) ($validated['custom_ai_api_key'] ?? ''));
                if ($apiKey === '' || str_starts_with($apiKey, '••••')) {
                    $apiKey = AiManager::settingValue('custom_ai_api_key', '');
                }

                $model = trim((string) ($validated['custom_ai_model'] ?? ''));
                if ($model === '') {
                    $model = AiManager::settingValue('custom_ai_model', (string) config('ai.providers.custom.model', 'ag/gemini-3.8-flash-high'));
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

    public function addAiModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'engine' => ['required', 'string', 'in:google_gemini_api,openai_compatible'],
            'model' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_\-\.\/]+$/'],
            'completion_tokens' => ['nullable', 'integer', 'min:1'],
            'output_tokens' => ['nullable', 'integer', 'min:1'],
            'max_tokens' => ['nullable', 'integer', 'min:1'],
        ]);

        $engine = $validated['engine'];
        $modelName = trim($validated['model']);

        $registry = AiManager::getAiModelsRegistry();
        $existingModels = $registry[$engine] ?? [];

        foreach ($existingModels as $existing) {
            if (strcasecmp($existing['model'] ?? '', $modelName) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Model '{$modelName}' is already registered for this engine.",
                ], 422);
            }
        }

        $newEntry = [
            'model' => $modelName,
            'completion_tokens' => (int) ($validated['completion_tokens'] ?? 4096),
            'output_tokens' => (int) ($validated['output_tokens'] ?? 2048),
            'max_tokens' => (int) ($validated['max_tokens'] ?? 8192),
        ];

        $registry[$engine][] = $newEntry;
        AiManager::saveAiModelsRegistry($registry);

        AuditLogService::record(
            'AI_MODEL_ADDED',
            'adm_system_settings',
            'ai_models_registry',
            null,
            ['engine' => $engine, 'model' => $modelName],
            $request
        );

        return response()->json([
            'success' => true,
            'message' => "Model '{$modelName}' added to registry.",
            'registry' => $registry,
            'model' => $newEntry,
        ]);
    }

    public function deleteAiModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'engine' => ['required', 'string', 'in:google_gemini_api,openai_compatible'],
            'model' => ['required', 'string', 'max:120'],
        ]);

        $engine = $validated['engine'];
        $modelName = trim($validated['model']);

        $registry = AiManager::getAiModelsRegistry();
        $models = $registry[$engine] ?? [];

        if (count($models) <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the last remaining model for this engine.',
            ], 422);
        }

        $filtered = array_values(array_filter($models, fn (array $m): bool => ($m['model'] ?? '') !== $modelName));

        if (count($filtered) === count($models)) {
            return response()->json([
                'success' => false,
                'message' => "Model '{$modelName}' not found in registry.",
            ], 404);
        }

        $registry[$engine] = $filtered;
        AiManager::saveAiModelsRegistry($registry);

        if ($engine === 'google_gemini_api' && AiManager::settingValue('gemini_model') === $modelName) {
            AdmSystemSetting::set('gemini_model', $filtered[0]['model']);
        } elseif ($engine === 'openai_compatible' && AiManager::settingValue('custom_ai_model') === $modelName) {
            AdmSystemSetting::set('custom_ai_model', $filtered[0]['model']);
            AdmSystemSetting::set('custom_ai_max_completion_tokens', (string) ($filtered[0]['completion_tokens'] ?? 4096));
            AdmSystemSetting::set('custom_ai_max_output_tokens', (string) ($filtered[0]['output_tokens'] ?? 2048));
            AdmSystemSetting::set('custom_ai_max_tokens', (string) ($filtered[0]['max_tokens'] ?? 8192));
        }

        $targetPrefixed = "{$engine}::{$modelName}";
        if (AiManager::settingValue('wa_ai_provider') === $targetPrefixed) {
            AdmSystemSetting::set('wa_ai_provider', 'default');
        }
        if (AiManager::settingValue('mail_ai_provider') === $targetPrefixed) {
            AdmSystemSetting::set('mail_ai_provider', 'default');
        }

        AiManager::reset();

        AuditLogService::record(
            'AI_MODEL_DELETED',
            'adm_system_settings',
            'ai_models_registry',
            ['engine' => $engine, 'model' => $modelName],
            ['fallback_model' => $filtered[0]['model']],
            $request
        );

        return response()->json([
            'success' => true,
            'message' => "Model '{$modelName}' removed from registry.",
            'registry' => $registry,
            'fallback_model' => $filtered[0]['model'],
        ]);
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
            : ($isConnected && $wagSetting ? $wagSetting->updated_at?->format('d M Y, H:i').' WIB' : 'Awaiting scan');

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
}
