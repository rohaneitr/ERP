<?php
/**
 * Temporary diagnostic script for session testing
 * Delete immediately after use!
 */

session_start();
echo "<h3>PHP Native Session Test</h3>";
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
} else {
    $_SESSION['test_counter']++;
}
echo "Counter: " . $_SESSION['test_counter'] . "<br>";

echo "<h3>Laravel Session Write Test</h3>";
try {
    require __DIR__.'/../vendor/autoload.php';
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    
    $sessionPath = storage_path('framework/sessions');
    echo "Session Path: $sessionPath <br>";
    echo "Is Writable: " . (is_writable($sessionPath) ? 'YES' : 'NO') . "<br>";
    echo "Owner ID: " . fileowner($sessionPath) . "<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($sessionPath)), -4) . "<br>";
    
    $testFile = $sessionPath . '/test_write.txt';
    $written = @file_put_contents($testFile, 'test');
    if ($written !== false) {
        echo "Write Test: SUCCESS<br>";
        @unlink($testFile);
    } else {
        echo "Write Test: FAILED<br>";
    }

    // Dump current session config
    echo "<h3>Session Config</h3><pre>";
    print_r(config('session'));
    echo "</pre>";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
