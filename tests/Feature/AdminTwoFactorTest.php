<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([]);
    }

    private function createAdmin(array $attributes = []): User
    {
        return User::create(array_merge([
            'id' => fake()->uuid(),
            'name' => 'Test Admin',
            'username' => 'admin-test',
            'email' => 'admin@example.test',
            'password' => Hash::make('CorrectPassword123!'),
            'user_type' => 'ADMIN_USER',
            'status' => 'ACTIVE',
        ], $attributes));
    }

    public function test_admin_login_requires_setup_or_challenge_before_dashboard(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeader('Sec-Fetch-Site', 'same-origin')->post('http://admin.fastcargo.local/login', [
            'login' => $admin->username,
            'password' => 'CorrectPassword123!',
        ]);

        $response->assertRedirect(route('admin.2fa.setup', [], false));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->get('http://admin.fastcargo.local/dashboard')->assertRedirect(route('admin.2fa.setup', [], false));
    }

    public function test_admin_dashboard_requires_valid_totp(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $admin = $this->createAdmin([
            'two_factor_secret' => encrypt($secret),
            'two_factor_enabled_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => false])
            ->get('http://admin.fastcargo.local/dashboard')
            ->assertRedirect(route('admin.2fa.challenge', [], false));

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->withHeader('Sec-Fetch-Site', 'same-origin')->actingAs($admin, 'admin')
            ->post('http://admin.fastcargo.local/two-factor/verify', ['code' => $code])
            ->assertRedirect(route('admin.dashboard', [], false));

        $this->assertTrue(session('admin_two_factor_verified'));
    }

    public function test_unauthenticated_admin_routes_redirect_to_admin_login(): void
    {
        $this->get('http://admin.fastcargo.local/dashboard')
            ->assertRedirect(route('admin.login'));

        $this->get('http://admin.fastcargo.local/two-factor/setup')
            ->assertRedirect(route('admin.login'));
    }
}
