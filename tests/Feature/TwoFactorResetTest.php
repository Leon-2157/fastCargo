<?php

namespace Tests\Feature;

use App\Mail\TwoFactorResetOtpMail;
use App\Modules\Shared\Models\TwoFactorResetToken;
use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([]);
    }

    private function createCustomerUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'id' => fake()->uuid(),
            'name' => 'Budi Customer',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'password' => Hash::make('Secret123!'),
            'user_type' => 'RETAIL_CUSTOMER',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_enabled_at' => now(),
        ], $attributes));
    }

    private function createAdminUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'id' => fake()->uuid(),
            'name' => 'Admin Boss',
            'email' => 'admin@fastcargo.test',
            'username' => 'adminboss',
            'password' => Hash::make('AdminPass123!'),
            'user_type' => 'ADMIN_USER',
            'status' => 'ACTIVE',
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_enabled_at' => now(),
        ], $attributes));
    }

    public function test_customer_can_request_2fa_reset_via_email(): void
    {
        Mail::fake();

        $user = $this->createCustomerUser();

        $response = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->actingAs($user, 'customer')
            ->post('http://customer.fastcargo.local/two-factor/reset/send', [
                'channel' => 'EMAIL',
            ]);

        $response->assertRedirect(route('customer.2fa.reset.verify.form', [], false));
        $this->assertDatabaseHas('two_factor_reset_tokens', [
            'user_id' => $user->id,
            'email' => $user->email,
            'channel' => 'EMAIL',
        ]);

        Mail::assertSent(TwoFactorResetOtpMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_customer_can_verify_otp_and_reset_2fa(): void
    {
        $user = $this->createCustomerUser();

        // Invalidate older and create token
        $code = '654321';
        TwoFactorResetToken::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'channel' => 'EMAIL',
            'token_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
            'used_at' => null,
        ]);

        $response = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->actingAs($user, 'customer')
            ->post('http://customer.fastcargo.local/two-factor/reset/verify', [
                'code' => $code,
            ]);

        $response->assertRedirect(route('customer.2fa.setup', [], false));

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_enabled_at);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_invalid_otp_is_rejected(): void
    {
        $user = $this->createCustomerUser();

        TwoFactorResetToken::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'channel' => 'EMAIL',
            'token_hash' => hash('sha256', '111111'),
            'expires_at' => now()->addMinutes(10),
            'used_at' => null,
        ]);

        $response = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->actingAs($user, 'customer')
            ->post('http://customer.fastcargo.local/two-factor/reset/verify', [
                'code' => '999999',
            ]);

        $response->assertSessionHasErrors('code');

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    public function test_artisan_command_resets_user_2fa(): void
    {
        $user = $this->createCustomerUser(['email' => 'emergency@example.com']);

        $this->artisan('2fa:reset emergency@example.com')
            ->expectsOutputToContain('has been successfully reset')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_enabled_at);
    }

    public function test_admin_can_reset_user_2fa_with_audit_trail(): void
    {
        $admin = $this->createAdminUser();
        $customer = $this->createCustomerUser(['email' => 'client@target.com']);

        $response = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->post("http://admin.fastcargo.local/users/{$customer->id}/reset-2fa");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertNull($customer->two_factor_secret);
        $this->assertNull($customer->two_factor_enabled_at);

        $this->assertDatabaseHas('adm_immutable_audit_trails', [
            'actor_id' => $admin->id,
            'action' => 'USER_2FA_MANUAL_RESET',
            'target_id' => $customer->id,
        ]);
    }
}
