<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifikasi X-Internal-Secret header untuk internal API routes.
 * Mencegah webhook endpoint diakses publik.
 */
class VerifyInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('app.internal_secret', '');
        $provided = (string) $request->header('X-Internal-Secret', '');

        if ($secret === '' || ! hash_equals($secret, $provided)) {
            abort(403, 'Forbidden: Invalid internal secret.');
        }

        return $next($request);
    }
}
