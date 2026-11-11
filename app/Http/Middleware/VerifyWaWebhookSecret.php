<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifikasi webhook secret untuk endpoint internal WA Gateway.
 * Mendukung X-Internal-Secret (internal trigger) dan X-Gateway-Secret (wa-gateway Go forwarder).
 */
class VerifyWaWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $internalSecret = (string) config('app.internal_secret', '');
        $gatewaySecret = (string) config('wagateway.secret', '');

        $providedInternal = (string) $request->header('X-Internal-Secret', '');
        $providedGateway = (string) $request->header('X-Gateway-Secret', '');

        $validInternal = $internalSecret !== '' && hash_equals($internalSecret, $providedInternal);
        $validGateway = $gatewaySecret !== '' && hash_equals($gatewaySecret, $providedGateway);

        if (! $validInternal && ! $validGateway) {
            abort(403, 'Forbidden: Invalid webhook secret.');
        }

        return $next($request);
    }
}
