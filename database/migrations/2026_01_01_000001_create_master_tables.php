<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel master: users, addresses, companies, ports, routes, hs_codes, exchange_rates
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('username')->unique()->nullable();
            $table->string('email')->unique();
            $table->string('phone', 50)->nullable();
            $table->string('password');
            // RETAIL_CUSTOMER, CORPORATE_USER, OPERATOR_USER, ADMIN_USER
            $table->string('user_type', 50)->default('RETAIL_CUSTOMER');
            // SUPER_ADMIN, FINANCE_ADMIN, DISPATCH_ADMIN, COMPLIANCE_ADMIN
            $table->string('admin_role', 50)->nullable();
            // ACTIVE, BOOKING_LOCKED, BLACKLISTED, SUSPENDED
            $table->string('status', 50)->default('ACTIVE');
            $table->string('two_factor_secret')->nullable();
            $table->timestamp('two_factor_enabled_at')->nullable();
            $table->string('nik', 30)->nullable();
            // UNVERIFIED, PENDING, VERIFIED, REJECTED
            $table->string('kyc_aml_status', 50)->default('UNVERIFIED');
            $table->timestamp('ktp_verified_at')->nullable();
            $table->string('ktp_file_url')->nullable();
            $table->string('business_type', 50)->nullable();
            // Timezone customer — auto-detect browser saat login pertama
            $table->string('timezone', 50)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['user_type', 'status'], 'idx_users_type_status');
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            // personal, company, billing, warehouse
            $table->string('type', 50)->default('personal');
            $table->string('country');
            $table->string('country_code', 10)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->text('street');
            $table->string('postal_code', 20)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id', 'idx_addresses_user_id');
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('company_name');
            $table->string('nib', 50)->unique()->nullable();
            $table->string('npwp', 50)->unique()->nullable();
            $table->text('company_address')->nullable();
            $table->string('pic_name')->nullable();
            $table->string('pic_phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('industry', 100)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id', 'idx_companies_user_id');
        });

        Schema::create('ports', function (Blueprint $table) {
            // ID port pakai kode IATA/ICODE: IDJKT, IDSUB, SGSIN, dsb
            $table->string('id', 10)->primary();
            $table->string('name');
            $table->string('city', 100);
            $table->string('country_code', 5)->default('ID');
            // SEAPORT, AIRPORT, LAND_HUB
            $table->string('port_type', 50)->default('SEAPORT');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('origin_port_id', 10);
            $table->string('destination_port_id', 10);
            // SEA, AIR, LAND
            $table->string('transport_mode', 20)->default('SEA');
            $table->integer('transit_days')->default(1);
            $table->decimal('base_rate_idr', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('origin_port_id')->references('id')->on('ports');
            $table->foreign('destination_port_id')->references('id')->on('ports');
            $table->index(['origin_port_id', 'destination_port_id'], 'idx_routes_od');
        });

        Schema::create('hs_codes', function (Blueprint $table) {
            $table->string('code', 15)->primary();
            $table->string('description');
            $table->string('category', 100)->nullable();
            $table->boolean('is_dangerous')->default(false);
            $table->boolean('requires_special_handling')->default(false);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            // Format: IDR_USD, IDR_SGD, dsb
            $table->string('currency_pair', 20);
            $table->decimal('rate', 14, 4);
            $table->timestamp('locked_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['currency_pair', 'expires_at'], 'idx_exchange_rates_pair');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('hs_codes');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('ports');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('users');
    }
};
