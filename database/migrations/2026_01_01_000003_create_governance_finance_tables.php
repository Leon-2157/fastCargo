<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel governance & finance: corporate credits, escrow, revenue splits, invoices, payment, invitations, gateway sessions, settings, audit
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adm_corporate_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->unique();
            $table->decimal('credit_limit_amount', 16, 2)->default(0);
            $table->decimal('used_credit_amount', 16, 2)->default(0);
            // 30, 45, 60 hari
            $table->integer('top_days')->default(30);
            // PENDING_VERIFICATION, VERIFIED_APPROVED, REJECTED
            $table->string('kyc_status', 50)->default('PENDING_VERIFICATION');
            $table->string('bank_guarantee_ref', 100)->nullable();
            // HEALTHY, SP1_ISSUED, SP2_LOCKED, SP3_LEGAL_COLLECTION
            $table->string('debt_enforcement_stage', 50)->default('HEALTHY');
            $table->uuid('verified_by_admin_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('verified_by_admin_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('adm_escrow_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cargo_booking_id')->unique();
            $table->decimal('total_paid_amount', 16, 2);
            // Milestone 1: 30% — saat GATE_IN
            $table->decimal('milestone_1_pickup_amount', 16, 2);
            $table->timestamp('milestone_1_released_at')->nullable();
            // Milestone 2: 40% — saat SAILING
            $table->decimal('milestone_2_sailing_amount', 16, 2);
            $table->timestamp('milestone_2_released_at')->nullable();
            // Milestone 3: 30% — saat DELIVERED + ePOD confirmed
            $table->decimal('milestone_3_delivered_amount', 16, 2);
            $table->timestamp('milestone_3_released_at')->nullable();
            // HELD_IN_ESCROW, PARTIALLY_RELEASED, FULLY_SETTLED, DISPUTE_FROZEN
            $table->string('escrow_status', 50)->default('HELD_IN_ESCROW');
            $table->timestamps();

            $table->foreign('cargo_booking_id')->references('id')->on('ops_cargo_bookings')->onDelete('cascade');
        });

        Schema::create('adm_revenue_splits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cargo_booking_id')->unique();
            // Platform fee 10-15%
            $table->decimal('platform_fee_amount', 14, 2);
            // Operator share 60-80%
            $table->decimal('operator_share_amount', 14, 2);
            $table->decimal('tax_and_port_dues', 14, 2);
            $table->decimal('net_platform_profit', 14, 2);
            $table->timestamps();

            $table->foreign('cargo_booking_id')->references('id')->on('ops_cargo_bookings')->onDelete('cascade');
        });

        Schema::create('adm_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Format: INV-B2B-20261106-008
            $table->string('invoice_number', 50)->unique();
            $table->uuid('company_id');
            $table->uuid('cargo_booking_id')->nullable();
            $table->decimal('total_invoice_amount', 16, 2);
            $table->string('currency', 10)->default('IDR');
            $table->decimal('exchange_rate_locked', 14, 4)->default(1.0000);
            $table->date('due_date');
            // UNPAID, PARTIALLY_PAID, OVERDUE, PAID_SETTLED
            $table->string('payment_status', 50)->default('UNPAID');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('cargo_booking_id')->references('id')->on('ops_cargo_bookings')->onDelete('set null');
            $table->index('company_id', 'idx_adm_invoices_company');
            $table->index(['due_date', 'payment_status'], 'idx_adm_invoices_due');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->uuid('payer_user_id');
            // DANA, MIDTRANS, XENDIT, MANUAL_TRANSFER
            $table->string('payment_gateway', 50)->default('DANA');
            // QRIS, BCA_VA, MANDIRI_VA, BANK_TRANSFER, dsb
            $table->string('payment_channel', 50);
            // Order ID dari gateway
            $table->string('transaction_ref', 100)->unique();
            $table->decimal('amount', 16, 2);
            // PENDING, SETTLEMENT, EXPIRED, FAILED, REFUNDED
            $table->string('payment_status', 50)->default('PENDING');
            $table->json('raw_gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('adm_invoices')->onDelete('cascade');
            $table->foreign('payer_user_id')->references('id')->on('users');
            $table->index('payment_status', 'idx_payment_tx_status');
        });

        // Tabel dual-purpose: aplikasi dari landing (PENDING_REVIEW) + invitation dari admin (INVITATION_SENT)
        Schema::create('adm_operator_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invitation_token', 64)->unique()->nullable();
            $table->string('email');
            $table->string('phone', 50);
            $table->string('name');
            // Nama hub/perusahaan dari form landing
            $table->string('hub_name')->nullable();
            $table->string('city')->nullable();
            // PENDING_REVIEW, INVITATION_SENT, REGISTERED, REJECTED, EXPIRED
            $table->string('status', 50)->default('PENDING_REVIEW');
            $table->string('assigned_hub_id', 10)->nullable();
            $table->string('assigned_role_tier', 50)->default('OPERATOR_DOMESTIC');
            $table->uuid('invited_by_admin_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('assigned_hub_id')->references('id')->on('ports')->onDelete('set null');
            $table->foreign('invited_by_admin_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('adm_gateway_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operator_user_id');
            $table->string('session_token', 64)->unique();
            $table->string('ip_address', 45);
            $table->string('vpn_region', 100)->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('operator_user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('adm_system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Immutable — tidak ada update/delete, hanya insert
        Schema::create('adm_immutable_audit_trails', function (Blueprint $table) {
            $table->id();
            $table->uuid('actor_id')->nullable();
            $table->string('actor_type', 50)->nullable();
            $table->string('action', 100);
            $table->string('target_type', 100)->nullable();
            $table->string('target_id', 36)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            // Tidak ada updated_at — immutable
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adm_immutable_audit_trails');
        Schema::dropIfExists('adm_system_settings');
        Schema::dropIfExists('adm_gateway_sessions');
        Schema::dropIfExists('adm_operator_invitations');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('adm_invoices');
        Schema::dropIfExists('adm_revenue_splits');
        Schema::dropIfExists('adm_escrow_ledgers');
        Schema::dropIfExists('adm_corporate_credits');
    }
};
