<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('password_reset_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('token_hash', 64);
            $table->enum('method', ['email', 'sms', 'admin'])->default('email');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'used_at']);
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 191)->index();
            $table->string('ip_address', 45)->index();
            $table->boolean('successful')->default(false);
            $table->string('failure_reason', 100)->nullable();
            $table->timestamp('attempted_at')->useCurrent();

            $table->index(['identifier', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
        });

        Schema::create('account_lockouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->timestamp('locked_at')->useCurrent();
            $table->timestamp('locked_until')->nullable();
            $table->string('reason', 191)->nullable();
            $table->string('unlock_token', 64)->nullable()->unique();
            $table->timestamp('unlocked_at')->nullable();
            $table->unsignedInteger('unlocked_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'unlocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_lockouts');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('password_reset_attempts');
    }
};
