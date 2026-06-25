<?php
if (!isset($_GET['token']) || $_GET['token'] !== 'diag123') {
    header('HTTP/1.0 403 Forbidden');
    echo "Unauthorized";
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

echo "=== System Info ===\n";
echo "OS: " . PHP_OS . "\n";
echo "Current User: " . getenv('USER') . " / " . getmyuid() . "\n";
echo "Storage Path: " . realpath(__DIR__ . '/../storage') . "\n";

echo "\n=== Testing Session Write ===\n";
$sessionPath = __DIR__ . '/../storage/framework/sessions';
echo "Session directory: " . realpath($sessionPath) . "\n";
echo "Exists: " . (is_dir($sessionPath) ? 'Yes' : 'No') . "\n";
echo "Writable: " . (is_writable($sessionPath) ? 'Yes' : 'No') . "\n";
$testFile = $sessionPath . '/test_write.txt';
$writeResult = @file_put_contents($testFile, 'test');
if ($writeResult !== false) {
    echo "Write test: Success\n";
    @unlink($testFile);
} else {
    echo "Write test: FAILED\n";
}

echo "\n=== Listing session files ===\n";
$files = glob($sessionPath . '/*');
echo "Count: " . count($files) . "\n";
foreach (array_slice($files, 0, 10) as $f) {
    echo " - " . basename($f) . " (" . filesize($f) . " bytes, modified: " . date('Y-m-d H:i:s', filemtime($f)) . ")\n";
}

echo "\n=== Database connection test ===\n";
try {
    // Boot Laravel
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    // Boot the application
    $app->bootstrapWith([
        \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
        \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
        \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
        \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
        \Illuminate\Foundation\Bootstrap\BootProviders::class,
    ]);

    $users = \DB::table('users')->select('id', 'username', 'email')->get();
    echo "Users count: " . $users->count() . "\n";
    foreach ($users as $u) {
        echo " - ID: {$u->id}, Username: {$u->username}, Email: {$u->email}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
