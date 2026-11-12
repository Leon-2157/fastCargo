<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\AI\AiManager;
use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Services\MailSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SystemSettingsController extends Controller
{
    private const SECRET_KEYS = [
        'ninerouter_api_key',
        'gemini_api_key',
        'mail_password',
    ];

    public function index(): View
    {
        $settings = AdmSystemSetting::all()->keyBy('key');

        $nineRouterKey = AiManager::settingValue('ninerouter_api_key', (string) config('ai.providers.9router.api_key', ''));
        $geminiKey = AiManager::settingValue('gemini_api_key', (string) config('ai.providers.gemini.api_key', ''));

        $aiConfig = [
            'active_provider' => AiManager::activeProviderKey(),
            'active_label' => AiManager::activeProviderLabel(),
            'ninerouter_base_url' => AiManager::settingValue('ninerouter_base_url', (string) config('ai.providers.9router.base_url', 'http://localhost:20128/v1')),
            'ninerouter_model' => AiManager::settingValue('ninerouter_model', (string) config('ai.providers.9router.model', 'ag/gemini-3.8-flash-high')),
            'ninerouter_key_configured' => $nineRouterKey !== '',
            'gemini_model' => AiManager::settingValue('gemini_model', (string) config('ai.providers.gemini.model', 'gemini-2.0-flash')),
            'gemini_key_configured' => $geminiKey !== '',
        ];

        $mailConfig = MailSettingsService::getAdminSummary();

        return view('admin.settings.index', [
            'role' => 'admin',
            'settings' => $settings,
            'aiConfig' => $aiConfig,
            'mailConfig' => $mailConfig,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ninerouter_base_url' => ['nullable', 'url', 'max:255'],
            'ninerouter_api_key' => ['nullable', 'string', 'max:255'],
            'ninerouter_model' => ['nullable', 'string', 'max:120'],
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
        ]);

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
            if (in_array($key, self::SECRET_KEYS, true) && $stringValue === '') {
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
        AiManager::reset();
        MailSettingsService::apply();

        if (! empty($auditChanges)) {
            $this->recordAudit($request, 'SYSTEM_SETTINGS_UPDATED', $auditChanges);
        }

        return back()->with('success', 'System parameters, mail delivery engine, and operational configurations updated successfully.');
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

            Mail::raw("Hello from FastCargo Logistics!\n\nThis is a verified test email sent via Resend SMTP engine.\nTimestamp: ".now()->toIso8601String()."\nSender: {$from} ({$fromName})\n\nIf you received this message, your FastCargo email delivery and 2FA reset dispatch are properly configured and operational.", function ($message) use ($recipient, $from, $fromName) {
                $message->to($recipient)
                    ->from($from, $fromName)
                    ->subject('FastCargo — Email Delivery Test ('.now()->format('H:i:s').')');
            });

            $this->recordAudit($request, 'TEST_EMAIL_DISPATCHED', [
                'recipient' => $recipient,
                'status' => 'SUCCESS',
            ]);

            return back()->with('success', "Test email was sent successfully to {$recipient}. Please check your inbox (and spam/promotions tab).");
        } catch (Throwable $e) {
            Log::error('Send test email failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->with('error', 'Failed to dispatch test email: '.$e->getMessage());
        }
    }

    public function switchAiProvider(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_provider' => ['required', 'in:9router,gemini'],
        ]);

        $provider = $validated['ai_provider'];
        $previous = AdmSystemSetting::where('key', 'ai_provider')->value('value');

        AdmSystemSetting::updateOrCreate(
            ['key' => 'ai_provider'],
            [
                'value' => $provider,
                'type' => 'string',
                'description' => 'Active AI provider: 9router or gemini',
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
