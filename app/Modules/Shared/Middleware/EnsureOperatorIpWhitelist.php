<?php

namespace App\Modules\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak request dari IP yang tidak ada di whitelist VPN operator.
 * Whitelist di-load dari adm_system_settings untuk bisa diubah via admin dashboard.
 */
class EnsureOperatorIpWhitelist
{
    /**
     * Validate that the operator portal request originates from an allowed VPN CIDR.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Saat dev lokal atau proteksi dialihkan ke Zero Trust, skip whitelist check
        if ($this->appShouldBypassWhitelist()) {
            return $next($request);
        }

        $clientIp = $request->ip();
        $allowedRanges = $this->loadAllowedRanges();

        if (! empty($clientIp) && ! empty($allowedRanges) && IpUtils::checkIp($clientIp, $allowedRanges)) {
            return $next($request);
        }

        abort(403, 'Access denied: IP not in operator VPN whitelist.');
    }

    private function appShouldBypassWhitelist(): bool
    {
        return app()->environment('local') || ! config('app.operator_ip_whitelist_enabled', true);
    }

    /**
     * @return array<int, string>
     */
    private function loadAllowedRanges(): array
    {
        // shortcut: cache 5 menit agar tidak query DB tiap request
        $ranges = cache()->remember('operator_vpn_whitelist', 300, function (): array {
            $setting = DB::table('adm_system_settings')
                ->where('key', 'operator_vpn_whitelist')
                ->value('value');

            $decoded = $setting ? json_decode((string) $setting, true) : [];

            return is_array($decoded) ? $decoded : [];
        });

        return array_values(array_filter(
            $ranges,
            fn (mixed $range): bool => is_string($range) && trim($range) !== ''
        ));
    }
}
