<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\Port;
use App\Modules\Shared\Models\Route;
use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\PayGPaymentGatewayService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayGPaymentGatewayTest extends TestCase
{
    use DatabaseTransactions;

    protected User $customer;

    protected Company $company;

    protected OpsCargoBooking $booking;

    protected AdmInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'PayG Test Customer',
            'email' => 'customer_payg_'.Str::random(6).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'CUSTOMER',
            'phone' => '628123456789',
        ]);

        $this->company = Company::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->customer->id,
            'company_name' => 'PT PayG Test Cargo',
            'company_address' => 'Surabaya, Indonesia',
            'npwp' => '01.234.567.8-012.000',
            'nib' => '1234567890123',
        ]);

        $origin = Port::firstOrCreate(
            ['id' => 'IDSUB'],
            ['name' => 'Tanjung Perak', 'city' => 'Surabaya', 'country_code' => 'ID', 'port_type' => 'SEA', 'latitude' => -7.1983, 'longitude' => 112.7323]
        );

        $dest = Port::firstOrCreate(
            ['id' => 'MYPKG'],
            ['name' => 'Port Klang', 'city' => 'Klang', 'country_code' => 'MY', 'port_type' => 'SEA', 'latitude' => 3.0000, 'longitude' => 101.4000]
        );

        $route = Route::firstOrCreate(
            ['origin_port_id' => $origin->id, 'destination_port_id' => $dest->id],
            ['id' => Str::uuid()->toString(), 'transport_mode' => 'SEA', 'transit_time_days' => 3, 'base_rate' => 15000000]
        );

        $this->booking = OpsCargoBooking::create([
            'id' => Str::uuid()->toString(),
            'tracking_number' => 'RPN-PAYG-'.Str::random(6),
            'shipper_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'route_id' => $route->id,
            'booking_type' => 'RETAIL_LCL',
            'cargo_type' => 'GENERAL_CARGO',
            'total_weight_kg' => 200,
            'total_volume_cbm' => 2,
            'declared_goods_value' => 15000000,
            'currency' => 'IDR',
            'cargo_status' => 'BOOKED',
        ]);

        $this->invoice = AdmInvoice::create([
            'id' => Str::uuid()->toString(),
            'invoice_number' => 'INV-PAYG-'.Str::random(6),
            'company_id' => $this->company->id,
            'cargo_booking_id' => $this->booking->id,
            'total_invoice_amount' => 15000000,
            'payment_status' => 'UNPAID',
            'due_date' => now()->addDays(7),
        ]);
    }

    public function test_payg_service_generates_checkout_and_pending_transaction(): void
    {
        $service = app(PayGPaymentGatewayService::class);
        $checkout = $service->generateCheckout($this->invoice, $this->customer);

        $this->assertArrayHasKey('transaction_ref', $checkout);
        $this->assertArrayHasKey('qr_string', $checkout);
        $this->assertArrayHasKey('qr_svg', $checkout);
        $this->assertStringContainsString('<svg', $checkout['qr_svg']);
        $this->assertEquals(15000000, $checkout['amount']);

        $this->assertDatabaseHas('payment_transactions', [
            'invoice_id' => $this->invoice->id,
            'payment_status' => 'PENDING',
            'transaction_ref' => $checkout['transaction_ref'],
        ]);
    }

    public function test_payg_settlement_updates_invoice_and_funds_escrow(): void
    {
        $service = app(PayGPaymentGatewayService::class);
        $checkout = $service->generateCheckout($this->invoice, $this->customer);

        $result = $service->processSettlement($checkout['transaction_ref'], [
            'engine' => 'simulator',
            'test_run' => true,
        ]);

        $this->assertTrue($result['success']);

        $this->invoice->refresh();
        $this->assertEquals('PAID_SETTLED', $this->invoice->payment_status);
        $this->assertNotNull($this->invoice->paid_at);

        // Verify Escrow Ledger funded
        $escrow = AdmEscrowLedger::where('cargo_booking_id', $this->booking->id)->first();
        $this->assertNotNull($escrow);
        $this->assertEquals(15000000, (float) $escrow->total_paid_amount);
        $this->assertEquals(4500000, (float) $escrow->milestone_1_pickup_amount); // 30%
        $this->assertEquals(6000000, (float) $escrow->milestone_2_sailing_amount); // 40%
        $this->assertEquals(4500000, (float) $escrow->milestone_3_delivered_amount); // 30%
        $this->assertEquals(AdmEscrowLedger::STATUS_HELD_IN_ESCROW, $escrow->escrow_status);
    }

    public function test_internal_payg_webhook_verifies_hmac_sha256_and_settles(): void
    {
        $service = app(PayGPaymentGatewayService::class);
        $checkout = $service->generateCheckout($this->invoice, $this->customer);

        $payload = [
            'event' => 'payment.settled',
            'transaction_ref' => $checkout['transaction_ref'],
            'status' => 'SETTLEMENT',
            'amount' => 15000000,
            'currency' => 'IDR',
        ];

        $content = json_encode($payload);
        $secret = config('payg.secret', 'fastcargo-payg-secret-2026');
        $signature = hash_hmac('sha256', $content, $secret);

        $response = $this->call(
            'POST',
            'http://fastcargo.local/api/internal/payg/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYG_SIGNATURE' => 'sha256='.$signature,
                'HTTP_X_PAYG_TIMESTAMP' => now()->toIso8601String(),
            ],
            $content
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'transaction_ref' => $checkout['transaction_ref'],
            'settlement_status' => 'PAID_SETTLED',
        ]);

        $this->invoice->refresh();
        $this->assertEquals('PAID_SETTLED', $this->invoice->payment_status);
    }

    public function test_internal_payg_webhook_rejects_invalid_hmac_signature(): void
    {
        $payload = [
            'event' => 'payment.settled',
            'transaction_ref' => 'REF-FORGED-12345',
            'status' => 'SETTLEMENT',
        ];

        $content = json_encode($payload);

        $response = $this->call(
            'POST',
            'http://fastcargo.local/api/internal/payg/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYG_SIGNATURE' => 'sha256=invalid_tampered_signature_99999',
            ],
            $content
        );

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Invalid PayG HMAC signature.',
        ]);
    }

    public function test_internal_payg_webhook_rejects_missing_signature(): void
    {
        $payload = [
            'event' => 'payment.settled',
            'transaction_ref' => 'REF-NO-SIG',
        ];

        $content = json_encode($payload);

        $response = $this->call(
            'POST',
            'http://fastcargo.local/api/internal/payg/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            $content
        );

        $response->assertStatus(401);
    }

    public function test_sobpay_fee_calculation(): void
    {
        $service = app(PayGPaymentGatewayService::class);
        $fees = $service->getSobpayFees('Indonesia', 10000000);

        $this->assertTrue($fees['success']);
        $this->assertNotEmpty($fees['estimates']);
        $this->assertEquals('Indonesia', $fees['country']);
    }

    public function test_internal_payg_webhook_rejects_tampered_payload_content(): void
    {
        $payload = [
            'event' => 'payment.settled',
            'transaction_ref' => 'REF-SECURE-999',
            'status' => 'SETTLEMENT',
            'amount' => 15000000,
        ];

        $content = json_encode($payload);
        $secret = config('payg.secret', 'fastcargo-payg-secret-2026');
        $validSignature = hash_hmac('sha256', $content, $secret);

        // Tamper 1 character in payload content
        $tamperedContent = str_replace('15000000', '15000001', $content);

        $response = $this->call(
            'POST',
            'http://fastcargo.local/api/internal/payg/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYG_SIGNATURE' => 'sha256='.$validSignature,
            ],
            $tamperedContent
        );

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Invalid PayG HMAC signature.',
        ]);
    }

    public function test_internal_payg_webhook_rejects_missing_transaction_ref(): void
    {
        $payload = [
            'event' => 'payment.settled',
            'status' => 'SETTLEMENT',
        ];

        $content = json_encode($payload);
        $secret = config('payg.secret', 'fastcargo-payg-secret-2026');
        $signature = hash_hmac('sha256', $content, $secret);

        $response = $this->call(
            'POST',
            'http://fastcargo.local/api/internal/payg/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYG_SIGNATURE' => 'sha256='.$signature,
            ],
            $content
        );

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Missing transaction_ref parameter.',
        ]);
    }

    public function test_internal_payg_webhook_handles_failed_status_without_settling(): void
    {
        $service = app(PayGPaymentGatewayService::class);
        $checkout = $service->generateCheckout($this->invoice, $this->customer);

        $payload = [
            'transaction_ref' => $checkout['transaction_ref'],
            'status' => 'FAILED',
        ];

        $content = json_encode($payload);
        $secret = config('payg.secret', 'fastcargo-payg-secret-2026');
        $signature = hash_hmac('sha256', $content, $secret);

        $response = $this->call(
            'POST',
            'http://fastcargo.local/api/internal/payg/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYG_SIGNATURE' => 'sha256='.$signature,
            ],
            $content
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'FAILED',
        ]);

        // Invoice MUST remain UNPAID
        $this->invoice->refresh();
        $this->assertEquals('UNPAID', $this->invoice->payment_status);
        $this->assertNull($this->invoice->paid_at);

        // Escrow MUST NOT be funded
        $escrow = AdmEscrowLedger::where('cargo_booking_id', $this->booking->id)->first();
        $this->assertNull($escrow);
    }

    public function test_internal_payg_webhook_handles_expired_status_without_settling(): void
    {
        $service = app(PayGPaymentGatewayService::class);
        $checkout = $service->generateCheckout($this->invoice, $this->customer);

        $payload = [
            'event' => 'payment.expired',
            'transaction_ref' => $checkout['transaction_ref'],
            'status' => 'EXPIRED',
        ];

        $content = json_encode($payload);
        $secret = config('payg.secret', 'fastcargo-payg-secret-2026');
        $signature = hash_hmac('sha256', $content, $secret);

        $response = $this->call(
            'POST',
            'http://fastcargo.local/api/internal/payg/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYG_SIGNATURE' => 'sha256='.$signature,
            ],
            $content
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'EXPIRED',
        ]);

        $this->invoice->refresh();
        $this->assertEquals('UNPAID', $this->invoice->payment_status);
    }

    public function test_internal_payg_webhook_returns_404_for_unknown_transaction(): void
    {
        $payload = [
            'transaction_ref' => 'REF-UNKNOWN-99999',
            'status' => 'FAILED',
        ];

        $content = json_encode($payload);
        $secret = config('payg.secret', 'fastcargo-payg-secret-2026');
        $signature = hash_hmac('sha256', $content, $secret);

        $response = $this->call(
            'POST',
            'http://fastcargo.local/api/internal/payg/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYG_SIGNATURE' => 'sha256='.$signature,
            ],
            $content
        );

        $response->assertStatus(404);
    }

    public function test_simulator_settlement_supports_failed_scenario(): void
    {
        $service = app(PayGPaymentGatewayService::class);
        $checkout = $service->generateCheckout($this->invoice, $this->customer);

        $result = $service->simulateSettlement($checkout['transaction_ref'], 'FAILED');
        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('payment_transactions', [
            'transaction_ref' => $checkout['transaction_ref'],
            'payment_status' => 'FAILED',
        ]);

        $this->invoice->refresh();
        $this->assertEquals('UNPAID', $this->invoice->payment_status);
    }

    public function test_admin_settings_can_update_payg_configuration(): void
    {
        $admin = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Admin PayG',
            'username' => 'admin_payg_'.Str::random(4),
            'email' => 'admin_payg_'.Str::random(4).'@example.com',
            'password' => bcrypt('password123'),
            'user_type' => 'ADMIN_USER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->put('http://admin.fastcargo.local/settings', [
                'payg_active_mode' => 'sgpay',
                'payg_sobpay_username' => 'fastcargotest',
                'payg_sgpay_static_qr' => '00020101021126580014ID.DANA.WWW6304ABCD',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('adm_system_settings', [
            'key' => 'payg_active_mode',
            'value' => 'sgpay',
        ]);
        $this->assertDatabaseHas('adm_system_settings', [
            'key' => 'payg_sobpay_username',
            'value' => 'fastcargotest',
        ]);
    }

    public function test_admin_payg_otp_and_fee_ajax_endpoints(): void
    {
        $admin = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Admin PayG Ajax',
            'username' => 'admin_ajax_'.Str::random(4),
            'email' => 'admin_ajax_'.Str::random(4).'@example.com',
            'password' => bcrypt('password123'),
            'user_type' => 'ADMIN_USER',
            'status' => 'ACTIVE',
        ]);

        // 1. Fee calculation endpoint
        $feeResponse = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->getJson('http://admin.fastcargo.local/settings/payg/fees?country=Malaysia&amount=500000');

        $feeResponse->assertStatus(200);
        $feeResponse->assertJson(['success' => true]);

        // 2. OTP request endpoint
        $otpResponse = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->postJson('http://admin.fastcargo.local/settings/payg/otp/request', [
                'provider' => 'shopee',
                'phone' => '081234567890',
            ]);

        $otpResponse->assertStatus(200);

        // 3. Disconnect endpoint
        $dcResponse = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->postJson('http://admin.fastcargo.local/settings/payg/disconnect', [
                'provider' => 'shopee',
            ]);

        $dcResponse->assertStatus(200);
    }

    public function test_emvco_crc16_calculation_matches_aspi_standard(): void
    {
        $sample = '00020101021226580014ID.DANA.WWW011893600911002233445551440014ID.LINKAJA.WWW01189360091100223344555204541153033605802ID5925FASTCARGO ASEAN LOGISTICS6007JAKARTA6304';
        $crc = PayGPaymentGatewayService::calculateCrc16Ccitt($sample);

        $this->assertEquals(4, strlen($crc));
        $this->assertEquals('3699', $crc);
    }
}
