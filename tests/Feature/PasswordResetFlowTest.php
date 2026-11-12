<?php

namespace Tests\Feature;

use App\Mail\PasswordResetNotificationMail;
use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([]);
    }

    private function createCustomer(array $attributes = []): User
    {
        return User::create(array_merge([
            'id' => fake()->uuid(),
            'name' => 'John Customer',
            'email' => 'john.customer@example.test',
            'phone' => '+6281122334455',
            'password' => Hash::make('OldPassword123!'),
            'user_type' => 'RETAIL_CUSTOMER',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_enabled_at' => now(),
        ], $attributes));
    }

    private function createOperator(array $attributes = []): User
    {
        return User::create(array_merge([
            'id' => fake()->uuid(),
            'name' => 'Agent Terminal',
            'email' => 'agent.ops@fastcargo.test',
            'phone' => '+6289988776655',
            'password' => Hash::make('OldPassword123!'),
            'user_type' => 'OPERATOR_USER',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_enabled_at' => now(),
        ], $attributes));
    }

    public function test_customer_can_view_forgot_password_form(): void
    {
        $response = $this->get('http://customer.fastcargo.local/forgot-password');

        $response->assertStatus(200);
        $response->assertSee('Customer Password Recovery');
        $response->assertSee('Send Password Reset Link');
    }

    public function test_operator_can_view_forgot_password_form(): void
    {
        $response = $this->get('http://operator.fastcargo.local/forgot-password');

        $response->assertStatus(200);
        $response->assertSee('Operator Password Recovery');
        $response->assertSee('Send Password Reset Link');
    }

    public function test_customer_can_request_password_reset_and_receives_notification(): void
    {
        Mail::fake();
        $customer = $this->createCustomer();

        $response = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->from('http://customer.fastcargo.local/forgot-password')
            ->post('http://customer.fastcargo.local/forgot-password', [
                'email' => $customer->email,
            ]);

        $response->assertRedirect('http://customer.fastcargo.local/forgot-password');
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $customer->email,
        ]);

        Mail::assertSent(PasswordResetNotificationMail::class, function ($mail) use ($customer) {
            return $mail->hasTo($customer->email) && str_contains($mail->resetUrl, '/reset-password/');
        });

        $this->assertDatabaseHas('adm_immutable_audit_trails', [
            'actor_id' => $customer->id,
            'action' => 'PASSWORD_RESET_REQUESTED',
        ]);
    }

    public function test_operator_account_is_rejected_on_customer_forgot_password_portal(): void
    {
        Mail::fake();
        $operator = $this->createOperator();

        $response = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->from('http://customer.fastcargo.local/forgot-password')
            ->post('http://customer.fastcargo.local/forgot-password', [
                'email' => $operator->email,
            ]);

        $response->assertRedirect('http://customer.fastcargo.local/forgot-password');
        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $operator->email,
        ]);
        Mail::assertNothingSent();
    }

    public function test_operator_can_request_password_reset_on_operator_portal(): void
    {
        Mail::fake();
        $operator = $this->createOperator();

        $response = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->from('http://operator.fastcargo.local/forgot-password')
            ->post('http://operator.fastcargo.local/forgot-password', [
                'email' => $operator->email,
            ]);

        $response->assertRedirect('http://operator.fastcargo.local/forgot-password');
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $operator->email,
        ]);
        Mail::assertSent(PasswordResetNotificationMail::class);
    }

    public function test_customer_can_reset_password_with_valid_token_and_2fa_remains_enforced_on_login(): void
    {
        $customer = $this->createCustomer(['email' => 'alice@customer.test']);
        $rawToken = 'sample-valid-test-token-64-characters-long-1234567890abcdef1234567890';
        $tokenHash = hash('sha256', $rawToken);

        DB::table('password_reset_tokens')->insert([
            'email' => $customer->email,
            'token' => $tokenHash,
            'created_at' => now(),
        ]);

        // View reset form
        $viewResponse = $this->get("http://customer.fastcargo.local/reset-password/{$rawToken}?email={$customer->email}");
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Set New Password');

        // Submit new password
        $submitResponse = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://customer.fastcargo.local/reset-password', [
                'token' => $rawToken,
                'email' => $customer->email,
                'password' => 'NewSecurePassword88!',
                'password_confirmation' => 'NewSecurePassword88!',
            ]);

        $submitResponse->assertRedirect('http://customer.fastcargo.local/login');
        $submitResponse->assertSessionHas('status');

        // Verify password hash updated
        $customer->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword88!', $customer->password));

        // Token should now be invalidated
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $customer->email,
        ]);

        // Attempt login with new password: must succeed credential check and redirect to 2FA challenge
        $loginResponse = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://customer.fastcargo.local/login', [
                'email' => $customer->email,
                'password' => 'NewSecurePassword88!',
            ]);

        $loginResponse->assertRedirect(route('customer.2fa.challenge'));
    }

    public function test_expired_or_invalid_reset_token_is_rejected(): void
    {
        $customer = $this->createCustomer(['email' => 'bob@customer.test']);
        $rawToken = 'sample-expired-token-1234567890abcdef12345678901234567890abcdef123456';
        $tokenHash = hash('sha256', $rawToken);

        // Expired token (70 minutes ago)
        DB::table('password_reset_tokens')->insert([
            'email' => $customer->email,
            'token' => $tokenHash,
            'created_at' => now()->subMinutes(70),
        ]);

        $response = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->from('http://customer.fastcargo.local/reset-password')
            ->post('http://customer.fastcargo.local/reset-password', [
                'token' => $rawToken,
                'email' => $customer->email,
                'password' => 'BrandNewPassword123!',
                'password_confirmation' => 'BrandNewPassword123!',
            ]);

        $response->assertSessionHasErrors(['email']);
        $customer->refresh();
        $this->assertTrue(Hash::check('OldPassword123!', $customer->password));
    }
}
