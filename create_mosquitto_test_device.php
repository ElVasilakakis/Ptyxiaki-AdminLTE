<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Device;
use App\Models\User;

echo "🦟 Creating Test Mosquitto Device\n";
echo "=================================\n\n";

// Check if we have a user to assign the device to
$user = User::first();
if (!$user) {
    echo "❌ No users found in database. Please create a user first.\n";
    exit(1);
}

echo "👤 Using user: {$user->name} (ID: {$user->id})\n\n";

// Check if test device already exists
$existingDevice = Device::where('device_id', 'mosquitto_test_001')->first();
if ($existingDevice) {
    echo "ℹ️ Test device already exists. Updating configuration...\n";
    $device = $existingDevice;
} else {
    echo "🆕 Creating new test device...\n";
    $device = new Device();
}

// Configure the Mosquitto test device
$device->fill([
    'device_id' => 'mosquitto_test_001',
    'name' => 'Test Mosquitto Device',
    'device_type' => 'sensor',
    'connection_type' => 'mqtt',
    'connection_broker' => 'mosquitto',
    'mqtt_host' => 'test.mosquitto.org',
    'port' => 1883,
    'use_ssl' => false,
    'username' => null, // Anonymous connection
    'password' => null,
    'mqtt_topics' => ['test/mosquitto/sensors', 'test/mosquitto/status'],
    'user_id' => $user->id,
    'is_active' => true,
    'status' => 'offline',
    'keepalive' => 60,
    'timeout' => 30,
    'auto_reconnect' => true,
    'max_reconnect_attempts' => 3,
    'description' => 'Test device for Mosquitto broker support'
]);

$device->save();

echo "✅ Mosquitto test device created/updated successfully!\n\n";

echo "📋 Device Configuration:\n";
echo "========================\n";
echo "Device ID: {$device->device_id}\n";
echo "Name: {$device->name}\n";
echo "Connection Type: {$device->connection_type}\n";
echo "Broker Type: {$device->connection_broker}\n";
echo "Host: {$device->mqtt_host}\n";
echo "Port: {$device->port}\n";
echo "SSL: " . ($device->use_ssl ? 'Yes' : 'No') . "\n";
echo "Username: " . ($device->username ?: 'Anonymous') . "\n";
echo "Topics: " . json_encode($device->mqtt_topics) . "\n";
echo "Active: " . ($device->is_active ? 'Yes' : 'No') . "\n";
echo "Status: {$device->status}\n\n";

echo "🧪 Testing Broker Detection:\n";
echo "============================\n";

// Test the broker detection
use App\Traits\MqttUtilities;

class TestUtilities {
    use MqttUtilities;
}

$tester = new TestUtilities();
$detectedBroker = $tester->detectBrokerType($device);
$selectedLibrary = $tester->selectMqttLibrary($device, $detectedBroker);
$port = $tester->getDevicePort($device);
$keepalive = $tester->getKeepaliveForBroker($device, $detectedBroker);

echo "Detected Broker: {$detectedBroker}\n";
echo "Selected Library: {$selectedLibrary}\n";
echo "Calculated Port: {$port}\n";
echo "Calculated Keepalive: {$keepalive}\n\n";

echo "🚀 Ready to Test!\n";
echo "=================\n";
echo "The Mosquitto test device is now ready. You can test it with:\n\n";

echo "1. **Test with MQTT listener:**\n";
echo "   php artisan mqtt:listen-all --device-reload-interval=60\n\n";

echo "2. **Test connection to public Mosquitto broker:**\n";
echo "   The device will connect to test.mosquitto.org:1883\n";
echo "   This is a public test broker that allows anonymous connections\n\n";

echo "3. **Publish test messages:**\n";
echo "   You can publish messages to the topics using any MQTT client:\n";
echo "   - Topic: test/mosquitto/sensors\n";
echo "   - Topic: test/mosquitto/status\n";
echo "   - Host: test.mosquitto.org\n";
echo "   - Port: 1883\n\n";

echo "4. **Example MQTT publish command (if you have mosquitto_pub installed):**\n";
echo "   mosquitto_pub -h test.mosquitto.org -t test/mosquitto/sensors -m '{\"temperature\": 25.5, \"humidity\": 60}'\n";
echo "   mosquitto_pub -h test.mosquitto.org -t test/mosquitto/status -m '{\"status\": \"online\", \"timestamp\": \"" . date('Y-m-d H:i:s') . "\"}'\n\n";

echo "5. **Monitor with mosquitto_sub (if installed):**\n";
echo "   mosquitto_sub -h test.mosquitto.org -t test/mosquitto/+\n\n";

echo "🔧 Device Management:\n";
echo "====================\n";
echo "To disable the test device:\n";
echo "php artisan tinker\n";
echo "\$device = App\\Models\\Device::where('device_id', 'mosquitto_test_001')->first();\n";
echo "\$device->is_active = false;\n";
echo "\$device->save();\n";
echo "exit\n\n";

echo "To delete the test device:\n";
echo "php artisan tinker\n";
echo "App\\Models\\Device::where('device_id', 'mosquitto_test_001')->delete();\n";
echo "exit\n\n";

echo "✨ Mosquitto support is now fully integrated!\n";
echo "The MQTT listener will automatically detect and connect to Mosquitto brokers.\n";
