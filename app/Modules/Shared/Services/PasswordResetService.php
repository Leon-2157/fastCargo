<?php

namespace App\Modules\Shared\Services;

use App\Mail\PasswordResetNotificationMail;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetService
{
    public const VALID_MINUTES = 60;

    public const MAX_ATTEMPTS_PER_WINDOW = 4;

    public const RATE_LIMIT_MINUTES = 15;

    /**
     * Check if email or IP address is currently rate-limited.
     */
    public static function isRateLimited(string $email, ?string $ip = null): bool
    {
        $cutoff = now()->subMinutes(self::RATE_LIMIT_MINUTES);

        $recentRequests = DB::table('adm_immutable_audit_trails')
            ->where('action', 'PASSWORD_RESET_REQUESTED')
            ->where('created_at', '>=', $cutoff)
            ->where(function ($query) use ($email, $ip) {
                $query->where('before', 'like', '%'.json_encode(['email' => $email]).'%');
                if ($ip !== null) {
                    $query->orWhere('ip_address', $ip);
                }
            })
            ->count();

        return $recentRequests >= self::MAX_ATTEMPTS_PER_WINDOW;
    }

    /**
     * Dispatch an encrypted, single-use password reset link via official email.
     *
     * @return array{success: bool, message: string, reset_url?: string}
     */
    public static function sendResetLink(User $user, string $portal, ?string $ip = null, ?string $userAgent = null): array
    {
        if (self::isRateLimited($user->email, $ip)) {
            return [
                'success' => false,
                'message' => 'Too many reset requests. Please wait '.self::RATE_LIMIT_MINUTES.' minutes before trying again.',
            ];
        }

        if ($user->status !== 'ACTIVE') {
            return [
                'success' => false,
                'message' => 'This account is currently suspended or locked. Contact FastCargo regional administration.',
            ];
        }

        $rawToken = Str::random(64);
        $tokenHash = hash('sha256', $rawToken);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $tokenHash,
                'created_at' => now(),
            ]
        );

        $portalLabel = $portal === 'operator' ? 'Terminal Operator' : 'Customer Enterprise';
        $routeName = $portal === 'operator' ? 'operator.password.reset' : 'customer.password.reset';
        $resetUrl = route($routeName, [
            'token' => $rawToken,
            'email' => $user->email,
        ], false);

        // Convert to absolute URL with current request domain context
        $absoluteUrl = url($resetUrl);

        try {
            Mail::to($user->email)->send(
                new PasswordResetNotificationMail($user->name, $absoluteUrl, $portalLabel, self::VALID_MINUTES)
            );
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch password reset email: '.$e->getMessage(), [
                'email' => $user->email,
                'portal' => $portal,
            ]);
        }

        // Record immutable audit trail
        DB::table('adm_immutable_audit_trails')->insert([
            'actor_id' => $user->id,
            'actor_type' => $portal === 'operator' ? 'OPERATOR' : 'CUSTOMER',
            'action' => 'PASSWORD_RESET_REQUESTED',
            'target_type' => 'users',
            'target_id' => $user->id,
            'before' => json_encode(['email' => $user->email]),
            'after' => json_encode(['status' => 'DISPATCHED', 'valid_minutes' => self::VALID_MINUTES]),
            'ip_address' => $ip,
            'created_at' => now(),
        ]);

        Log::info("Password reset token generated for [{$user->email}] on portal [{$portal}]", [
            'reset_url' => $absoluteUrl,
        ]);

        return [
            'success' => true,
            'message' => 'A password reset link has been dispatched to your registered email address.',
            'reset_url' => $absoluteUrl,
        ];
    }

    /**
     * Validate reset token authenticity and expiration.
     */
    public static function validateResetToken(string $email, string $rawToken): bool
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record || empty($record->token) || empty($record->created_at)) {
            return false;
        }

        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(self::VALID_MINUTES)->isPast()) {
            return false;
        }

        return hash_equals($record->token, hash('sha256', $rawToken));
    }

    /**
     * Complete the password reset procedure and invalidate token.
     *
     * @return array{success: bool, message: string}
     */
    public static function resetPassword(string $email, string $rawToken, string $newPassword, ?string $ip = null): array
    {
        if (! self::validateResetToken($email, $rawToken)) {
            return [
                'success' => false,
                'message' => 'The password reset token is invalid or has expired. Please submit a new reset request.',
            ];
        }

        /** @var User|null $user */
        $user = User::where('email', $email)->first();
        if (! $user) {
            return [
                'success' => false,
                'message' => 'Unable to locate an active user account associated with this email address.',
            ];
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Invalidate single-use token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Record immutable audit trail
        DB::table('adm_immutable_audit_trails')->insert([
            'actor_id' => $user->id,
            'actor_type' => $user->isOperator() ? 'OPERATOR' : 'CUSTOMER',
            'action' => 'PASSWORD_RESET_COMPLETED',
            'target_type' => 'users',
            'target_id' => $user->id,
            'before' => json_encode(['email' => $user->email]),
            'after' => json_encode(['status' => 'PASSWORD_UPDATED', 'two_factor_enforced' => true]),
            'ip_address' => $ip,
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Your password has been successfully updated. Please sign in with your new credentials.',
        ];
    }
}
