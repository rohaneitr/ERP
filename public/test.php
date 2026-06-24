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

echo "\n--- DB Connection Test ---\n";
if (file_exists('../.env')) {
    $env = parse_ini_file('../.env');
    $host = $env['DB_HOST'] ?? '';
    $db = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    
    echo "Host: $host, DB: $db, User: $user\n";
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRORS => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        echo "DB Connection: SUCCESS!\n";
    } catch (Exception $e) {
        echo "DB Connection: FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo ".env not found\n";
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
