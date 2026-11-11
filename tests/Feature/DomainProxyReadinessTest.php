<?php

namespace Tests\Feature;

use App\Modules\Shared\Middleware\EnsureTwoFactorVerified;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\AdmOperatorInvitation;
use App\Modules\Shared\Models\AdmSystemSetting;
use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DomainProxyReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test EnsureOperatorIpWhitelist enforces IPv4/IPv6 CIDRs in production and respects env toggle.
     */
    public function test_operator_whitelist_allows_ipv4_and_ipv6_cidrs_and_honors_toggle(): void
    {
        AdmSystemSetting::updateOrCreate(
            ['key' => 'operator_vpn_whitelist'],
            ['value' => json_encode(['10.0.0.0/8', '2001:db8::/32']), 'type' => 'json']
        );
        Cache::forget('operator_vpn_whitelist');
        config(['app.operator_ip_whitelist_enabled' => true]);

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->withServerVariables(['REMOTE_ADDR' => '10.12.34.56'])
                ->get('http://operator.fastcargo.local/login')
                ->assertStatus(200);

            $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::42'])
                ->get('http://operator.fastcargo.local/login')
                ->assertStatus(200);

            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->get('http://operator.fastcargo.local/login')
                ->assertStatus(403);

            config(['app.operator_ip_whitelist_enabled' => false]);

            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->get('http://operator.fastcargo.local/login')
                ->assertStatus(200);
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    /**
     * Test TrustProxies resolves real forwarded client IP and HTTPS scheme only from trusted proxies.
     */
    public function test_trusted_proxy_resolves_client_ip_and_https_scheme_safely(): void
    {
        AdmSystemSetting::updateOrCreate(
            ['key' => 'operator_vpn_whitelist'],
            ['value' => json_encode(['10.20.0.0/16']), 'type' => 'json']
        );
        Cache::forget('operator_vpn_whitelist');
        config(['app.operator_ip_whitelist_enabled' => true]);

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            // Podman internal proxy (10.89.0.5) forwarding a public client IP must still be blocked
            $this->withServerVariables([
                'REMOTE_ADDR' => '10.89.0.5',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])->get('http://operator.fastcargo.local/login')->assertStatus(403);

            // Podman internal proxy forwarding an allowed VPN IP passes and detects HTTPS
            $this->withServerVariables([
                'REMOTE_ADDR' => '10.89.0.5',
                'HTTP_X_FORWARDED_FOR' => '10.20.1.10',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])->get('http://operator.fastcargo.local/login')->assertStatus(200);

            $this->assertTrue(request()->isSecure());

            // Untrusted direct external IP cannot spoof X-Forwarded-Proto
            $this->withServerVariables([
                'REMOTE_ADDR' => '198.51.100.22',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])->get('http://fastcargo.local/')->assertStatus(200);

            $this->assertFalse(request()->isSecure());
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    /**
     * Test OperatorInvitationController approve and resend use configured portal URL.
     */
    public function test_operator_invitation_approve_and_resend_use_configured_portal_domain(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $admin = User::where('email', 'admin@fastcargo.internal')->firstOrFail();
        $invitation = AdmOperatorInvitation::create([
            'email' => 'new-hub@leonproject.my.id',
            'phone' => '+6281234567890',
            'name' => 'Batam Logistics Operator',
            'hub_name' => 'PT Batam Samudera Hub',
            'city' => 'Batam, Indonesia',
            'status' => AdmOperatorInvitation::STATUS_PENDING_REVIEW,
        ]);

        config(['app.portal_urls.operator' => 'https://operator.leonproject.my.id']);

        $approveResponse = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->post("http://admin.fastcargo.local/invitations/{$invitation->id}/approve");

        $approveResponse->assertRedirect();
        $approveMessage = (string) session('success');
        $this->assertStringContainsString('https://operator.leonproject.my.id/register/', $approveMessage);
        $this->assertStringNotContainsString('fastcargo.local:8080', $approveMessage);

        $resendResponse = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->post("http://admin.fastcargo.local/invitations/{$invitation->id}/resend");

        $resendResponse->assertRedirect();
        $resendMessage = (string) session('success');
        $this->assertStringContainsString('https://operator.leonproject.my.id/register/', $resendMessage);
        $this->assertStringNotContainsString('fastcargo.local:8080', $resendMessage);
    }

    /**
     * Test Admin settings and PDF invoice views render configured domain and billing contact.
     */
    public function test_admin_settings_and_invoice_pdf_render_configured_domain_and_billing_email(): void
    {
        $admin = User::where('email', 'admin@fastcargo.internal')->firstOrFail();
        $invoice = AdmInvoice::with(['company', 'booking.route'])->firstOrFail();

        config([
            'app.domains.operator' => 'operator.leonproject.my.id',
            'app.billing_email' => 'billing@leonproject.my.id',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get('http://admin.fastcargo.local/settings');

        $response->assertStatus(200);
        $response->assertSee('operator.leonproject.my.id');
        $response->assertDontSee('operator.fastcargo.local');

        $pdfHtml = view('customer.invoice.pdf', ['invoice' => $invoice])->render();
        $this->assertStringContainsString('billing@leonproject.my.id', $pdfHtml);
        $this->assertStringNotContainsString('billing@fastcargo.com', $pdfHtml);
    }

    /**
     * Test EnsureTwoFactorVerified resolves portal prefixes for configured custom domains.
     */
    public function test_ensure_two_factor_verified_matches_configured_custom_domains(): void
    {
        config([
            'app.domains.admin' => 'admin.leonproject.my.id',
            'app.domains.operator' => 'operator.leonproject.my.id',
        ]);

        $middleware = new EnsureTwoFactorVerified;

        $adminRequest = Request::create('https://admin.leonproject.my.id/dashboard', 'GET');
        $adminRequest->setLaravelSession(app('session')->driver());
        $adminResponse = $middleware->handle($adminRequest, fn (): Response => new Response('OK'));
        $this->assertSame(route('admin.2fa.challenge'), $adminResponse->headers->get('Location'));

        $operatorRequest = Request::create('https://operator.leonproject.my.id/', 'GET');
        $operatorRequest->setLaravelSession(app('session')->driver());
        $operatorResponse = $middleware->handle($operatorRequest, fn (): Response => new Response('OK'));
        $this->assertSame(route('operator.2fa.challenge'), $operatorResponse->headers->get('Location'));
    }
}
