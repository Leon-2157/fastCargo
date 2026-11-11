<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Laravel infrastructure tables + tabel tambahan: geonames_places, notifications, sessions, cache, jobs
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->uuid('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // Data wilayah ASEAN dari GeoNames dump — offline, tidak perlu API call
        Schema::create('geonames_places', function (Blueprint $table) {
            $table->unsignedInteger('geoname_id')->primary();
            $table->string('name', 200);
            $table->string('ascii_name', 200);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            // A=Admin, P=Populated, S=Spot, H=Hydrographic, T=Terrain
            $table->char('feature_class', 1);
            // PPL=city, ADM1=provinsi, AIRP=airport, SEAP=seaport, dsb
            $table->string('feature_code', 10);
            $table->char('country_code', 2);
            $table->string('admin1_code', 20)->nullable();
            $table->string('admin2_code', 80)->nullable();
            $table->unsignedInteger('population')->default(0);
            $table->string('timezone', 40)->nullable();

            $table->index(['country_code', 'feature_code'], 'idx_country_feature');
            $table->index(['latitude', 'longitude'], 'idx_coords');
            $table->fullText(['name', 'ascii_name'], 'ft_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geonames_places');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('password_reset_tokens');
    }
};
