<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the mobile_activations table for the FastPos licensing system.
 *
 * Each row represents one active device licence binding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_activations', function (Blueprint $table) {
            $table->id();

            // License key (UUID) assigned per purchase / customer
            $table->string('license_key', 64)->index();

            // Hardware-bound device fingerprint (SHA-256)
            $table->string('device_fingerprint', 64)->index();

            // Human-readable device info
            $table->string('device_name', 255)->nullable();
            $table->string('device_brand', 100)->nullable();
            $table->string('device_model', 100)->nullable();
            $table->string('platform', 20)->default('android'); // android | ios

            // App version at activation time
            $table->string('app_version', 30)->nullable();

            // Association to the business (tenant)
            $table->unsignedInteger('business_id')->nullable();
            $table->foreign('business_id')->references('id')->on('business')->nullOnDelete();

            // Who activated (if using user-level auth)
            $table->unsignedInteger('activated_by')->nullable();
            $table->foreign('activated_by')->references('id')->on('users')->nullOnDelete();

            // Licensing state
            $table->enum('status', ['active', 'suspended', 'expired', 'revoked'])->default('active')->index();

            // Expiry — null = lifetime
            $table->timestamp('expires_at')->nullable()->index();

            // Activity tracking
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_ip', 45)->nullable();
            $table->unsignedInteger('sync_count')->default(0);

            // Admin notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // One fingerprint can only have one active binding per license
            $table->unique(['license_key', 'device_fingerprint'], 'unique_device_binding');
        });

        // License keys table — tracks issued keys and their limits
        Schema::create('mobile_license_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->unsignedInteger('business_id')->nullable();
            $table->foreign('business_id')->references('id')->on('business')->nullOnDelete();
            $table->unsignedTinyInteger('max_devices')->default(1);
            $table->enum('plan', ['trial', 'basic', 'professional', 'enterprise'])->default('basic');
            $table->timestamp('valid_from')->useCurrent();
            $table->timestamp('valid_until')->nullable(); // null = lifetime
            $table->enum('status', ['active', 'suspended', 'expired'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_activations');
        Schema::dropIfExists('mobile_license_keys');
    }
};
