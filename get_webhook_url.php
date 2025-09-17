<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get the correct URL based on environment
$env = env('APP_ENV', 'local');
$webhookUrl = '';

if ($env === 'production') {
    $baseUrl = env('APP_LIVE_URL', env('APP_URL'));
} else {
    $baseUrl = env('APP_URL', 'http://127.0.0.1:8000');
}

// Ensure the URL has the correct port for local development
if ($env !== 'production' && !str_contains($baseUrl, ':8000')) {
    $baseUrl .= ':8000';
}

$webhookUrl = $baseUrl . '/api/lorawan/webhook';

echo "Environment: " . $env . "\n";
echo "Base URL: " . $baseUrl . "\n";
echo "LoRaWAN Webhook URL: " . $webhookUrl . "\n";
echo "\nConfigure this URL in The Things Stack console:\n";
echo $webhookUrl . "\n";
