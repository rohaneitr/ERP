<?php
/**
 * Temporary Diagnostic Script to reset admin/superadmin passwords
 * Delete immediately after use!
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

try {
    // 2. Count users
    $userCount = DB::table('users')->count();
    echo "<p>User count in DB: $userCount</p>";

    // 3. Create superadmin if not exists
    $superadmin = DB::table('users')->where('username', 'superadmin')->first();
    if (!$superadmin) {
        echo "<p>Creating superadmin user...</p>";
        $now = date('Y-m-d H:i:s');
        $userId = DB::table('users')->insertGetId([
            'surname' => 'Mr.',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('123456'),
            'language' => 'en',
            'business_id' => null,
            'created_at' => $now,
            'updated_at' => $now
        ]);
        echo "<p style='color:green;'>Superadmin created with ID: $userId</p>";
    } else {
        echo "<p>Superadmin already exists. Resetting password...</p>";
        DB::table('users')->where('username', 'superadmin')->update([
            'password' => Hash::make('123456')
        ]);
        echo "<p style='color:green;'>Superadmin password reset to 123456.</p>";
    }

    // 4. Create admin user if not exists
    $admin = DB::table('users')->where('username', 'admin')->first();
    if (!$admin) {
        echo "<p>Creating admin user...</p>";
        // Check if business exists
        $businessExists = DB::table('business')->where('id', 1)->exists();
        if (!$businessExists) {
            echo "<p>Business ID 1 does not exist. Creating default business...</p>";
            DB::table('business')->insert([
                'id' => 1,
                'name' => 'Default Business',
                'currency_id' => 2, // USD
                'start_date' => '2026-01-01',
                'time_zone' => 'Asia/Dhaka',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            echo "<p style='color:green;'>Default business created.</p>";
        }

        $userId = DB::table('users')->insertGetId([
            'surname' => 'Mr',
            'first_name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('123456'),
            'language' => 'en',
            'business_id' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "<p style='color:green;'>Admin created with ID: $userId</p>";
    } else {
        echo "<p>Admin already exists. Resetting password...</p>";
        DB::table('users')->where('username', 'admin')->update([
            'password' => Hash::make('123456')
        ]);
        echo "<p style='color:green;'>Admin password reset to 123456.</p>";
    }

} catch (\Exception $e) {
    echo "<h2>Error executing script:</h2><pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
