<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Models\AdmSystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPayGSignature
{
    /**
     * Handle an incoming request and verify the HMAC-SHA256 signature from PayG microservice.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $internalSecret = (string) config('app.internal_secret', '');
        $providedInternal = (string) $request->header('X-Internal-Secret', '');

        // 1. Check if authenticated via internal secret (e.g. internal tests / dispatchers)
        if ($internalSecret !== '' && hash_equals($internalSecret, $providedInternal)) {
            return $next($request);
        }

        // 2. Load shared PayG secret (Admin DB setting takes precedence over env/config)
        $configuredSecret = AdmSystemSetting::get('payg_secret');
        $configSecret = trim((string) config('payg.secret', ''));
        $sharedSecret = (is_string($configuredSecret) && trim($configuredSecret) !== '')
            ? trim($configuredSecret)
            : ($configSecret !== '' ? $configSecret : 'fastcargo-payg-secret-2026');

        // 3. Check direct X-PayG-Secret header (zero-code MacroDroid Free integration)
        $providedPaygSecret = trim((string) $request->header('X-PayG-Secret', ''));
        if ($sharedSecret !== '' && $providedPaygSecret !== '' && hash_equals($sharedSecret, $providedPaygSecret)) {
            return $next($request);
        }

        // 4. Check X-PayG-Signature HMAC-SHA256
        $signatureHeader = trim((string) $request->header('X-PayG-Signature', ''));
        if ($sharedSecret !== '' && $signatureHeader !== '') {
            $rawSignature = str_starts_with($signatureHeader, 'sha256=')
                ? substr($signatureHeader, 7)
                : $signatureHeader;

            $payload = $request->getContent();
            $expectedSignature = hash_hmac('sha256', $payload, $sharedSecret);

            if (! hash_equals(strtolower($expectedSignature), strtolower($rawSignature))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Invalid PayG HMAC signature.',
                ], 403);
            }

            return $next($request);
        }

        if ($providedPaygSecret !== '') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid PayG secret header.',
            ], 401);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: Missing PayG signature or secret header.',
        ], 401);
    }
}
