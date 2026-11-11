<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\Address;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\Route;
use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CockpitMapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_cockpit_renders_map_switches_and_valid_json_layers(): void
    {
        $admin = User::where('email', 'admin@fastcargo.internal')->firstOrFail();

        // Create a user with an address city and another with no resolvable location.
        $surabayaUser = User::create([
            'name' => 'Surabaya Exporter',
            'email' => 'sby-exporter@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'RETAIL_CUSTOMER',
            'status' => 'ACTIVE',
            'kyc_aml_status' => 'VERIFIED',
            'timezone' => null,
        ]);

        Address::create([
            'user_id' => $surabayaUser->id,
            'type' => 'company',
            'country' => 'Indonesia',
            'country_code' => 'ID',
            'city' => 'Surabaya',
            'street' => 'Jl. Perak Timur 10',
        ]);

        User::create([
            'name' => 'Unmapped Account',
            'email' => 'unmapped-user@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'RETAIL_CUSTOMER',
            'status' => 'ACTIVE',
            'kyc_aml_status' => 'PENDING',
            'timezone' => null,
        ]);

        $viewResponse = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->get('http://admin.fastcargo.local/dashboard');

        $viewResponse->assertStatus(200)
            ->assertSee('Network Spatial Operations Map')
            ->assertSee('data-layer-switch="cargo"', false)
            ->assertSee('data-layer-switch="ops"', false)
            ->assertSee('data-layer-switch="user"', false);

        $jsonResponse = $this->actingAs($admin, 'admin')
            ->withSession(['admin_two_factor_verified' => true])
            ->getJson('http://admin.fastcargo.local/dashboard/map-data');

        $jsonResponse->assertStatus(200)
            ->assertJsonPath('role', 'admin')
            ->assertJsonPath('default_layer', 'cargo');

        $layers = $jsonResponse->json('layers');
        $this->assertCount(2, $layers['cargo']['items']);
        $this->assertCount(1, $layers['ops']['items']);
        $this->assertEquals('Head Operator', $layers['ops']['items'][0]['title']);

        $userItems = collect($layers['user']['items']);
        $this->assertEquals(1, $layers['user']['unmapped_count']);
        $this->assertTrue($userItems->contains('title', 'Surabaya Exporter'));
        $this->assertFalse($userItems->contains('title', 'Unmapped Account'));
    }

    public function test_operator_cockpit_renders_assigned_hub_and_manifest_cargo_layers(): void
    {
        $operator = User::where('email', 'operator@fastcargo.com')->firstOrFail();

        $viewResponse = $this->actingAs($operator, 'operator')
            ->withSession(['two_factor_verified' => true])
            ->get('http://operator.fastcargo.local/');

        $viewResponse->assertStatus(200)
            ->assertSee('Tanjung Priok Port (IDJKT)')
            ->assertSee('Station Dispatch &amp; Fleet Map', false)
            ->assertSee('data-layer-switch="manifest"', false)
            ->assertSee('data-layer-switch="cargo"', false);

        $jsonResponse = $this->actingAs($operator, 'operator')
            ->withSession(['two_factor_verified' => true])
            ->getJson('http://operator.fastcargo.local/map-data');

        $jsonResponse->assertStatus(200)
            ->assertJsonPath('role', 'operator')
            ->assertJsonPath('default_layer', 'manifest')
            ->assertJsonPath('hub_anchor.id', 'IDJKT');

        $layers = $jsonResponse->json('layers');
        $this->assertNotEmpty($layers['manifest']['items']);
        $this->assertNotEmpty($layers['cargo']['items']);
    }

    public function test_customer_cockpit_map_enforces_tenant_isolation_and_active_vs_all_layers(): void
    {
        $customer = User::where('email', 'customer@example.com')->firstOrFail();

        $otherUser = User::create([
            'name' => 'Competitor Shipper',
            'email' => 'competitor@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'RETAIL_CUSTOMER',
            'status' => 'ACTIVE',
            'kyc_aml_status' => 'VERIFIED',
            'timezone' => 'Asia/Singapore',
        ]);

        $route = Route::firstOrFail();

        $otherBooking = OpsCargoBooking::create([
            'tracking_number' => 'RPN-OTHER-999',
            'shipper_id' => $otherUser->id,
            'route_id' => $route->id,
            'booking_type' => 'RETAIL_LCL',
            'cargo_type' => 'GENERAL_CARGO',
            'total_weight_kg' => 100,
            'total_volume_cbm' => 1,
            'declared_goods_value' => 5000000,
            'currency' => 'IDR',
            'cargo_status' => OpsCargoBooking::STATUS_BOOKED,
        ]);

        $deliveredBooking = OpsCargoBooking::create([
            'tracking_number' => 'RPN-DONE-'.Str::upper(Str::random(4)),
            'shipper_id' => $customer->id,
            'route_id' => $route->id,
            'booking_type' => 'RETAIL_LCL',
            'cargo_type' => 'GENERAL_CARGO',
            'total_weight_kg' => 250,
            'total_volume_cbm' => 1.5,
            'declared_goods_value' => 12000000,
            'currency' => 'IDR',
            'cargo_status' => OpsCargoBooking::STATUS_DELIVERED,
        ]);

        $viewResponse = $this->actingAs($customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->get('http://customer.fastcargo.local/');

        $viewResponse->assertStatus(200)
            ->assertSee('My Consignment Corridor Map')
            ->assertSee('data-layer-switch="active"', false)
            ->assertSee('data-layer-switch="all"', false);

        $jsonResponse = $this->actingAs($customer, 'customer')
            ->withSession(['two_factor_verified' => true])
            ->getJson('http://customer.fastcargo.local/map-data');

        $jsonResponse->assertStatus(200)
            ->assertJsonPath('role', 'customer')
            ->assertJsonPath('default_layer', 'active');

        $activeTitles = collect($jsonResponse->json('layers.active.items'))->pluck('title');
        $allTitles = collect($jsonResponse->json('layers.all.items'))->pluck('title');

        $this->assertFalse($activeTitles->contains($otherBooking->tracking_number));
        $this->assertFalse($allTitles->contains($otherBooking->tracking_number));
        $this->assertFalse($activeTitles->contains($deliveredBooking->tracking_number));
        $this->assertTrue($allTitles->contains($deliveredBooking->tracking_number));
    }

    public function test_map_data_endpoints_require_authentication(): void
    {
        $this->get('http://admin.fastcargo.local/dashboard/map-data')->assertRedirect();
        $this->get('http://operator.fastcargo.local/map-data')->assertRedirect();
        $this->get('http://customer.fastcargo.local/map-data')->assertRedirect();
    }
}
