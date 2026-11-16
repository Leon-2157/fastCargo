<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\AdmCorporateCredit;
use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsIncidentEscalation;
use App\Modules\Shared\Models\OpsOperatorProfile;
use App\Modules\Shared\Models\Port;
use App\Modules\Shared\Models\Route;
use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

    public function test_admin_settings_uses_clean_direct_merchant_payment_gateway(): void
    {
        $response = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get('http://admin.fastcargo.local/settings');

        $response->assertStatus(200);
        $response->assertSee('SGpay Direct');
        $response->assertSee('Merchant Static QR');
        $response->assertSee('Merchant Identity Preview');
        $response->assertSee('Panduan MacroDroid (Rp 0)');
        $response->assertSee('payg_sgpay_static_qr');
        $response->assertSee('payg_webhook_url');
        $response->assertDontSee('Active Gateway Engine');
        $response->assertDontSee('id="tab-simulator"', false);
        $response->assertDontSee('id="tab-sobpay"', false);
        $response->assertDontSee('modalLiveTestSgpay');
    }

    public function test_all_five_admin_detail_views_render_cleanly_without_500_errors(): void
    {
        $customer = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'PT Nusantara Shipping',
            'username' => 'nusantara_corp',
            'email' => 'corp@nusantara.test',
            'password' => Hash::make('Secret123!'),
            'user_type' => 'CORPORATE_USER',
            'status' => 'ACTIVE',
            'kyc_aml_status' => 'PENDING',
        ]);

        $company = Company::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $customer->id,
            'company_name' => 'PT Nusantara Shipping Tbk',
            'npwp' => '01.234.567.8-099.000',
            'nib' => '9120001234567',
            'billing_address' => 'Jl. Sudirman No. 1, Jakarta',
        ]);

        $credit = AdmCorporateCredit::create([
            'id' => Str::uuid()->toString(),
            'company_id' => $company->id,
            'credit_limit_amount' => 50000000,
            'used_credit_amount' => 10000000,
            'top_days' => '30',
            'kyc_status' => 'VERIFIED_APPROVED',
            'debt_enforcement_stage' => 'NORMAL',
            'verified_by_admin_id' => $this->admin->id,
        ]);

        $origin = Port::create([
            'id' => 'IDJKT',
            'name' => 'Tanjung Priok',
            'city' => 'Jakarta',
            'country_code' => 'ID',
            'port_type' => 'SEA',
            'latitude' => -6.10,
            'longitude' => 106.88,
        ]);
        $dest = Port::create([
            'id' => 'SGSIN',
            'name' => 'Port of Singapore',
            'city' => 'Singapore',
            'country_code' => 'SG',
            'port_type' => 'SEA',
            'latitude' => 1.26,
            'longitude' => 103.82,
        ]);
        $route = Route::create([
            'id' => Str::uuid()->toString(),
            'origin_port_id' => $origin->id,
            'destination_port_id' => $dest->id,
            'transport_mode' => 'SEA',
            'transit_time_days' => 2,
            'base_rate' => 12000000,
        ]);

        $booking = OpsCargoBooking::create([
            'id' => Str::uuid()->toString(),
            'tracking_number' => 'RPN-DETAIL-001',
            'shipper_id' => $customer->id,
            'company_id' => $company->id,
            'route_id' => $route->id,
            'booking_type' => 'CORPORATE_FCL',
            'cargo_type' => 'GENERAL_CARGO',
            'total_weight_kg' => 1000,
            'total_volume_cbm' => 10,
            'declared_goods_value' => 50000000,
            'currency' => 'IDR',
            'cargo_status' => 'SAILING',
        ]);

        $invoice = AdmInvoice::create([
            'id' => Str::uuid()->toString(),
            'invoice_number' => 'INV-DETAIL-001',
            'company_id' => $company->id,
            'cargo_booking_id' => $booking->id,
            'total_invoice_amount' => 12000000,
            'currency' => 'IDR',
            'due_date' => now()->addDays(30),
            'payment_status' => 'UNPAID',
        ]);

        $operatorUser = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Port Officer',
            'username' => 'officer01',
            'email' => 'officer@fastcargo.test',
            'password' => Hash::make('Secret123!'),
            'user_type' => 'OPERATOR_USER',
            'status' => 'ACTIVE',
        ]);
        $operatorProfile = OpsOperatorProfile::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $operatorUser->id,
            'employee_id' => 'EMP-001',
            'assigned_hub_id' => 'IDJKT',
            'operator_role' => 'HARBOR_MASTER',
            'security_clearance_level' => 3,
            'shift_status' => 'ON_DUTY',
        ]);

        $incident = OpsIncidentEscalation::create([
            'id' => Str::uuid()->toString(),
            'incident_ticket_no' => 'INC-DETAIL-001',
            'cargo_booking_id' => $booking->id,
            'reporter_operator_id' => $operatorProfile->id,
            'incident_type' => 'WEATHER_DELAY',
            'requested_amount' => 1500000,
            'approval_status' => 'PENDING_REVIEW',
            'admin_notes' => 'Heavy swell at Sunda Strait.',
            'sla_response_minutes' => 45,
        ]);

        $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get("http://admin.fastcargo.local/users/{$customer->id}")
            ->assertStatus(200)
            ->assertSee('PT Nusantara Shipping');

        $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get("http://admin.fastcargo.local/kyc/{$customer->id}")
            ->assertStatus(200)
            ->assertSee('PT Nusantara Shipping Tbk');

        $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get("http://admin.fastcargo.local/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertSee('INV-DETAIL-001');

        $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get("http://admin.fastcargo.local/incidents/{$incident->id}")
            ->assertStatus(200)
            ->assertSee('INC-DETAIL-001');

        $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get("http://admin.fastcargo.local/credits/{$credit->id}")
            ->assertStatus(200)
            ->assertSee('PT Nusantara Shipping Tbk');
    }

    public function test_admin_cannot_suspend_or_block_own_account(): void
    {
        $response = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post("http://admin.fastcargo.local/users/{$this->admin->id}/suspend");

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->admin->refresh();
        $this->assertSame('ACTIVE', $this->admin->status);
    }

    public function test_kyc_approval_sets_corporate_user_type_and_auto_provisions_corporate_credit_facility(): void
    {
        $applicant = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'PT Samudera Raya',
            'username' => 'samudera_raya',
            'email' => 'kyc@samudera.test',
            'password' => Hash::make('Secret123!'),
            'user_type' => 'RETAIL_CUSTOMER',
            'status' => 'ACTIVE',
            'kyc_aml_status' => 'PENDING',
        ]);

        $response = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post("http://admin.fastcargo.local/kyc/{$applicant->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $applicant->refresh();
        $this->assertSame('VERIFIED', $applicant->kyc_aml_status);
        $this->assertSame('CORPORATE_USER', $applicant->user_type);

        $company = Company::where('user_id', $applicant->id)->first();
        $this->assertNotNull($company);

        $credit = AdmCorporateCredit::where('company_id', $company->id)->first();
        $this->assertNotNull($credit);
        $this->assertSame('VERIFIED_APPROVED', $credit->kyc_status);
        $this->assertEquals(50000000, (float) $credit->credit_limit_amount);

        $this->assertDatabaseHas('adm_immutable_audit_trails', [
            'action' => 'KYC_APPROVED',
            'target_id' => $applicant->id,
        ]);
    }

    public function test_manual_invoice_mark_paid_and_sequential_escrow_release_execute_revenue_split(): void
    {
        $customer = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Shipper One',
            'username' => 'shipper_one',
            'email' => 'shipper@one.test',
            'password' => Hash::make('Secret123!'),
            'user_type' => 'CORPORATE_USER',
            'status' => 'ACTIVE',
        ]);

        $company = Company::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $customer->id,
            'company_name' => 'PT Shipper One',
            'npwp' => '01.111.222.3-444.000',
            'nib' => '8120009998887',
        ]);

        $origin = Port::create([
            'id' => 'IDSUB',
            'name' => 'Tanjung Perak',
            'city' => 'Surabaya',
            'country_code' => 'ID',
            'port_type' => 'SEA',
            'latitude' => -7.19,
            'longitude' => 112.73,
        ]);
        $dest = Port::create([
            'id' => 'MYPKG',
            'name' => 'Port Klang',
            'city' => 'Klang',
            'country_code' => 'MY',
            'port_type' => 'SEA',
            'latitude' => 3.00,
            'longitude' => 101.40,
        ]);
        $route = Route::create([
            'id' => Str::uuid()->toString(),
            'origin_port_id' => $origin->id,
            'destination_port_id' => $dest->id,
            'transport_mode' => 'SEA',
            'transit_time_days' => 3,
            'base_rate' => 10000000,
        ]);

        $booking = OpsCargoBooking::create([
            'id' => Str::uuid()->toString(),
            'tracking_number' => 'RPN-ESCROW-001',
            'shipper_id' => $customer->id,
            'company_id' => $company->id,
            'route_id' => $route->id,
            'booking_type' => 'RETAIL_LCL',
            'cargo_type' => 'GENERAL_CARGO',
            'total_weight_kg' => 500,
            'total_volume_cbm' => 4,
            'declared_goods_value' => 25000000,
            'currency' => 'IDR',
            'cargo_status' => 'BOOKED',
        ]);

        $invoice = AdmInvoice::create([
            'id' => Str::uuid()->toString(),
            'invoice_number' => 'INV-MANUAL-001',
            'company_id' => $company->id,
            'cargo_booking_id' => $booking->id,
            'total_invoice_amount' => 10000000,
            'currency' => 'IDR',
            'due_date' => now()->addDays(14),
            'payment_status' => 'UNPAID',
        ]);

        // 1. Mark invoice paid manually
        $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post("http://admin.fastcargo.local/invoices/{$invoice->id}/mark-paid")
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame('PAID_SETTLED', $invoice->payment_status);

        $escrow = AdmEscrowLedger::where('cargo_booking_id', $booking->id)->first();
        $this->assertNotNull($escrow);
        $this->assertEquals(10000000, (float) $escrow->total_paid_amount);

        // 2. Attempting Milestone 2 before Milestone 1 is blocked
        $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post("http://admin.fastcargo.local/finance/escrow/{$escrow->id}/release", ['milestone' => 2])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $escrow->refresh();
        $this->assertNull($escrow->milestone_2_released_at);

        // 3. Release M1 -> M2 -> M3 in sequence
        foreach ([1, 2, 3] as $m) {
            $this->withSession($this->adminSession())
                ->actingAs($this->admin, 'admin')
                ->withHeader('Sec-Fetch-Site', 'same-origin')
                ->post("http://admin.fastcargo.local/finance/escrow/{$escrow->id}/release", ['milestone' => $m])
                ->assertRedirect()
                ->assertSessionHas('success');
        }

        $escrow->refresh();
        $this->assertSame('FULLY_SETTLED', $escrow->escrow_status);

        // Verify 12% / 78% / 10% revenue split recorded in adm_revenue_splits
        $this->assertDatabaseHas('adm_revenue_splits', [
            'cargo_booking_id' => $booking->id,
            'platform_fee_amount' => 1200000.00,
            'operator_share_amount' => 7800000.00,
            'tax_and_port_dues' => 1000000.00,
        ]);
    }
}
