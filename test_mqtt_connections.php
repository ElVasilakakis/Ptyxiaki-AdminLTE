<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MqttBroker;
use App\Services\MqttConnectionTester;

echo "=== Testing MQTT Connections with New Service ===\n\n";

$tester = new MqttConnectionTester();
$brokers = MqttBroker::all();

if ($brokers->isEmpty()) {
    echo "No MQTT brokers found in database.\n";
    exit;
}

foreach ($brokers as $broker) {
    echo "Testing broker: {$broker->name} ({$broker->host}:{$broker->port})\n";
    echo "Type: {$broker->type}\n";
    echo "SSL: " . ($broker->use_ssl ? 'Yes' : 'No') . "\n";
    echo "Username: " . ($broker->username ? $broker->username : 'None') . "\n";
    echo "---\n";

    // Test with new service
    $result = $tester->testConnection($broker);

    if ($result['success']) {
        echo "✅ " . $result['message'] . "\n";
        if (isset($result['details'])) {
            echo "   Details:\n";
            echo "   - Host: " . $result['details']['host'] . "\n";
            echo "   - Port: " . $result['details']['port'] . "\n";
            echo "   - TLS: " . ($result['details']['tls'] ? 'Yes' : 'No') . "\n";
            echo "   - Auth: " . ($result['details']['auth'] ? 'Yes' : 'No') . "\n";
            echo "   - Pub/Sub Test: " . ($result['details']['pubsub_test'] ? 'Passed' : 'Failed') . "\n";
        }
    } else {
        echo "❌ " . $result['message'] . "\n";
        if (isset($result['details'])) {
            echo "   Error Details:\n";
            echo "   - Host: " . $result['details']['host'] . "\n";
            echo "   - Port: " . $result['details']['port'] . "\n";
            if (isset($result['details']['error_type'])) {
                echo "   - Error Type: " . $result['details']['error_type'] . "\n";
            }
        }
    }

    echo "\n";

    // Also test socket connection as fallback
    echo "Socket test (fallback): ";
    $socketResult = $tester->testSocketConnection($broker);
    echo ($socketResult['success'] ? "✅" : "❌") . " " . $socketResult['message'] . "\n";

    echo "===========================================\n\n";
}

echo "=== Testing All Brokers at Once ===\n\n";

$allResults = $tester->testAllBrokers();

foreach ($allResults as $brokerId => $result) {
    echo "Broker: {$result['broker']}\n";
    echo "Result: " . ($result['result']['success'] ? "✅ Success" : "❌ Failed") . "\n";
    echo "Message: {$result['result']['message']}\n";
    echo "---\n";
}
