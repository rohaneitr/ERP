<?php
session_start();
echo "PHP session persist_test: " . ($_SESSION['persist_test'] ?? 'NOT FOUND') . "<br>";
echo "PHP Session ID: " . session_id() . "<br>";

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Laravel Session laravel_persist_test: " . (session('laravel_persist_test') ?? 'NOT FOUND') . "<br>";
echo "Laravel Session ID: " . session()->getId() . "<br>";
