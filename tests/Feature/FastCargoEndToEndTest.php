<?php

namespace Tests\Feature;

use App\Modules\Shared\AI\AiManager;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsManifest;
use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FastCargoEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test public landing page returns HTTP 200.
     */
    public function test_landing_page_renders_successfully(): void
    {
        $response = $this->get('http://fastcargo.local/');

        $response->assertStatus(200);
        $response->assertSee('FastCargo');
    }

    /**
     * Test public tracking endpoint for valid tracking number.
     */
    public function test_public_tracking_for_seeded_consignment(): void
    {
        $response = $this->get('http://fastcargo.local/track/RPN-20261106-001');

        $response->assertStatus(200);
        $response->assertSee('RPN-20261106-001');
        $response->assertSee('Tanjung Priok Port');
    }

    /**
     * Test public tracking endpoint for nonexistent consignment.
     */
    public function test_public_tracking_for_nonexistent_consignment(): void
    {
        $response = $this->get('http://fastcargo.local/track/NONEXISTENT-999');

        $response->assertStatus(200);
        $response->assertSee('Tracking Number Not Found');
    }

    /**
     * Test Customer portal login screen loads.
     */
    public function test_customer_portal_login_renders(): void
    {
        $response = $this->get('http://customer.fastcargo.local/login');

        $response->assertStatus(200);
        $response->assertSee('Customer Portal');
    }

    /**
     * Test Operator terminal login screen loads.
     */
    public function test_operator_terminal_login_renders(): void
    {
        $response = $this->get('http://operator.fastcargo.local/login');

        $response->assertStatus(200);
        $response->assertSee('Terminal');
    }

    /**
     * Test Admin governance cockpit login screen loads.
     */
    public function test_admin_governance_login_renders(): void
    {
        $response = $this->get('http://admin.fastcargo.local/login');

        $response->assertStatus(200);
        $response->assertSee('Administrative Cockpit');
    }

    /**
     * Test Customer dashboard authenticated access.
     */
    public function test_customer_dashboard_accessible_with_auth(): void
    {
        $customer = User::where('email', 'customer@example.com')->first();
        $this->assertNotNull($customer);

        $response = $this->actingAs($customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->get('http://customer.fastcargo.local/');

        $response->assertStatus(200);
        $response->assertSee('Customer Cargo Cockpit');
    }

    /**
     * Test Operator terminal dashboard authenticated access.
     */
    public function test_operator_dashboard_accessible_with_auth(): void
    {
        $operator = User::where('email', 'operator@fastcargo.com')->first();
        $this->assertNotNull($operator);

        $response = $this->actingAs($operator, 'operator')
            ->withSession(['two_factor_verified' => true])
            ->get('http://operator.fastcargo.local/');

        $response->assertStatus(200);
        $response->assertSee('Station Dispatch Terminal');
    }

    /**
     * Test Admin dashboard authenticated access.
     */
    public function test_admin_dashboard_accessible_with_auth(): void
    {
        $admin = User::where('email', 'admin@fastcargo.internal')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get('http://admin.fastcargo.local/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Executive Governance Cockpit');
    }

    /**
     * Test Dana Business QRIS payment simulation.
     */
    public function test_customer_can_simulate_qris_payment(): void
    {
        $customer = User::where('email', 'customer@example.com')->first();
        $invoice = AdmInvoice::first();
        $this->assertNotNull($invoice);
        $invoice->update(['payment_status' => 'UNPAID']);

        $response = $this->withoutMiddleware()
            ->actingAs($customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->post("http://customer.fastcargo.local/invoices/{$invoice->id}/pay");

        $response->assertRedirect("http://customer.fastcargo.local/invoices/{$invoice->id}");
        $invoice->refresh();
        $this->assertEquals('PAID_SETTLED', $invoice->payment_status);
    }

    /**
     * Test 9router AI Provider resolution.
     */
    public function test_ai_provider_resolution(): void
    {
        $provider = AiManager::provider();
        $this->assertNotNull($provider);
        $this->assertEquals('9router (FastCargo)', $provider->name());
    }

    /**
     * Regression test for Operator, Customer, and Admin routes exercising Eloquent relationships.
     */
    public function test_portal_operational_and_relationship_routes_return_200(): void
    {
        $operator = User::where('email', 'operator@fastcargo.com')->firstOrFail();
        $customer = User::where('email', 'customer@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@fastcargo.internal')->firstOrFail();

        $manifest = OpsManifest::firstOrFail();
        $booking = OpsCargoBooking::where('tracking_number', 'RPN-20261106-001')->firstOrFail();

        $this->actingAs($operator, 'operator')
            ->withSession(['two_factor_verified' => true])
            ->get('http://operator.fastcargo.local/manifests')
            ->assertStatus(200);

        $this->actingAs($operator, 'operator')
            ->withSession(['two_factor_verified' => true])
            ->get("http://operator.fastcargo.local/manifests/{$manifest->id}")
            ->assertStatus(200);

        $this->actingAs($operator, 'operator')
            ->withSession(['two_factor_verified' => true])
            ->get('http://operator.fastcargo.local/tracking')
            ->assertStatus(200);

        $this->actingAs($operator, 'operator')
            ->withSession(['two_factor_verified' => true])
            ->get('http://operator.fastcargo.local/fleet')
            ->assertStatus(200);

        $this->actingAs($customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->get("http://customer.fastcargo.local/bookings/{$booking->id}")
            ->assertStatus(200);

        $this->actingAs($customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->get('http://customer.fastcargo.local/invoices')
            ->assertStatus(200);

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get('http://admin.fastcargo.local/kyc')
            ->assertStatus(200);

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get('http://admin.fastcargo.local/invoices')
            ->assertStatus(200);
    }
}
