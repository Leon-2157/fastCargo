<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel operasional: operator profiles, booking, items, manifest, tracking, incident, epod, telemetry
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_operator_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            // Format: OPS-JKT-042
            $table->string('employee_id', 50)->unique();
            $table->string('assigned_hub_id', 10);
            // OPERATOR_DOMESTIC, OPERATOR_INTERNATIONAL
            $table->string('role_tier', 50)->default('OPERATOR_DOMESTIC');
            $table->string('wireguard_pubkey')->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->decimal('kpi_rating', 3, 2)->default(5.00);
            $table->boolean('is_on_duty')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_hub_id')->references('id')->on('ports');
            $table->index('user_id', 'idx_ops_operator_user');
            $table->index('assigned_hub_id', 'idx_ops_operator_hub');
        });

        Schema::create('ops_cargo_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Format: RPN-20261106-001
            $table->string('tracking_number', 50)->unique();
            $table->uuid('shipper_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('route_id');
            $table->string('hs_code', 15)->nullable();
            // RETAIL_LCL, CORPORATE_FCL, CHARTER_TRUCK
            $table->string('booking_type', 50)->default('RETAIL_LCL');
            // GENERAL_CARGO, DANGEROUS_GOODS, REEFER_PERISHABLE
            $table->string('cargo_type', 50)->default('GENERAL_CARGO');
            $table->decimal('total_weight_kg', 10, 2);
            $table->decimal('total_volume_cbm', 10, 2);
            $table->decimal('declared_goods_value', 16, 2);
            $table->string('currency', 10)->default('IDR');
            // DRAFT, BOOKED, GATE_IN, CUSTOMS_INSPECTION, SAILING, DELIVERED, WEATHER_HOLD, CLAIM_HOLD, CANCELLED
            $table->string('cargo_status', 50)->default('BOOKED');
            // GREEN_CHANNEL, YELLOW_CHANNEL, RED_CHANNEL
            $table->string('customs_channel', 20)->nullable();
            $table->uuid('assigned_operator_id')->nullable();
            $table->timestamps();

            $table->foreign('shipper_id')->references('id')->on('users');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('route_id')->references('id')->on('routes');
            $table->foreign('hs_code')->references('code')->on('hs_codes')->onDelete('set null');
            $table->foreign('assigned_operator_id')->references('id')->on('ops_operator_profiles')->onDelete('set null');
            $table->index('shipper_id', 'idx_ops_cargo_shipper');
            $table->index('cargo_status', 'idx_ops_cargo_status');
        });

        Schema::create('ops_cargo_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('cargo_booking_id');
            $table->string('item_name');
            $table->string('hs_code', 15)->nullable();
            // CARTON, PALLET, WOODEN_CRATE, DRUM, BUNDLE
            $table->string('package_type', 50)->default('CARTON');
            $table->integer('quantity')->default(1);
            $table->decimal('weight_kg', 10, 2);
            $table->decimal('length_cm', 8, 2)->default(0);
            $table->decimal('width_cm', 8, 2)->default(0);
            $table->decimal('height_cm', 8, 2)->default(0);
            $table->decimal('volume_cbm', 10, 4)->default(0);
            $table->boolean('is_dangerous_good')->default(false);
            $table->timestamps();

            $table->foreign('cargo_booking_id')->references('id')->on('ops_cargo_bookings')->onDelete('cascade');
            $table->foreign('hs_code')->references('code')->on('hs_codes')->onDelete('set null');
        });

        Schema::create('ops_manifests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Format: MMK-JKT-SUB-20261106
            $table->string('manifest_number', 50)->unique();
            $table->string('origin_port_id', 10);
            $table->string('destination_port_id', 10);
            $table->string('vessel_or_vehicle_name');
            $table->string('voyage_number', 50);
            $table->timestamp('departure_eta');
            $table->timestamp('arrival_eta');
            // PREPARING, LOCKED_SEALED, SAILING, ARRIVED, UNLOADED
            $table->string('status', 50)->default('PREPARING');
            $table->timestamps();

            $table->foreign('origin_port_id')->references('id')->on('ports');
            $table->foreign('destination_port_id')->references('id')->on('ports');
        });

        Schema::create('ops_manifest_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('manifest_id');
            $table->uuid('cargo_booking_id');
            // Format ISO 6346: SRAU-728192-0
            $table->string('container_serial_number', 20);
            // DRY_20FT, DRY_40FT, REEFER_40HC
            $table->string('container_type', 50)->default('DRY_20FT');
            $table->string('seal_number', 50);
            $table->timestamps();

            $table->foreign('manifest_id')->references('id')->on('ops_manifests')->onDelete('cascade');
            $table->foreign('cargo_booking_id')->references('id')->on('ops_cargo_bookings');
            $table->index('container_serial_number', 'idx_ops_manifest_items_container');
        });

        Schema::create('ops_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('cargo_booking_id');
            $table->string('event_name');
            $table->string('current_status', 50);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->uuid('operator_id')->nullable();
            $table->timestamp('event_timestamp')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('cargo_booking_id')->references('id')->on('ops_cargo_bookings')->onDelete('cascade');
            $table->foreign('operator_id')->references('id')->on('ops_operator_profiles')->onDelete('set null');
            $table->index('cargo_booking_id', 'idx_ops_tracking_cargo');
            $table->index('event_timestamp', 'idx_ops_tracking_time');
        });

        Schema::create('ops_incident_escalations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Format: ESC-20261106-004
            $table->string('incident_ticket_no', 50)->unique();
            $table->uuid('cargo_booking_id');
            $table->uuid('reporter_operator_id');
            // FIELD_COST, DAMAGE_CLAIM, SLA_BREACH, WEATHER_HOLD
            $table->string('incident_type', 50);
            $table->decimal('requested_amount', 14, 2)->nullable();
            $table->string('bap_document_path')->nullable();
            // PENDING_REVIEW, APPROVED_BY_ADMIN, REJECTED, INSURANCE_PROCESSED
            $table->string('approval_status', 50)->default('PENDING_REVIEW');
            $table->text('admin_notes')->nullable();
            $table->integer('sla_response_minutes')->default(0);
            $table->timestamps();

            $table->foreign('cargo_booking_id')->references('id')->on('ops_cargo_bookings')->onDelete('cascade');
            $table->foreign('reporter_operator_id')->references('id')->on('ops_operator_profiles');
        });

        Schema::create('ops_epods', function (Blueprint $table) {
            $table->id();
            $table->uuid('cargo_booking_id')->unique();
            $table->string('recipient_name');
            $table->string('recipient_phone', 50);
            $table->string('signature_image_path')->nullable();
            $table->string('photo_proof_path')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('confirmed_at')->useCurrent();
            $table->timestamps();

            $table->foreign('cargo_booking_id')->references('id')->on('ops_cargo_bookings')->onDelete('cascade');
        });

        Schema::create('ops_fleet_telemetry', function (Blueprint $table) {
            $table->id();
            $table->uuid('manifest_id');
            $table->string('device_id', 100);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_knots', 6, 2)->default(0);
            $table->decimal('heading_degrees', 5, 2)->default(0);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('manifest_id')->references('id')->on('ops_manifests')->onDelete('cascade');
            $table->index('manifest_id', 'idx_ops_telemetry_manifest');
            $table->index('recorded_at', 'idx_ops_telemetry_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_fleet_telemetry');
        Schema::dropIfExists('ops_epods');
        Schema::dropIfExists('ops_incident_escalations');
        Schema::dropIfExists('ops_tracking_events');
        Schema::dropIfExists('ops_manifest_items');
        Schema::dropIfExists('ops_manifests');
        Schema::dropIfExists('ops_cargo_items');
        Schema::dropIfExists('ops_cargo_bookings');
        Schema::dropIfExists('ops_operator_profiles');
    }
};
