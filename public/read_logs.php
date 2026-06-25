<?php
if (!isset($_GET['token']) || $_GET['token'] !== 'diag123') {
    header('HTTP/1.0 403 Forbidden');
    echo "Unauthorized";
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

$logDir = __DIR__ . '/../storage/logs/';
if (!is_dir($logDir)) {
    echo "Log directory not found: $logDir\n";
    exit;
}

$files = glob($logDir . '*.log');
if (empty($files)) {
    echo "No log files found in $logDir\n";
    exit;
}

// Sort files by modified time (newest first)
usort($files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

$latestLogFile = $files[0];
echo "Reading latest log file: " . basename($latestLogFile) . "\n";
echo "File size: " . filesize($latestLogFile) . " bytes\n";
echo "==================================================\n\n";

$lines = file($latestLogFile);
$lastLines = array_slice($lines, -100);
echo implode("", $lastLines);
