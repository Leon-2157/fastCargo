<?php

namespace Database\Seeders;

use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsManifest;
use App\Modules\Shared\Models\OpsOperatorProfile;
use App\Modules\Shared\Models\Route;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationalMasterSeeder extends Seeder
{
    public function run(): void
    {
        $adminUserId = '019445a1-0001-7000-8000-000000000001';
        $operatorUserId = '019445a1-0002-7000-8000-000000000002';
        $customerUserId = '019445a1-0003-7000-8000-000000000003';

        // 1. Seed Company for Demo Customer
        $companyId = '019445a1-0003-7000-9000-000000000001';
        Company::updateOrCreate(
            ['id' => $companyId],
            [
                'user_id' => $customerUserId,
                'company_name' => 'PT Nusantara Global Export',
                'nib' => '1234567890123',
                'npwp' => '01.234.567.8-012.000',
                'company_address' => 'Kawasan Industri MM2100 Blok C-4, Cikarang Barat, Bekasi, Jawa Barat',
                'pic_name' => 'Demo Customer',
                'pic_phone' => '+62811000003',
                'email' => 'customer@example.com',
                'industry' => 'Manufacturing & Electronics',
            ]
        );

        // 2. Seed Operator Profile for Head Operator
        $operatorProfileId = '019445a1-0002-7000-9000-000000000001';
        OpsOperatorProfile::updateOrCreate(
            ['id' => $operatorProfileId],
            [
                'user_id' => $operatorUserId,
                'employee_id' => 'OPS-JKT-001',
                'assigned_hub_id' => 'IDJKT',
                'role_tier' => 'OPERATOR_INTERNATIONAL',
                'kpi_rating' => 4.95,
                'is_on_duty' => true,
            ]
        );

        // 3. Seed Common ASEAN HS Codes
        $hsCodes = [
            ['code' => '8471.30', 'description' => 'Portable automatic data processing machines (Laptops, Tablets)', 'category' => 'Electronics', 'is_dangerous' => false],
            ['code' => '8517.13', 'description' => 'Smartphones and other telecommunication apparatus', 'category' => 'Telecommunications', 'is_dangerous' => false],
            ['code' => '0901.11', 'description' => 'Coffee, not roasted, not decaffeinated (Arabica & Robusta Beans)', 'category' => 'Agriculture', 'is_dangerous' => false],
            ['code' => '6109.10', 'description' => 'T-shirts, singlets and other vests, knitted or crocheted, of cotton', 'category' => 'Textiles & Apparel', 'is_dangerous' => false],
            ['code' => '8708.29', 'description' => 'Parts and accessories of the motor vehicles (Automotive components)', 'category' => 'Automotive', 'is_dangerous' => false],
            ['code' => '3923.10', 'description' => 'Boxes, cases, crates and similar articles of plastics', 'category' => 'Plastics & Packaging', 'is_dangerous' => false],
            ['code' => '2905.11', 'description' => 'Methanol (methyl alcohol) - Industrial chemical', 'category' => 'Chemicals', 'is_dangerous' => true],
            ['code' => '0306.17', 'description' => 'Frozen cold-water shrimps and prawns (Reefer cargo)', 'category' => 'Fishery & Seafood', 'is_dangerous' => false],
        ];

        foreach ($hsCodes as $item) {
            DB::table('hs_codes')->updateOrInsert(['code' => $item['code']], $item);
        }

        // 4. Seed Exchange Rates
        $rates = [
            ['currency_pair' => 'IDR_USD', 'rate' => 0.000063, 'expires_at' => now()->addDays(30)],
            ['currency_pair' => 'IDR_SGD', 'rate' => 0.000085, 'expires_at' => now()->addDays(30)],
            ['currency_pair' => 'IDR_MYR', 'rate' => 0.000280, 'expires_at' => now()->addDays(30)],
            ['currency_pair' => 'IDR_THB', 'rate' => 0.002200, 'expires_at' => now()->addDays(30)],
        ];

        foreach ($rates as $r) {
            DB::table('exchange_rates')->updateOrInsert(
                ['currency_pair' => $r['currency_pair']],
                array_merge($r, ['updated_at' => now(), 'created_at' => now()])
            );
        }

        // 5. Seed ASEAN & Domestic Routes
        $routesData = [
            // Sea Routes
            ['origin_port_id' => 'IDJKT', 'destination_port_id' => 'SGSIN', 'transport_mode' => 'SEA', 'transit_days' => 2, 'base_rate_idr' => 4500000],
            ['origin_port_id' => 'SGSIN', 'destination_port_id' => 'IDJKT', 'transport_mode' => 'SEA', 'transit_days' => 2, 'base_rate_idr' => 4800000],
            ['origin_port_id' => 'IDJKT', 'destination_port_id' => 'IDSUB', 'transport_mode' => 'SEA', 'transit_days' => 2, 'base_rate_idr' => 2200000],
            ['origin_port_id' => 'IDSUB', 'destination_port_id' => 'IDJKT', 'transport_mode' => 'SEA', 'transit_days' => 2, 'base_rate_idr' => 2200000],
            ['origin_port_id' => 'IDJKT', 'destination_port_id' => 'MYPKG', 'transport_mode' => 'SEA', 'transit_days' => 3, 'base_rate_idr' => 5200000],
            ['origin_port_id' => 'MYPKG', 'destination_port_id' => 'SGSIN', 'transport_mode' => 'SEA', 'transit_days' => 1, 'base_rate_idr' => 3100000],
            ['origin_port_id' => 'SGSIN', 'destination_port_id' => 'THBKK', 'transport_mode' => 'SEA', 'transit_days' => 4, 'base_rate_idr' => 6000000],
            ['origin_port_id' => 'SGSIN', 'destination_port_id' => 'VNSGN', 'transport_mode' => 'SEA', 'transit_days' => 3, 'base_rate_idr' => 5500000],
            ['origin_port_id' => 'SGSIN', 'destination_port_id' => 'PHMNL', 'transport_mode' => 'SEA', 'transit_days' => 5, 'base_rate_idr' => 7200000],
            ['origin_port_id' => 'IDBTM', 'destination_port_id' => 'SGSIN', 'transport_mode' => 'SEA', 'transit_days' => 1, 'base_rate_idr' => 1800000],
            ['origin_port_id' => 'IDJKT', 'destination_port_id' => 'IDMAK', 'transport_mode' => 'SEA', 'transit_days' => 4, 'base_rate_idr' => 3800000],
            ['origin_port_id' => 'IDJKT', 'destination_port_id' => 'IDMED', 'transport_mode' => 'SEA', 'transit_days' => 3, 'base_rate_idr' => 3500000],

            // Air Routes
            ['origin_port_id' => 'APCGK', 'destination_port_id' => 'APSIN', 'transport_mode' => 'AIR', 'transit_days' => 1, 'base_rate_idr' => 8500000],
            ['origin_port_id' => 'APSIN', 'destination_port_id' => 'APCGK', 'transport_mode' => 'AIR', 'transit_days' => 1, 'base_rate_idr' => 8900000],
            ['origin_port_id' => 'APCGK', 'destination_port_id' => 'APKUL', 'transport_mode' => 'AIR', 'transit_days' => 1, 'base_rate_idr' => 7800000],
            ['origin_port_id' => 'APCGK', 'destination_port_id' => 'APBKK', 'transport_mode' => 'AIR', 'transit_days' => 1, 'base_rate_idr' => 9200000],
            ['origin_port_id' => 'APSIN', 'destination_port_id' => 'APSGN', 'transport_mode' => 'AIR', 'transit_days' => 1, 'base_rate_idr' => 8000000],
            ['origin_port_id' => 'APCGK', 'destination_port_id' => 'APKDI', 'transport_mode' => 'AIR', 'transit_days' => 1, 'base_rate_idr' => 3500000],

            // Land Hub Routes
            ['origin_port_id' => 'LHJHB', 'destination_port_id' => 'SGSIN', 'transport_mode' => 'LAND', 'transit_days' => 1, 'base_rate_idr' => 2500000],
            ['origin_port_id' => 'LHJHB', 'destination_port_id' => 'LHMAL', 'transport_mode' => 'LAND', 'transit_days' => 1, 'base_rate_idr' => 1500000],
            ['origin_port_id' => 'LHMAL', 'destination_port_id' => 'LHSAD', 'transport_mode' => 'LAND', 'transit_days' => 2, 'base_rate_idr' => 3800000],
            ['origin_port_id' => 'LHSAD', 'destination_port_id' => 'THBKK', 'transport_mode' => 'LAND', 'transit_days' => 2, 'base_rate_idr' => 4200000],
        ];

        $routeMap = [];
        foreach ($routesData as $r) {
            $existing = DB::table('routes')
                ->where('origin_port_id', $r['origin_port_id'])
                ->where('destination_port_id', $r['destination_port_id'])
                ->where('transport_mode', $r['transport_mode'])
                ->first();

            if (! $existing) {
                $id = Str::uuid()->toString();
                DB::table('routes')->insert(array_merge($r, [
                    'id' => $id,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
                $routeMap[$r['origin_port_id'].'-'.$r['destination_port_id']] = $id;
            } else {
                $routeMap[$r['origin_port_id'].'-'.$r['destination_port_id']] = $existing->id;
            }
        }

        // 6. Seed Demo Bookings for Immediate UI Richness
        $jktSinRouteId = $routeMap['IDJKT-SGSIN'] ?? Route::where('origin_port_id', 'IDJKT')->where('destination_port_id', 'SGSIN')->value('id');

        if ($jktSinRouteId) {
            // Demo Booking 1: RPN-20261106-001 (In Transit / Sailing)
            $booking1Id = '019445a1-0010-7000-9000-000000000001';
            $booking1 = OpsCargoBooking::updateOrCreate(
                ['id' => $booking1Id],
                [
                    'tracking_number' => 'RPN-20261106-001',
                    'shipper_id' => $customerUserId,
                    'company_id' => $companyId,
                    'route_id' => $jktSinRouteId,
                    'hs_code' => '8471.30',
                    'booking_type' => 'CORPORATE_FCL',
                    'cargo_type' => 'GENERAL_CARGO',
                    'total_weight_kg' => 4500.00,
                    'total_volume_cbm' => 18.50,
                    'declared_goods_value' => 850000000.00,
                    'currency' => 'IDR',
                    'cargo_status' => 'SAILING',
                    'customs_channel' => 'GREEN_CHANNEL',
                    'assigned_operator_id' => $operatorProfileId,
                ]
            );

            // Item for Booking 1
            DB::table('ops_cargo_items')->updateOrInsert(
                ['cargo_booking_id' => $booking1Id, 'item_name' => 'High-Precision Microcontroller Units (Palletized)'],
                [
                    'hs_code' => '8471.30',
                    'package_type' => 'PALLET',
                    'quantity' => 12,
                    'weight_kg' => 4500.00,
                    'length_cm' => 120.00,
                    'width_cm' => 100.00,
                    'height_cm' => 150.00,
                    'volume_cbm' => 18.50,
                    'is_dangerous_good' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Tracking Events for Booking 1
            $events1 = [
                ['event_name' => 'Booking Confirmed & Export Clearance Registered', 'current_status' => 'BOOKED', 'latitude' => -6.1050, 'longitude' => 106.8847, 'minutes_ago' => 2880],
                ['event_name' => 'Gate-In Tanjung Priok International Terminal (CY-04)', 'current_status' => 'GATE_IN', 'latitude' => -6.1050, 'longitude' => 106.8847, 'minutes_ago' => 1440],
                ['event_name' => 'Customs Green Channel Verified & Seal Affixed', 'current_status' => 'CUSTOMS_INSPECTION', 'latitude' => -6.1050, 'longitude' => 106.8847, 'minutes_ago' => 720],
                ['event_name' => 'Loaded aboard MV Nusantara Express Voy 408E - Sailing to Singapore', 'current_status' => 'SAILING', 'latitude' => -3.2000, 'longitude' => 105.8000, 'minutes_ago' => 120],
            ];

            foreach ($events1 as $ev) {
                DB::table('ops_tracking_events')->updateOrInsert(
                    ['cargo_booking_id' => $booking1Id, 'event_name' => $ev['event_name']],
                    [
                        'current_status' => $ev['current_status'],
                        'latitude' => $ev['latitude'],
                        'longitude' => $ev['longitude'],
                        'operator_id' => $operatorProfileId,
                        'event_timestamp' => now()->subMinutes($ev['minutes_ago']),
                        'created_at' => now()->subMinutes($ev['minutes_ago']),
                    ]
                );
            }

            // Manifest for Booking 1
            $manifestId = '019445a1-0020-7000-9000-000000000001';
            OpsManifest::updateOrCreate(
                ['id' => $manifestId],
                [
                    'manifest_number' => 'MMK-JKT-SIN-20261106',
                    'origin_port_id' => 'IDJKT',
                    'destination_port_id' => 'SGSIN',
                    'vessel_or_vehicle_name' => 'MV Nusantara Express',
                    'voyage_number' => 'VOY-408E',
                    'departure_eta' => now()->subHours(2),
                    'arrival_eta' => now()->addHours(46),
                    'status' => 'SAILING',
                ]
            );

            // Manifest Item
            DB::table('ops_manifest_items')->updateOrInsert(
                ['manifest_id' => $manifestId, 'cargo_booking_id' => $booking1Id],
                [
                    'container_serial_number' => 'FCGU-892104-7',
                    'container_type' => 'DRY_40FT',
                    'seal_number' => 'ID-JKT-SEAL-99812',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Invoice for Booking 1
            $invoice1Id = '019445a1-0030-7000-9000-000000000001';
            DB::table('adm_invoices')->updateOrInsert(
                ['id' => $invoice1Id],
                [
                    'invoice_number' => 'INV-B2B-20261106-001',
                    'company_id' => $companyId,
                    'cargo_booking_id' => $booking1Id,
                    'total_invoice_amount' => 14500000.00,
                    'currency' => 'IDR',
                    'exchange_rate_locked' => 1.0000,
                    'due_date' => now()->addDays(14)->toDateString(),
                    'payment_status' => 'PAID_SETTLED',
                    'paid_at' => now()->subDays(1),
                    'created_at' => now()->subDays(2),
                    'updated_at' => now()->subDays(1),
                ]
            );

            // Payment Transaction for Booking 1
            DB::table('payment_transactions')->updateOrInsert(
                ['id' => '019445a1-0040-7000-9000-000000000001'],
                [
                    'invoice_id' => $invoice1Id,
                    'payer_user_id' => $customerUserId,
                    'payment_gateway' => 'DANA',
                    'payment_channel' => 'QRIS',
                    'transaction_ref' => 'DANA-TX-992819028',
                    'amount' => 14500000.00,
                    'payment_status' => 'SETTLEMENT',
                    'paid_at' => now()->subDays(1),
                    'created_at' => now()->subDays(1),
                    'updated_at' => now()->subDays(1),
                ]
            );

            // Demo Booking 2: RPN-20261110-002 (Retail LCL - Booked / Pending Manifest)
            $booking2Id = '019445a1-0010-7000-9000-000000000002';
            OpsCargoBooking::updateOrCreate(
                ['id' => $booking2Id],
                [
                    'tracking_number' => 'RPN-20261110-002',
                    'shipper_id' => $customerUserId,
                    'company_id' => $companyId,
                    'route_id' => $jktSinRouteId,
                    'hs_code' => '0901.11',
                    'booking_type' => 'RETAIL_LCL',
                    'cargo_type' => 'GENERAL_CARGO',
                    'total_weight_kg' => 650.00,
                    'total_volume_cbm' => 2.40,
                    'declared_goods_value' => 85000000.00,
                    'currency' => 'IDR',
                    'cargo_status' => 'BOOKED',
                    'customs_channel' => 'GREEN_CHANNEL',
                    'assigned_operator_id' => $operatorProfileId,
                ]
            );

            DB::table('ops_cargo_items')->updateOrInsert(
                ['cargo_booking_id' => $booking2Id, 'item_name' => 'Specialty Arabica Gayo Green Coffee Beans (Jute Bags)'],
                [
                    'hs_code' => '0901.11',
                    'package_type' => 'BUNDLE',
                    'quantity' => 10,
                    'weight_kg' => 650.00,
                    'length_cm' => 80.00,
                    'width_cm' => 60.00,
                    'height_cm' => 50.00,
                    'volume_cbm' => 2.40,
                    'is_dangerous_good' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('ops_tracking_events')->updateOrInsert(
                ['cargo_booking_id' => $booking2Id, 'event_name' => 'Order Placed & Awaiting Consignment Drop-off'],
                [
                    'current_status' => 'BOOKED',
                    'latitude' => -6.1050,
                    'longitude' => 106.8847,
                    'operator_id' => $operatorProfileId,
                    'event_timestamp' => now()->subHours(5),
                    'created_at' => now()->subHours(5),
                ]
            );

            DB::table('adm_invoices')->updateOrInsert(
                ['id' => '019445a1-0030-7000-9000-000000000002'],
                [
                    'invoice_number' => 'INV-LCL-20261110-002',
                    'company_id' => $companyId,
                    'cargo_booking_id' => $booking2Id,
                    'total_invoice_amount' => 3650000.00,
                    'currency' => 'IDR',
                    'exchange_rate_locked' => 1.0000,
                    'due_date' => now()->addDays(7)->toDateString(),
                    'payment_status' => 'UNPAID',
                    'paid_at' => null,
                    'created_at' => now()->subHours(5),
                    'updated_at' => now()->subHours(5),
                ]
            );
        }
    }
}
