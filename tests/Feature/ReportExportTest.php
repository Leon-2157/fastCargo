<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\Port;
use App\Modules\Shared\Models\Route;
use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'id' => fake()->uuid(),
            'name' => 'Gov Director',
            'email' => 'director@fastcargo.test',
            'username' => 'govdirector',
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

    public function test_admin_can_view_reports_dashboard(): void
    {
        $response = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get('http://admin.fastcargo.local/reports');

        $response->assertStatus(200);
        $response->assertSee('Reports & Data Export Center');
        $response->assertSee('Invoices & Revenue Ledger');
        $response->assertSee('Escrow & Revenue Splits');
    }

    public function test_admin_can_export_invoices_csv_with_utf8_bom(): void
    {
        // Create sample invoice
        $customer = User::create([
            'id' => fake()->uuid(),
            'name' => 'PT Ekspor Sejahtera',
            'email' => 'finance@ekspor.co.id',
            'password' => Hash::make('Secret123!'),
            'user_type' => 'CORPORATE_USER',
            'status' => 'ACTIVE',
        ]);

        $company = Company::create([
            'id' => fake()->uuid(),
            'user_id' => $customer->id,
            'company_name' => 'PT Ekspor Sejahtera',
            'email' => $customer->email,
        ]);

        $origin = Port::create([
            'id' => 'IDJKT',
            'country_code' => 'ID',
            'name' => 'Tanjung Priok Port',
            'city' => 'Jakarta',
            'modality' => 'SEA',
            'latitude' => -6.102,
            'longitude' => 106.885,
        ]);

        $destination = Port::create([
            'id' => 'MYPKG',
            'country_code' => 'MY',
            'name' => 'Port Klang',
            'city' => 'Klang',
            'modality' => 'SEA',
            'latitude' => 3.002,
            'longitude' => 101.398,
        ]);

        $route = Route::create([
            'id' => 'ROUTE-SEA-JKT-PKG',
            'origin_port_id' => 'IDJKT',
            'destination_port_id' => 'MYPKG',
            'modality' => 'SEA',
            'distance_km' => 1100,
            'estimated_hours' => 48,
            'base_rate_per_kg' => 15000,
        ]);

        $booking = OpsCargoBooking::create([
            'id' => fake()->uuid(),
            'tracking_number' => 'FC-SEA-IDMY-998877',
            'shipper_id' => $customer->id,
            'company_id' => $company->id,
            'route_id' => $route->id,
            'cargo_status' => 'GATE_IN',
            'total_weight_kg' => 500,
            'total_volume_cbm' => 2.5,
            'declared_goods_value' => 75000000,
        ]);

        AdmInvoice::create([
            'id' => fake()->uuid(),
            'company_id' => $company->id,
            'cargo_booking_id' => $booking->id,
            'invoice_number' => 'INV-2026-0001',
            'total_invoice_amount' => 8475000,
            'currency' => 'IDR',
            'due_date' => now()->addDays(7),
            'payment_status' => 'PAID_SETTLED',
            'paid_at' => now(),
        ]);

        $response = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get('http://admin.fastcargo.local/reports/export/invoices?preset=this_month');

        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="FastCargo_invoices_report_', $response->headers->get('Content-Disposition') ?? '');

        // Capture streamed content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Must start with UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('INV-2026-0001', $content);
        $this->assertStringContainsString('PT Ekspor Sejahtera', $content);
        $this->assertStringContainsString('PAID_SETTLED', $content);

        // Verify audit log
        $this->assertDatabaseHas('adm_immutable_audit_trails', [
            'actor_id' => $this->admin->id,
            'action' => 'REPORT_EXPORTED_CSV',
            'target_id' => 'invoices',
        ]);
    }

    public function test_admin_can_export_manifests_and_escrow_csv(): void
    {
        // Test manifests export
        $manifestResponse = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get('http://admin.fastcargo.local/reports/export/manifests?preset=all');

        $manifestResponse->assertStatus(200);
        ob_start();
        $manifestResponse->sendContent();
        $manifestCsv = ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $manifestCsv);
        $this->assertStringContainsString('Manifest Number', $manifestCsv);
        $this->assertStringContainsString('Vessel / Vehicle Name', $manifestCsv);

        // Test escrow export
        $escrowResponse = $this->withSession($this->adminSession())
            ->actingAs($this->admin, 'admin')
            ->get('http://admin.fastcargo.local/reports/export/escrow?preset=all');

        $escrowResponse->assertStatus(200);
        ob_start();
        $escrowResponse->sendContent();
        $escrowCsv = ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $escrowCsv);
        $this->assertStringContainsString('Escrow Ledger ID', $escrowCsv);
        $this->assertStringContainsString('Operator Share (78%)', $escrowCsv);
    }
}
