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

        // 2. Check X-PayG-Signature HMAC
        $signatureHeader = (string) $request->header('X-PayG-Signature', '');
        $sharedSecret = (string) (AdmSystemSetting::get('payg_secret')
            ?: config('payg.secret', 'fastcargo-payg-secret-2026'));

        if (empty($signatureHeader) || empty($sharedSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Missing PayG signature header.',
            ], 401);
        }

        // Strip prefix "sha256=" if present
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
}
