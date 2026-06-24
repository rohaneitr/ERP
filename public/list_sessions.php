<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $sessionPath = storage_path('framework/sessions');
    echo "<h3>Files in $sessionPath:</h3><pre>";
    $files = scandir($sessionPath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $filePath = $sessionPath . '/' . $file;
        echo "$file | Size: " . filesize($filePath) . " | Modified: " . date('Y-m-d H:i:s', filemtime($filePath)) . "\n";
    }
    echo "</pre>";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
