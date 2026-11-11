<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

    }

    public function test_registration_sends_verification_and_keeps_customer_unverified(): void
    {
        Notification::fake();

        $response = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://customer.fastcargo.local/register', [
                'name' => 'Test Customer',
                'email' => 'new-customer@example.test',
                'phone' => '+628123456789',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
            ]);

        $user = User::where('email', 'new-customer@example.test')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_email_verification_marks_customer_verified(): void
    {
        Event::fake([Verified::class]);
        $user = User::create([
            'id' => fake()->uuid(),
            'name' => 'Test Customer',
            'email' => 'signed-customer@example.test',
            'password' => 'Password123!',
            'user_type' => 'RETAIL_CUSTOMER',
            'status' => 'ACTIVE',
            'email_verified_at' => null,
        ]);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->actingAs($user, 'customer')->get($url);

        $response->assertRedirect(route('customer.login', [], false));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_login_blocks_unverified_customer(): void
    {
        $user = User::create([
            'id' => fake()->uuid(),
            'name' => 'Unverified Customer',
            'email' => 'unverified@example.test',
            'password' => 'password',
            'user_type' => 'RETAIL_CUSTOMER',
            'status' => 'ACTIVE',
            'email_verified_at' => null,
        ]);

        $this->withHeader('Sec-Fetch-Site', 'same-origin')->post('http://customer.fastcargo.local/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('verification.notice', [], false));

        $this->assertAuthenticatedAs($user, 'customer');
    }

    public function test_unauthenticated_email_verify_redirects_to_customer_login(): void
    {
        $this->get('http://customer.fastcargo.local/email/verify')
            ->assertRedirect(route('customer.login'));
    }
}
