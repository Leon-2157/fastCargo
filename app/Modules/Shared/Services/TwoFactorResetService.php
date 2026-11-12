<?php

namespace App\Modules\Shared\Services;

use App\Mail\TwoFactorResetOtpMail;
use App\Modules\Shared\Models\TwoFactorResetToken;
use App\Modules\Shared\Models\User;
use App\Modules\Shared\WhatsApp\WaGatewayClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TwoFactorResetService
{
    public const VALID_MINUTES = 10;

    public const MAX_ATTEMPTS_PER_WINDOW = 4;

    public const RATE_LIMIT_MINUTES = 15;

    /**
     * Check if user is rate-limited from requesting new reset tokens.
     */
    public static function isRateLimited(User $user): bool
    {
        $recentCount = TwoFactorResetToken::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(self::RATE_LIMIT_MINUTES))
            ->count();

        return $recentCount >= self::MAX_ATTEMPTS_PER_WINDOW;
    }

    /**
     * Generate and dispatch a 2FA reset OTP code via Email or WhatsApp.
     *
     * @return array{success: bool, message: string, channel: string}
     */
    public static function sendResetToken(User $user, string $channel, ?string $ip = null, ?string $userAgent = null): array
    {
        if (self::isRateLimited($user)) {
            return [
                'success' => false,
                'message' => 'Too many reset requests. Please wait '.self::RATE_LIMIT_MINUTES.' minutes before trying again.',
                'channel' => $channel,
            ];
        }

        $channel = strtoupper($channel);
        if (! in_array($channel, ['EMAIL', 'WHATSAPP'], true)) {
            $channel = 'EMAIL';
        }

        if ($channel === 'WHATSAPP' && empty(trim((string) $user->phone))) {
            return [
                'success' => false,
                'message' => 'No registered mobile/WhatsApp number found on your account. Please use Email verification.',
                'channel' => 'EMAIL',
            ];
        }

        // Invalidate older unused tokens for this user
        TwoFactorResetToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otpCode = sprintf('%06d', random_int(100000, 999999));
        $tokenHash = hash('sha256', $otpCode);

        $resetToken = TwoFactorResetToken::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'channel' => $channel,
            'token_hash' => $tokenHash,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'expires_at' => now()->addMinutes(self::VALID_MINUTES),
            'used_at' => null,
        ]);

        if ($channel === 'WHATSAPP') {
            try {
                $client = app(WaGatewayClient::class);
                $normalizedPhone = WaGatewayClient::normalizePhone($user->phone);

                $text = "*[FastCargo Security]*\n"
                    ."Halo *{$user->name}*,\n\n"
                    ."Kode OTP reset 2-Factor Authentication Anda adalah:\n"
                    ."*{$otpCode}*\n\n"
                    .'Kode berlaku selama '.self::VALID_MINUTES." menit. Jangan bagikan kode ini kepada siapa pun.\n"
                    .'Setelah memasukkan kode, Anda akan diminta memasang authenticator baru.';

                $sent = $client->sendText($normalizedPhone, $text);

                if (! $sent) {
                    Log::warning('WhatsApp 2FA reset dispatch returned false, falling back prompt', ['phone' => $normalizedPhone]);
                }

                return [
                    'success' => true,
                    'message' => 'Reset code has been sent via WhatsApp to '.substr($normalizedPhone, 0, 5).'****'.substr($normalizedPhone, -2),
                    'channel' => 'WHATSAPP',
                ];
            } catch (Throwable $e) {
                Log::error('Failed to send WhatsApp 2FA reset OTP: '.$e->getMessage());

                return [
                    'success' => false,
                    'message' => 'Failed to send WhatsApp message. Please choose Email instead.',
                    'channel' => 'EMAIL',
                ];
            }
        }

        // Default: EMAIL
        try {
            MailSettingsService::apply();
            Mail::to($user->email)->send(new TwoFactorResetOtpMail($user->name, $otpCode, self::VALID_MINUTES));

            return [
                'success' => true,
                'message' => 'A 6-digit verification code has been sent to your email ('.$user->email.'). Please check your inbox.',
                'channel' => 'EMAIL',
            ];
        } catch (Throwable $e) {
            Log::error('Failed to send 2FA reset email: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to deliver reset email: '.$e->getMessage(),
                'channel' => 'EMAIL',
            ];
        }
    }

    /**
     * Verify submitted OTP code and clear 2FA credentials on success.
     *
     * @return array{success: bool, message: string}
     */
    public static function verifyAndReset(User $user, string $code): array
    {
        $code = trim($code);
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return [
                'success' => false,
                'message' => 'Verification code must be exactly 6 digits.',
            ];
        }

        $inputHash = hash('sha256', $code);

        $token = TwoFactorResetToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $token || ! hash_equals($token->token_hash, $inputHash)) {
            return [
                'success' => false,
                'message' => 'Invalid or expired verification code. Please request a new code.',
            ];
        }

        // Mark token as consumed
        $token->update(['used_at' => now()]);

        // Revoke 2FA secret and enabled timestamp
        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
        ]);

        Log::info('2FA self-service reset completed successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'channel' => $token->channel,
        ]);

        return [
            'success' => true,
            'message' => 'Two-Factor Authentication has been reset. Please configure a new authenticator immediately to protect your account.',
        ];
    }

    /**
     * Reset 2FA by administrative action with audit recording.
     */
    public static function adminReset(User $targetUser, ?User $actorAdmin = null, ?string $ip = null): void
    {
        $beforeSecret = $targetUser->two_factor_secret ? '[CONFIGURED]' : '[NONE]';

        $targetUser->update([
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
        ]);

        // Invalidate any pending tokens
        TwoFactorResetToken::where('user_id', $targetUser->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        DB::table('adm_immutable_audit_trails')->insert([
            'actor_id' => $actorAdmin?->id,
            'actor_type' => 'ADMIN',
            'action' => 'USER_2FA_MANUAL_RESET',
            'target_type' => 'users',
            'target_id' => $targetUser->id,
            'before' => json_encode(['two_factor_status' => $beforeSecret]),
            'after' => json_encode(['two_factor_status' => 'RESET_CLEARED']),
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    }
}
