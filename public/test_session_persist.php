<?php
session_start();
$_SESSION['persist_test'] = 'PHP Native Session works!';

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Save test data to Laravel session
session(['laravel_persist_test' => 'Laravel Session works!']);
session()->save();

echo "Session set. <a href='test_session_persist_read.php'>Click here to read</a><br>";
echo "PHP Session ID: " . session_id() . "<br>";
echo "Laravel Session ID: " . session()->getId() . "<br>";
