<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Device;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;

echo "=== HiveMQ Connection Test for Device ID 6 ===\n\n";

// Get device configuration
$device = Device::find(6);

if (!$device) {
    echo "❌ Device ID 6 not found!\n";
    exit(1);
}

echo "📋 Device Configuration:\n";
echo "   - Name: {$device->name}\n";
echo "   - Device ID: {$device->device_id}\n";
echo "   - Host: {$device->mqtt_host}\n";
echo "   - Port: {$device->port}\n";
echo "   - SSL: " . ($device->use_ssl ? 'Yes' : 'No') . "\n";
echo "   - Username: {$device->username}\n";
echo "   - Password: " . ($device->password ? 'Set (length: ' . strlen($device->password) . ')' : 'None') . "\n";
echo "   - Broker Type: {$device->connection_broker}\n";
echo "   - Topics: " . implode(', ', $device->mqtt_topics) . "\n";
echo "   - Keep Alive: {$device->keepalive}\n\n";

// Test connection
echo "🔌 Testing HiveMQ Connection...\n";

try {
    $clientId = 'test_hivemq_' . time() . '_' . substr(md5(uniqid()), 0, 8);
    echo "   - Client ID: {$clientId}\n";
    
    // Create connection settings
    $connectionSettings = new ConnectionSettings();
    
    if ($device->username) {
        $connectionSettings->setUsername($device->username);
        echo "   - Username set: {$device->username}\n";
    }
    
    if ($device->password) {
        $connectionSettings->setPassword($device->password);
        echo "   - Password set: " . str_repeat('*', strlen($device->password)) . "\n";
    }
    
    // Configure SSL/TLS
    if ($device->use_ssl) {
        $connectionSettings->setUseTls(true);
        $connectionSettings->setTlsVerifyPeer(false);
        $connectionSettings->setTlsVerifyPeerName(false);
        $connectionSettings->setTlsSelfSignedAllowed(true);
        echo "   - SSL/TLS enabled with permissive settings\n";
    }
    
    // Set keepalive and timeout
    $connectionSettings->setKeepAliveInterval($device->keepalive ?: 60);
    $connectionSettings->setConnectTimeout(15); // 15 second timeout
    
    echo "   - Keep Alive: " . ($device->keepalive ?: 60) . "s\n";
    echo "   - Connection Timeout: 15s\n\n";
    
    echo "⏳ Attempting connection to {$device->mqtt_host}:{$device->port}...\n";
    $startTime = microtime(true);
    
    // Create MQTT client
    $mqtt = new MqttClient($device->mqtt_host, $device->port, $clientId, MqttClient::MQTT_3_1_1);
    
    // Attempt connection
    $mqtt->connect($connectionSettings, true);
    
    $endTime = microtime(true);
    $connectionTime = round(($endTime - $startTime) * 1000, 2);
    
    echo "✅ Successfully connected to HiveMQ! ({$connectionTime}ms)\n\n";
    
    // Test subscription
    echo "📡 Testing topic subscription...\n";
    foreach ($device->mqtt_topics as $topic) {
        echo "   - Subscribing to: {$topic}\n";
        $mqtt->subscribe($topic, function ($receivedTopic, $message) {
            echo "   📨 Received message on '{$receivedTopic}': " . substr($message, 0, 100) . "\n";
        }, 0);
    }
    
    echo "✅ Successfully subscribed to topics!\n\n";
    
    // Test publishing (optional)
    echo "📤 Testing message publishing...\n";
    $testTopic = $device->mqtt_topics[0] ?? 'test/topic';
    $testMessage = json_encode([
        'test' => true,
        'timestamp' => time(),
        'client_id' => $clientId
    ]);
    
    $mqtt->publish($testTopic, $testMessage, 0);
    echo "✅ Test message published to '{$testTopic}'\n\n";
    
    // Listen for messages briefly
    echo "👂 Listening for messages for 5 seconds...\n";
    $listenStart = time();
    while ((time() - $listenStart) < 5) {
        $mqtt->loop(false, true);
        usleep(100000); // 100ms
    }
    
    // Disconnect
    echo "\n🔌 Disconnecting...\n";
    $mqtt->disconnect();
    echo "✅ Disconnected successfully!\n\n";
    
    // Update device status
    $device->update([
        'status' => 'online',
        'last_seen_at' => now()
    ]);
    
    echo "📊 Device status updated to 'online'\n";
    echo "🎉 HiveMQ connection test completed successfully!\n";
    
} catch (MqttClientException $e) {
    echo "❌ MQTT Connection Error: " . $e->getMessage() . "\n";
    echo "   Error Code: " . $e->getCode() . "\n\n";
    
    // Update device status
    $device->update([
        'status' => 'error',
        'last_seen_at' => now()
    ]);
    
    echo "📊 Device status updated to 'error'\n";
    
    // Provide troubleshooting suggestions
    echo "🔧 Troubleshooting Suggestions:\n";
    echo "   1. Verify HiveMQ Cloud cluster is active\n";
    echo "   2. Check username and password are correct\n";
    echo "   3. Ensure client is authorized in HiveMQ Cloud console\n";
    echo "   4. Verify network connectivity to HiveMQ Cloud\n";
    echo "   5. Check if port {$device->port} is accessible\n";
    
    if (strpos($e->getMessage(), 'timeout') !== false) {
        echo "   6. Connection timeout - may indicate network/firewall issues\n";
    }
    
    if (strpos($e->getMessage(), 'authentication') !== false || strpos($e->getMessage(), 'unauthorized') !== false) {
        echo "   6. Authentication failed - check credentials\n";
    }
    
    exit(1);
    
} catch (Exception $e) {
    echo "❌ General Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    
    $device->update([
        'status' => 'error',
        'last_seen_at' => now()
    ]);
    
    echo "📊 Device status updated to 'error'\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";
