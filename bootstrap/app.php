<?php

use App\Http\Middleware\VerifyInternalSecret;
use App\Http\Middleware\VerifyWaWebhookSecret;
use App\Modules\Shared\Middleware\EnsureOperatorIpWhitelist;
use App\Modules\Shared\Middleware\EnsureTwoFactorVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware aliases untuk route groups
        $middleware->alias([
            'operator.vpn' => EnsureOperatorIpWhitelist::class,
            'operator.2fa' => EnsureTwoFactorVerified::class,
            'operator.2fa.verified' => EnsureTwoFactorVerified::class,
            'customer.2fa.verified' => EnsureTwoFactorVerified::class,
            'admin.2fa.verified' => EnsureTwoFactorVerified::class,
            'api.internal.secret' => VerifyInternalSecret::class,
            'api.wa.webhook' => VerifyWaWebhookSecret::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Session domain shared untuk semua subdomain .fastcargo.local
        $middleware->validateCsrfTokens(except: [
            'api/internal/*',
            'api/webhooks/*',
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            $host = $request->getHost();

            if ($host === config('app.domains.admin', 'admin.fastcargo.local') || str_starts_with($host, 'admin.')) {
                return route('admin.login');
            }

            if ($host === config('app.domains.operator', 'operator.fastcargo.local') || str_starts_with($host, 'operator.')) {
                return route('operator.login');
            }

            if ($host === config('app.domains.customer', 'customer.fastcargo.local') || str_starts_with($host, 'customer.')) {
                return route('customer.login');
            }

            return config('app.portal_urls.customer', 'http://customer.fastcargo.local:8080').'/login';
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            $host = $request->getHost();

            if ($host === config('app.domains.admin', 'admin.fastcargo.local') || str_starts_with($host, 'admin.')) {
                return route('admin.dashboard');
            }

            if ($host === config('app.domains.operator', 'operator.fastcargo.local') || str_starts_with($host, 'operator.')) {
                return route('operator.dashboard');
            }

            return route('customer.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
