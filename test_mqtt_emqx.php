<?php

require_once 'vendor/autoload.php';

use Bluerhinos\phpMQTT;

// EMQX broker configuration
$host = 'broker.emqx.io';
$port = 1883;
$username = 'mqttuser';
$password = 'mqttpass';
$clientId = 'test_client_' . time();

// Create MQTT client
$mqtt = new phpMQTT($host, $port, $clientId);

echo "Connecting to EMQX broker at {$host}:{$port}...\n";

if ($mqtt->connect(true, NULL, $username, $password)) {
    echo "✅ Connected successfully!\n";
    
    // Test topic (same as configured in your device)
    $topic = 'ESP32-DEV-001/sensors';
    
    // Test messages with different formats
    $testMessages = [
        // ESP32 format
        json_encode([
            'sensors' => [
                ['type' => 'temperature', 'value' => 25.6],
                ['type' => 'humidity', 'value' => 60.2],
                ['type' => 'light', 'value' => 75.0]
            ]
        ]),
        
        // Simple key-value format
        json_encode([
            'temperature' => 26.1,
            'humidity' => 58.5,
            'light' => 80.0,
            'timestamp' => time()
        ]),
        
        // Single sensor format
        json_encode([
            'sensor_type' => 'temperature',
            'value' => 24.8,
            'unit' => '°C'
        ])
    ];
    
    foreach ($testMessages as $index => $message) {
        echo "\n📤 Publishing test message " . ($index + 1) . " to topic: {$topic}\n";
        echo "Message: " . $message . "\n";
        
        if ($mqtt->publish($topic, $message, 0)) {
            echo "✅ Message published successfully!\n";
        } else {
            echo "❌ Failed to publish message!\n";
        }
        
        // Wait 2 seconds between messages
        sleep(2);
    }
    
    echo "\n🔌 Disconnecting...\n";
    $mqtt->close();
    echo "✅ Disconnected successfully!\n";
    
} else {
    echo "❌ Failed to connect to EMQX broker!\n";
}

echo "\nTest completed. Check your Laravel application to see if the sensor data was updated.\n";
