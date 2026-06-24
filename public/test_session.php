<?php
/**
 * Temporary diagnostic script for session testing
 * Delete immediately after use!
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "<h3>Laravel Session Write Test</h3>";
    $sessionPath = storage_path('framework/sessions');
    echo "Session Path: $sessionPath <br>";
    echo "Is Writable: " . (is_writable($sessionPath) ? 'YES' : 'NO') . "<br>";
    
    echo "<h3>Specific Session & Request Settings</h3>";
    echo "session.secure: " . (config('session.secure') ? 'TRUE' : 'FALSE') . "<br>";
    echo "session.domain: " . var_export(config('session.domain'), true) . "<br>";
    echo "session.same_site: " . var_export(config('session.same_site'), true) . "<br>";
    echo "session.cookie: " . var_export(config('session.cookie'), true) . "<br>";
    echo "Request Is Secure: " . (request()->isSecure() ? 'TRUE' : 'FALSE') . "<br>";
    echo "Request URL: " . request()->fullUrl() . "<br>";
    echo "Header X-Forwarded-Proto: " . request()->header('X-Forwarded-Proto') . "<br>";
    echo "Header X-Forwarded-For: " . request()->header('X-Forwarded-For') . "<br>";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
