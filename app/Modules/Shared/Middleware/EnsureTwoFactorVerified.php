<?php

namespace App\Modules\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan user sudah melewati 2FA challenge sebelum akses dashboard.
 * Session key 'two_factor_verified' di-set oleh TwoFactorController setelah OTP benar.
 */
class EnsureTwoFactorVerified
{
    /**
     * Redirect unverified portal users to 2FA setup or verification challenge.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $portal = $this->resolvePortalPrefix($request->getHost());
        $verificationKey = $portal === 'admin' ? 'admin_two_factor_verified' : 'two_factor_verified';

        if (! $request->session()->get($verificationKey)) {
            $user = $request->user('customer') ?? $request->user('operator') ?? $request->user('admin') ?? $request->user();

            if ($user && empty($user->two_factor_secret)) {
                return redirect()->route("{$portal}.2fa.setup");
            }

            return redirect()->route("{$portal}.2fa.challenge");
        }

        return $next($request);
    }

    private function resolvePortalPrefix(string $host): string
    {
        if ($host === config('app.domains.admin') || str_starts_with($host, 'admin.')) {
            return 'admin';
        }

        if ($host === config('app.domains.operator') || str_starts_with($host, 'operator.')) {
            return 'operator';
        }

        return 'customer';
    }
}
