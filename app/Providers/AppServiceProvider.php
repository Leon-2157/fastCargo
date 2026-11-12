<?php

namespace App\Providers;

use App\Modules\Shared\Services\MailSettingsService;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind User model ke Spatie Permission — gunakan model kita bukan default Laravel
        $this->app->bind(
            User::class,
            \App\Modules\Shared\Models\User::class
        );
    }

    public function boot(): void
    {
        // Apply dynamic SMTP/Mail settings from database
        MailSettingsService::apply();

        // Override model Auth agar semua package (Spatie, dsb) pakai model FastCargo
        config(['auth.providers.users.model' => \App\Modules\Shared\Models\User::class]);

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        if (! $this->app->runningInConsole() && $this->app->environment('local')) {
            $request = $this->app['request'];
            $portalUrl = match ($request->getHost()) {
                config('app.domains.admin') => config('app.portal_urls.admin'),
                config('app.domains.operator') => config('app.portal_urls.operator'),
                config('app.domains.customer') => config('app.portal_urls.customer'),
                config('app.domains.landing') => config('app.portal_urls.landing'),
                default => null,
            };

            if ($portalUrl && $request->getPort() === 80 && ($port = parse_url($portalUrl, PHP_URL_PORT))) {
                $hostWithPort = $request->getHost().':'.$port;
                $request->headers->set('HOST', $hostWithPort);
                $request->server->set('HTTP_HOST', $hostWithPort);
                URL::forceRootUrl($portalUrl);
            }
        }
    }
}
