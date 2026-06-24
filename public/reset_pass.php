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
    $users = DB::table('users')->select('id', 'username', 'email')->get();
    echo "<h2>Existing Users in Database:</h2><pre>";
    foreach ($users as $user) {
        echo "ID: {$user->id} | Username: {$user->username} | Email: {$user->email}\n";
    }
    echo "</pre>";

    // Reset password for superadmin
    $superadminExists = DB::table('users')->where('username', 'superadmin')->exists();
    if ($superadminExists) {
        $affected = DB::table('users')
            ->where('username', 'superadmin')
            ->update(['password' => Hash::make('123456')]);
        echo "<p style='color:green;'>Superadmin password reset status: affected rows = {$affected}</p>";
    } else {
        echo "<p style='color:red;'>Superadmin user does not exist in DB.</p>";
    }

    // Reset password for admin
    $adminExists = DB::table('users')->where('username', 'admin')->exists();
    if ($adminExists) {
        $affected2 = DB::table('users')
            ->where('username', 'admin')
            ->update(['password' => Hash::make('123456')]);
        echo "<p style='color:green;'>Admin password reset status: affected rows = {$affected2}</p>";
    } else {
        echo "<p style='color:red;'>Admin user does not exist in DB.</p>";
    }

} catch (\Exception $e) {
    echo "<h2>Error executing script:</h2><pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
