<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('password_hash');
            $table->string('verification_token', 64)->nullable()->unique();
            $table->enum('verification_method', ['email', 'phone', 'none'])->default('email');
            $table->timestamp('verified_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('requested_plan_id')->nullable();
            $table->unsignedInteger('business_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('requested_plan_id')->references('id')->on('subscription_plans')->onDelete('set null');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_registrations');
    }
};
