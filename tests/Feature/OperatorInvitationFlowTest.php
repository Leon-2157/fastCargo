<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Modules\Shared\Models\AdmOperatorInvitation;
use App\Modules\Shared\Models\OpsOperatorProfile;
use App\Modules\Shared\Models\User;
use Database\Seeders\PortSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class OperatorInvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PortSeeder::class);
    }

    /**
     * Create an admin user who has already completed 2FA setup.
     */
    private function createVerifiedAdmin(): User
    {
        $secret = (new Google2FA)->generateSecretKey();

        return User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Compliance Admin',
            'username' => 'compliance-admin-'.Str::lower(Str::random(5)),
            'email' => 'compliance-'.Str::lower(Str::random(5)).'@example.test',
            'password' => Hash::make('SecretAdminPass123!'),
            'user_type' => 'ADMIN_USER',
            'status' => 'ACTIVE',
            'two_factor_secret' => encrypt($secret),
            'two_factor_enabled_at' => now(),
        ]);
    }

    private function landingUrl(string $path): string
    {
        return 'http://'.config('app.domains.landing', 'fastcargo.local').$path;
    }

    private function adminUrl(string $path): string
    {
        return 'http://'.config('app.domains.admin', 'admin.fastcargo.local').$path;
    }

    private function operatorUrl(string $path): string
    {
        return 'http://'.config('app.domains.operator', 'operator.fastcargo.local').$path;
    }

    public function test_full_operator_invitation_lifecycle_from_application_to_registration(): void
    {
        Queue::fake();
        Storage::fake('local');

        $admin = $this->createVerifiedAdmin();

        $applyResponse = $this->withHeader('Sec-Fetch-Site', 'same-origin')->post(
            $this->landingUrl('/apply/operator'),
            [
                'name' => 'Captain Raka Pratama',
                'email' => 'raka.hub@example.test',
                'phone' => '+6281234567890',
                'hub_name' => 'PT Batam Maritime Hub',
                'city' => 'Batam, Indonesia',
            ]
        );

        $applyResponse->assertRedirect();

        $invitation = AdmOperatorInvitation::where('email', 'raka.hub@example.test')->firstOrFail();
        $this->assertSame(AdmOperatorInvitation::STATUS_PENDING_REVIEW, $invitation->status);

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get($this->adminUrl('/invitations'))
            ->assertOk()
            ->assertSee('Captain Raka Pratama')
            ->assertSee('PENDING REVIEW');

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get($this->adminUrl("/invitations/{$invitation->id}"))
            ->assertOk()
            ->assertSee('IDBTM')
            ->assertSee('OPERATOR_INTERNATIONAL');

        $approveResponse = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->post($this->adminUrl("/invitations/{$invitation->id}/approve"), [
                'assigned_hub_id' => 'IDBTM',
                'assigned_role_tier' => 'OPERATOR_INTERNATIONAL',
            ]);

        $approveResponse->assertRedirect();
        $approveResponse->assertSessionHas('success');

        $invitation->refresh();
        $this->assertSame(AdmOperatorInvitation::STATUS_INVITATION_SENT, $invitation->status);
        $this->assertNotEmpty($invitation->invitation_token);
        $this->assertNotNull($invitation->expires_at);
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertFalse($invitation->isExpired());
        $this->assertSame('IDBTM', $invitation->assigned_hub_id);
        $this->assertSame('OPERATOR_INTERNATIONAL', $invitation->assigned_role_tier);
        $this->assertSame($admin->id, $invitation->invited_by_admin_id);

        Queue::assertPushedTimes(SendWhatsAppNotification::class, 1);

        $firstToken = $invitation->invitation_token;

        $resendResponse = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->post($this->adminUrl("/invitations/{$invitation->id}/resend"));

        $resendResponse->assertRedirect();
        $resendResponse->assertSessionHas('success');

        $invitation->refresh();
        $secondToken = $invitation->invitation_token;

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertSame(AdmOperatorInvitation::STATUS_INVITATION_SENT, $invitation->status);
        $this->assertNotNull($invitation->expires_at);
        $this->assertTrue($invitation->expires_at->isFuture());

        Queue::assertPushedTimes(SendWhatsAppNotification::class, 2);
        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job) use ($secondToken): bool {
            return str_contains($job->message, "/register/{$secondToken}");
        });

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get($this->adminUrl('/invitations?status=INVITATION_SENT'))
            ->assertOk()
            ->assertSee('INVITATION SENT')
            ->assertSee($invitation->expires_at->format('d M Y'));

        Auth::guard('admin')->logout();
        $this->flushSession();

        $this->get($this->operatorUrl("/register/{$firstToken}"))->assertStatus(403);

        $this->get($this->operatorUrl("/register/{$secondToken}"))
            ->assertOk()
            ->assertSee('PT Batam Maritime Hub')
            ->assertSee('raka.hub@example.test');

        $registerResponse = $this->withHeader('Sec-Fetch-Site', 'same-origin')->post(
            $this->operatorUrl("/register/{$secondToken}"),
            [
                'password' => 'OperatorPass123!',
                'password_confirmation' => 'OperatorPass123!',
                'nik' => '2171012345670001',
                'ktp_file' => UploadedFile::fake()->image('ktp-raka.png'),
                'bank_name' => 'BCA',
                'bank_account_number' => '8877665544',
                'bank_account_name' => 'Captain Raka Pratama',
            ]
        );

        $registerResponse->assertRedirect(route('operator.2fa.setup'));

        $operatorUser = User::where('email', 'raka.hub@example.test')->firstOrFail();
        $this->assertSame('OPERATOR_USER', $operatorUser->user_type);
        $this->assertSame('ACTIVE', $operatorUser->status);
        $this->assertAuthenticatedAs($operatorUser, 'operator');

        $profile = OpsOperatorProfile::where('user_id', $operatorUser->id)->firstOrFail();
        $this->assertSame('IDBTM', $profile->assigned_hub_id);
        $this->assertSame('OPERATOR_INTERNATIONAL', $profile->role_tier);

        $invitation->refresh();
        $this->assertSame(AdmOperatorInvitation::STATUS_REGISTERED, $invitation->status);

        Auth::guard('operator')->logout();
        $this->flushSession();

        $this->get($this->operatorUrl("/register/{$secondToken}"))->assertStatus(403);

        $lockedApprove = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->post($this->adminUrl("/invitations/{$invitation->id}/approve"));

        $lockedApprove->assertSessionHas('warning');
        $invitation->refresh();
        $this->assertSame(AdmOperatorInvitation::STATUS_REGISTERED, $invitation->status);
        $this->assertSame($secondToken, $invitation->invitation_token);
    }

    public function test_expired_and_rejected_invitations_block_registration_and_store_rejection_reason(): void
    {
        Queue::fake();

        $admin = $this->createVerifiedAdmin();

        $expiredInvitation = AdmOperatorInvitation::create([
            'id' => Str::uuid()->toString(),
            'invitation_token' => 'expired-token-'.Str::random(26),
            'name' => 'Expired Candidate',
            'email' => 'expired.op@example.test',
            'phone' => '+628111222333',
            'hub_name' => 'Surabaya Depot',
            'city' => 'Surabaya',
            'status' => AdmOperatorInvitation::STATUS_INVITATION_SENT,
            'assigned_hub_id' => 'IDJKT',
            'assigned_role_tier' => 'OPERATOR_DOMESTIC',
            'expires_at' => now()->subMinute(),
        ]);

        $this->get($this->operatorUrl("/register/{$expiredInvitation->invitation_token}"))
            ->assertStatus(403);

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get($this->adminUrl("/invitations/{$expiredInvitation->id}"))
            ->assertOk()
            ->assertSee('EXPIRED')
            ->assertSee('Renew Token');

        $pendingForRejection = AdmOperatorInvitation::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Rejected Candidate',
            'email' => 'rejected.op@example.test',
            'phone' => '+628998877665',
            'hub_name' => 'Unverified Yard',
            'city' => 'Medan',
            'status' => AdmOperatorInvitation::STATUS_PENDING_REVIEW,
            'assigned_role_tier' => 'OPERATOR_DOMESTIC',
        ]);

        $rejectResponse = $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->post($this->adminUrl("/invitations/{$pendingForRejection->id}/reject"), [
                'rejection_reason' => 'Incomplete warehouse legal permit',
            ]);

        $rejectResponse->assertRedirect();
        $rejectResponse->assertSessionHas('warning');

        $pendingForRejection->refresh();
        $this->assertSame(AdmOperatorInvitation::STATUS_REJECTED, $pendingForRejection->status);
        $this->assertNull($pendingForRejection->invitation_token);
        $this->assertNull($pendingForRejection->expires_at);
        $this->assertSame($admin->id, $pendingForRejection->invited_by_admin_id);
        $this->assertSame('Incomplete warehouse legal permit', $pendingForRejection->rejection_reason);

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get($this->adminUrl("/invitations/{$pendingForRejection->id}"))
            ->assertOk()
            ->assertSee('REJECTED')
            ->assertSee('Incomplete warehouse legal permit');
    }
}
