<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Contact
            $table->string('phone', 30)->nullable()->after('email');

            // Verification
            $table->timestamp('email_verified_at')->nullable()->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');

            // Security
            $table->boolean('must_change_password')->default(false)->after('phone_verified_at');
            $table->unsignedTinyInteger('login_attempts_count')->default(0)->after('must_change_password');
            $table->timestamp('locked_until')->nullable()->after('login_attempts_count');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            // Subscription & creation tracking
            $table->unsignedBigInteger('active_subscription_id')->nullable()->after('last_login_ip');
            $table->unsignedInteger('created_by_admin_id')->nullable()->after('active_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'email_verified_at', 'phone_verified_at',
                'must_change_password', 'login_attempts_count',
                'locked_until', 'last_login_at', 'last_login_ip',
                'active_subscription_id', 'created_by_admin_id',
            ]);
        });
    }
};
