<?php
// Standalone PHP test script to diagnose environment
header('Content-Type: text/plain');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "PHP VERSION: " . PHP_VERSION . "\n";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "CURRENT USER: " . get_current_user() . " (UID: " . posix_getuid() . ")\n\n";

echo "--- Directory Permissions ---\n";
$paths = [
    '../.env',
    '../bootstrap/cache',
    '../storage',
    '../storage/logs',
    '../vendor/autoload.php'
];

foreach ($paths as $path) {
    $abs = realpath($path);
    if ($abs) {
        $perms = decoct(fileperms($abs) & 0777);
        $owner = posix_getpwuid(fileowner($abs))['name'];
        echo "$path: EXISTS, perms=$perms, owner=$owner, readable=" . (is_readable($abs) ? "YES" : "NO") . ", writeable=" . (is_writable($abs) ? "YES" : "NO") . "\n";
    } else {
        echo "$path: DOES NOT EXIST\n";
    }
}

echo "\n--- Environment Variables ---\n";
$envVars = $_ENV ?: ($_SERVER ?: []);
foreach (getenv() as $key => $val) {
    $envVars[$key] = $val;
}
ksort($envVars);
foreach ($envVars as $key => $value) {
    if (preg_match('/(PASS|KEY|SECRET|TOKEN|AUTH|CREDENTIAL)/i', $key)) {
        echo "$key: ********\n";
    } else {
        echo "$key: $value\n";
    }
}

echo "\n--- DB Connection Test ---\n";
$env = [];
if (file_exists('../.env')) {
    // Suppress warning if parsing fails due to syntax
    $env = @parse_ini_file('../.env') ?: [];
}
$host = getenv('DB_HOST') ?: ($env['DB_HOST'] ?? '');
$db = getenv('DB_DATABASE') ?: ($env['DB_DATABASE'] ?? '');
$user = getenv('DB_USERNAME') ?: ($env['DB_USERNAME'] ?? '');
$pass = getenv('DB_PASSWORD') ?: ($env['DB_PASSWORD'] ?? '');

echo "Host: $host, DB: $db, User: $user\n";
if ($host && $db) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        echo "DB Connection: SUCCESS!\n";
    } catch (Exception $e) {
        echo "DB Connection: FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo "DB Config is empty.\n";
}

echo "\n--- Last 30 lines of Laravel daily log ---\n";
$logDir = '../storage/logs';
if (is_dir($logDir)) {
    $files = glob("$logDir/laravel-*.log");
    if ($files) {
        // Get the latest log file
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $latest = $files[0];
        echo "Latest log: " . basename($latest) . "\n";
        $lines = array_slice(file($latest), -30);
        foreach ($lines as $line) {
            echo $line;
        }
    } else {
        echo "No laravel-*.log files found.\n";
    }
} else {
    echo "Log directory not found.\n";
}
