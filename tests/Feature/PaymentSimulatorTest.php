<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentSimulatorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'id' => fake()->uuid(),
            'name' => 'Security Admin',
            'email' => 'admin.sec@fastcargo.test',
            'username' => 'secadmin',
            'password' => Hash::make('AdminPass123!'),
            'user_type' => 'ADMIN_USER',
            'status' => 'ACTIVE',
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_enabled_at' => now(),
        ]);
    }

    private function adminSession(): array
    {
        return [
            'admin_two_factor_verified' => true,
        ];
    }

    public function test_admin_can_view_webhook_simulator_console(): void
    {
        $response = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get('http://admin.fastcargo.local/webhook-simulator');

        $response->assertStatus(200);
        $response->assertSee('Payment Webhook Simulator');
        $response->assertSee('Generate Sandbox Test Invoice');
        $response->assertSee('SETTLEMENT (00)');
    }

    public function test_admin_can_generate_sandbox_transaction_in_one_click(): void
    {
        $response = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/webhook-simulator/generate');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Sandbox test transaction generated successfully.',
        ]);

        $data = $response->json('transaction');
        $this->assertNotNull($data);
        $this->assertStringStartsWith('SANDBOX-DANA-', $data['transaction_ref']);
        $this->assertStringStartsWith('SANDBOX-INV-', $data['invoice_number']);

        $this->assertDatabaseHas('payment_transactions', [
            'transaction_ref' => $data['transaction_ref'],
            'payment_status' => 'PENDING',
        ]);

        $this->assertDatabaseHas('adm_immutable_audit_trails', [
            'actor_id' => $this->admin->id,
            'action' => 'SIMULATOR_SANDBOX_TX_CREATED',
        ]);
    }

    public function test_admin_can_fire_settlement_webhook_and_fund_escrow(): void
    {
        // First generate a sandbox transaction
        $genResponse = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/webhook-simulator/generate');

        $ref = $genResponse->json('transaction.transaction_ref');
        $amount = $genResponse->json('transaction.amount');

        // Fire simulated SETTLEMENT webhook
        $fireResponse = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/webhook-simulator/fire', [
                'partnerReferenceNo' => $ref,
                'scenario' => 'SETTLEMENT',
                'amount' => $amount,
            ]);

        $fireResponse->assertStatus(200);
        $fireResponse->assertJson([
            'success' => true,
            'http_status' => 200,
            'scenario' => 'SETTLEMENT',
            'ledger_state' => [
                'transaction_status' => 'SETTLEMENT',
                'invoice_status' => 'PAID_SETTLED',
                'escrow_status' => 'HELD_IN_ESCROW',
            ],
        ]);

        $this->assertDatabaseHas('payment_transactions', [
            'transaction_ref' => $ref,
            'payment_status' => 'SETTLEMENT',
        ]);

        $this->assertDatabaseHas('adm_immutable_audit_trails', [
            'actor_id' => $this->admin->id,
            'action' => 'WEBHOOK_SIMULATOR_DISPATCHED',
        ]);
    }

    public function test_admin_can_fire_expired_scenario_webhook(): void
    {
        $genResponse = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/webhook-simulator/generate');

        $ref = $genResponse->json('transaction.transaction_ref');

        $fireResponse = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post('http://admin.fastcargo.local/webhook-simulator/fire', [
                'partnerReferenceNo' => $ref,
                'scenario' => 'EXPIRED',
            ]);

        $fireResponse->assertStatus(200);
        $fireResponse->assertJson([
            'success' => true,
            'http_status' => 200,
            'scenario' => 'EXPIRED',
            'ledger_state' => [
                'transaction_status' => 'EXPIRED',
            ],
        ]);

        $this->assertDatabaseHas('payment_transactions', [
            'transaction_ref' => $ref,
            'payment_status' => 'EXPIRED',
        ]);
    }
}
