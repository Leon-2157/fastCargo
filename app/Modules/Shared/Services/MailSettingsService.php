<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\Models\AdmSystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailSettingsService
{
    /**
     * Apply runtime mail configuration from database settings.
     */
    public static function apply(): void
    {
        try {
            $settings = Cache::remember('adm_mail_settings', 3600, function () {
                return AdmSystemSetting::whereIn('key', [
                    'mail_mailer',
                    'mail_host',
                    'mail_port',
                    'mail_username',
                    'mail_password',
                    'mail_encryption',
                    'mail_from_address',
                    'mail_from_name',
                ])->pluck('value', 'key')->toArray();
            });

            if (empty($settings)) {
                return;
            }

            $mailer = $settings['mail_mailer'] ?? config('mail.default', 'smtp');
            $host = $settings['mail_host'] ?? config('mail.mailers.smtp.host', 'smtp.resend.com');
            $port = (int) ($settings['mail_port'] ?? config('mail.mailers.smtp.port', 465));
            $username = $settings['mail_username'] ?? config('mail.mailers.smtp.username', 'resend');
            $password = $settings['mail_password'] ?? config('mail.mailers.smtp.password', '');
            $encryption = $settings['mail_encryption'] ?? config('mail.mailers.smtp.encryption', 'tls');
            $fromAddress = $settings['mail_from_address'] ?? config('mail.from.address', 'noreply@leonproject.my.id');
            $fromName = $settings['mail_from_name'] ?? config('mail.from.name', 'FastCargo');

            $scheme = null;
            if ($encryption === 'ssl' || $port === 465) {
                $scheme = 'smtps';
            } elseif ($encryption === 'tls' || $port === 587) {
                $scheme = null; // STARTTLS
            }

            config([
                'mail.default' => $mailer,
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => $port,
                'mail.mailers.smtp.username' => $username,
                'mail.mailers.smtp.password' => $password,
                'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
                'mail.mailers.smtp.scheme' => $scheme,
                'mail.from.address' => $fromAddress,
                'mail.from.name' => $fromName,
            ]);

            // Purge cached transport so changes take effect immediately
            if (app()->bound('mail.manager')) {
                try {
                    app('mail.manager')->purge($mailer);
                    if ($mailer !== 'smtp') {
                        app('mail.manager')->purge('smtp');
                    }
                } catch (Throwable) {
                    // Ignore during early bootstrap
                }
            }
        } catch (Throwable $e) {
            // Silently ignore during migration/early boot when tables don't exist
            Log::debug('MailSettingsService::apply skipped: '.$e->getMessage());
        }
    }

    /**
     * Get array of mail settings for admin display.
     *
     * @return array<string, mixed>
     */
    public static function getAdminSummary(): array
    {
        $settings = AdmSystemSetting::whereIn('key', [
            'mail_mailer',
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_from_address',
            'mail_from_name',
        ])->pluck('value', 'key');

        $password = $settings->get('mail_password', (string) config('mail.mailers.smtp.password', ''));

        return [
            'mail_mailer' => $settings->get('mail_mailer', config('mail.default', 'smtp')),
            'mail_host' => $settings->get('mail_host', config('mail.mailers.smtp.host', 'smtp.resend.com')),
            'mail_port' => $settings->get('mail_port', config('mail.mailers.smtp.port', 465)),
            'mail_username' => $settings->get('mail_username', config('mail.mailers.smtp.username', 'resend')),
            'mail_encryption' => $settings->get('mail_encryption', config('mail.mailers.smtp.encryption', 'tls')),
            'mail_from_address' => $settings->get('mail_from_address', config('mail.from.address', 'noreply@leonproject.my.id')),
            'mail_from_name' => $settings->get('mail_from_name', config('mail.from.name', 'FastCargo')),
            'is_password_configured' => trim((string) $password) !== '',
        ];
    }
}
