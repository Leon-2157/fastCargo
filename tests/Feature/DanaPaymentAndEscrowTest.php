<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\AdmRevenueSplit;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsOperatorProfile;
use App\Modules\Shared\Models\Port;
use App\Modules\Shared\Models\Route;
use App\Modules\Shared\Models\User;
use App\Modules\Shared\Services\DanaPaymentGatewayService;
use App\Modules\Shared\Services\EscrowSettlementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DanaPaymentAndEscrowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $customer;

    protected User $operator;

    protected Company $company;

    protected OpsCargoBooking $booking;

    protected AdmInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->customer = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Dana Customer',
            'email' => 'customer_dana_'.Str::random(6).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'CUSTOMER',
            'phone' => '628123456789',
        ]);

        $this->company = Company::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->customer->id,
            'company_name' => 'PT Dana Test Logistics',
            'company_address' => 'Jakarta, Indonesia',
            'npwp' => '01.234.567.8-012.000',
            'nib' => '1234567890123',
        ]);

        $this->operator = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Dana Operator',
            'email' => 'operator_dana_'.Str::random(6).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'OPERATOR',
        ]);

        $origin = Port::firstOrCreate(
            ['id' => 'SGSIN'],
            ['name' => 'Port of Singapore', 'city' => 'Singapore', 'country_code' => 'SG', 'port_type' => 'SEA', 'latitude' => 1.2654, 'longitude' => 103.8198]
        );

        $dest = Port::firstOrCreate(
            ['id' => 'IDTPP'],
            ['name' => 'Tanjung Priok', 'city' => 'Jakarta', 'country_code' => 'ID', 'port_type' => 'SEA', 'latitude' => -6.1025, 'longitude' => 106.8825]
        );

        $profile = OpsOperatorProfile::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->operator->id,
            'employee_id' => 'EMP-DANA-01',
            'assigned_hub_id' => $dest->id,
            'is_on_duty' => true,
        ]);

        $route = Route::firstOrCreate(
            ['origin_port_id' => $origin->id, 'destination_port_id' => $dest->id],
            ['id' => Str::uuid()->toString(), 'transport_mode' => 'SEA', 'transit_time_days' => 2, 'base_rate' => 10000000]
        );

        $this->booking = OpsCargoBooking::create([
            'id' => Str::uuid()->toString(),
            'tracking_number' => 'RPN-20261112-DANA',
            'shipper_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'route_id' => $route->id,
            'booking_type' => 'RETAIL_LCL',
            'cargo_type' => 'GENERAL_CARGO',
            'total_weight_kg' => 100,
            'total_volume_cbm' => 1,
            'declared_goods_value' => 10000000,
            'currency' => 'IDR',
            'cargo_status' => 'BOOKED',
        ]);

        $this->invoice = AdmInvoice::create([
            'id' => Str::uuid()->toString(),
            'invoice_number' => 'INV-20261112-DANA',
            'company_id' => $this->company->id,
            'cargo_booking_id' => $this->booking->id,
            'total_invoice_amount' => 10000000,
            'payment_status' => 'UNPAID',
            'due_date' => now()->addDays(7),
        ]);
    }

    public function test_dana_service_generates_valid_qris_svg_and_pending_transaction(): void
    {
        $service = app(DanaPaymentGatewayService::class);
        $qris = $service->generateQrisCheckout($this->invoice, $this->customer);

        $this->assertArrayHasKey('transaction_ref', $qris);
        $this->assertArrayHasKey('qr_svg', $qris);
        $this->assertStringContainsString('<svg', $qris['qr_svg']);
        $this->assertEquals(10000000, $qris['amount']);

        $this->assertDatabaseHas('payment_transactions', [
            'invoice_id' => $this->invoice->id,
            'payment_gateway' => 'DANA',
            'payment_status' => 'PENDING',
            'transaction_ref' => $qris['transaction_ref'],
        ]);
    }

    public function test_dana_webhook_settlement_updates_invoice_and_funds_escrow(): void
    {
        $service = app(DanaPaymentGatewayService::class);
        $qris = $service->generateQrisCheckout($this->invoice, $this->customer);

        $response = $this->postJson('http://fastcargo.local/api/webhooks/dana', [
            'originalPartnerReferenceNo' => $qris['transaction_ref'],
            'latestTransactionStatus' => '00',
            'amount' => [
                'value' => '10000000.00',
                'currency' => 'IDR',
            ],
            'additionalInfo' => [
                'rrn' => '998877665544',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'responseCode' => '2002500',
            'responseMessage' => 'Successful',
        ]);

        $this->invoice->refresh();
        $this->assertEquals('PAID_SETTLED', $this->invoice->payment_status);
        $this->assertNotNull($this->invoice->paid_at);

        // Verify Escrow Ledger is funded with 30/40/30 breakdown
        $escrow = AdmEscrowLedger::where('cargo_booking_id', $this->booking->id)->first();
        $this->assertNotNull($escrow);
        $this->assertEquals(10000000, (float) $escrow->total_paid_amount);
        $this->assertEquals(3000000, (float) $escrow->milestone_1_pickup_amount);
        $this->assertEquals(4000000, (float) $escrow->milestone_2_sailing_amount);
        $this->assertEquals(3000000, (float) $escrow->milestone_3_delivered_amount);
        $this->assertEquals(AdmEscrowLedger::STATUS_HELD_IN_ESCROW, $escrow->escrow_status);
    }

    public function test_escrow_milestone_releases_and_revenue_split(): void
    {
        $service = app(DanaPaymentGatewayService::class);
        $escrowService = app(EscrowSettlementService::class);

        $qris = $service->generateQrisCheckout($this->invoice, $this->customer);
        $service->processSettlement($qris['transaction_ref']);

        // Milestone 1: GATE_IN (30%)
        $escrow = $escrowService->checkAndReleaseMilestone($this->booking, 'GATE_IN');
        $this->assertNotNull($escrow->milestone_1_released_at);
        $this->assertEquals(AdmEscrowLedger::STATUS_PARTIALLY_RELEASED, $escrow->escrow_status);

        // Milestone 2: SAILING (40%)
        $escrow = $escrowService->checkAndReleaseMilestone($this->booking, 'SAILING');
        $this->assertNotNull($escrow->milestone_2_released_at);
        $this->assertEquals(AdmEscrowLedger::STATUS_PARTIALLY_RELEASED, $escrow->escrow_status);

        // Milestone 3: DELIVERED (30%) -> triggers Revenue Split
        $escrow = $escrowService->checkAndReleaseMilestone($this->booking, 'DELIVERED');
        $this->assertNotNull($escrow->milestone_3_released_at);
        $this->assertEquals(AdmEscrowLedger::STATUS_FULLY_SETTLED, $escrow->escrow_status);

        // Verify Revenue Split record
        $split = AdmRevenueSplit::where('cargo_booking_id', $this->booking->id)->first();
        $this->assertNotNull($split);
        $this->assertEquals(1200000, (float) $split->platform_fee_amount); // 12%
        $this->assertEquals(1000000, (float) $split->tax_and_port_dues); // 10%
        $this->assertEquals(7800000, (float) $split->operator_share_amount); // 78%
    }

    public function test_customer_invoice_show_displays_qris_and_simulation_works(): void
    {
        $response = $this->actingAs($this->customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->get("http://customer.fastcargo.local/invoices/{$this->invoice->id}");

        $response->assertStatus(200);
        $response->assertSee('Dana Business QRIS');
        $response->assertSee('FASTCARGO ASEAN LOGISTICS');
        $response->assertSee('3-Stage Escrow Protection');

        // Execute payment simulation via POST
        $payResponse = $this->actingAs($this->customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post("http://customer.fastcargo.local/invoices/{$this->invoice->id}/pay");

        $payResponse->assertRedirect("http://customer.fastcargo.local/invoices/{$this->invoice->id}");

        $this->invoice->refresh();
        $this->assertEquals('PAID_SETTLED', $this->invoice->payment_status);
    }

    public function test_operator_epod_submission_finalizes_delivery_and_releases_escrow(): void
    {
        // First fund escrow
        $service = app(DanaPaymentGatewayService::class);
        $qris = $service->generateQrisCheckout($this->invoice, $this->customer);
        $service->processSettlement($qris['transaction_ref']);

        // Post ePOD
        $response = $this->actingAs($this->operator, 'operator')
            ->withSession(['two_factor_verified' => true])
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post("http://operator.fastcargo.local/epod/{$this->booking->id}", [
                'recipient_name' => 'Ahmad Suhendra',
                'recipient_phone' => '6281987654321',
                'latitude' => -6.1025,
                'longitude' => 106.8825,
                'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            ]);

        $response->assertRedirect("http://operator.fastcargo.local/bookings/{$this->booking->id}");

        $this->booking->refresh();
        $this->assertEquals('DELIVERED', $this->booking->cargo_status);

        $this->assertDatabaseHas('ops_epods', [
            'cargo_booking_id' => $this->booking->id,
            'recipient_name' => 'Ahmad Suhendra',
            'recipient_phone' => '6281987654321',
        ]);

        $escrow = AdmEscrowLedger::where('cargo_booking_id', $this->booking->id)->first();
        $this->assertEquals(AdmEscrowLedger::STATUS_FULLY_SETTLED, $escrow->escrow_status);
    }
}
