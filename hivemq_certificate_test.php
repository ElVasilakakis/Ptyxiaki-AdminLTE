<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Device;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;

echo "=== HiveMQ Certificate-Based Connection Test ===\n";

$device = Device::find(6);

echo "Device: {$device->name}\n";
echo "Host: {$device->mqtt_host}\n";
echo "Port: {$device->port}\n";
echo "Username: {$device->username}\n";
echo "SSL: " . ($device->use_ssl ? 'Yes' : 'No') . "\n\n";

// Check for certificate files
$certPath = storage_path('certificates');
$clientCert = $certPath . '/client.crt';
$clientKey = $certPath . '/client.key';
$caCert = $certPath . '/ca.crt';

echo "🔍 Checking for certificate files:\n";
echo "   - Certificate directory: " . $certPath . "\n";
echo "   - Client cert: " . $clientCert . " " . (file_exists($clientCert) ? "✅" : "❌") . "\n";
echo "   - Client key: " . $clientKey . " " . (file_exists($clientKey) ? "✅" : "❌") . "\n";
echo "   - CA cert: " . $caCert . " " . (file_exists($caCert) ? "✅" : "❌") . "\n\n";

// Test 1: Try with certificate authentication (if certificates exist)
if (file_exists($clientCert) && file_exists($clientKey)) {
    echo "🔐 Testing with client certificate authentication...\n";
    
    try {
        $clientId = 'cert_test_' . time();
        
        $connectionSettings = new ConnectionSettings();
        $connectionSettings->setUsername($device->username);
        $connectionSettings->setPassword($device->password);
        $connectionSettings->setUseTls(true);
        
        // Set certificate files
        $connectionSettings->setTlsClientCertificateFile($clientCert);
        $connectionSettings->setTlsClientCertificateKeyFile($clientKey);
        
        if (file_exists($caCert)) {
            $connectionSettings->setTlsCaCertificateFile($caCert);
            $connectionSettings->setTlsVerifyPeer(true);
            $connectionSettings->setTlsVerifyPeerName(true);
        } else {
            $connectionSettings->setTlsVerifyPeer(false);
            $connectionSettings->setTlsVerifyPeerName(false);
        }
        
        $connectionSettings->setTlsSelfSignedAllowed(true);
        $connectionSettings->setKeepAliveInterval(30);
        $connectionSettings->setConnectTimeout(10);
        
        echo "   - Connecting with certificates...\n";
        
        $mqtt = new MqttClient($device->mqtt_host, $device->port, $clientId, MqttClient::MQTT_3_1_1);
        $mqtt->connect($connectionSettings, true);
        
        echo "✅ SUCCESS: Connected with certificate authentication!\n";
        
        $mqtt->disconnect();
        echo "✅ Disconnected cleanly\n";
        
        $device->update(['status' => 'online', 'last_seen_at' => now()]);
        echo "📊 Device status updated to online\n";
        
        exit(0);
        
    } catch (MqttClientException $e) {
        echo "❌ Certificate authentication failed: " . $e->getMessage() . "\n\n";
    }
}

// Test 2: Try with username/password only (no certificate verification)
echo "🔓 Testing with username/password authentication (no certificates)...\n";

try {
    $clientId = 'no_cert_test_' . time();
    
    $connectionSettings = new ConnectionSettings();
    $connectionSettings->setUsername($device->username);
    $connectionSettings->setPassword($device->password);
    $connectionSettings->setUseTls(true);
    
    // Disable all certificate verification
    $connectionSettings->setTlsVerifyPeer(false);
    $connectionSettings->setTlsVerifyPeerName(false);
    $connectionSettings->setTlsSelfSignedAllowed(true);
    
    $connectionSettings->setKeepAliveInterval(30);
    $connectionSettings->setConnectTimeout(10);
    
    echo "   - Connecting without certificate verification...\n";
    
    $mqtt = new MqttClient($device->mqtt_host, $device->port, $clientId, MqttClient::MQTT_3_1_1);
    $mqtt->connect($connectionSettings, true);
    
    echo "✅ SUCCESS: Connected without certificates!\n";
    
    $mqtt->disconnect();
    echo "✅ Disconnected cleanly\n";
    
    $device->update(['status' => 'online', 'last_seen_at' => now()]);
    echo "📊 Device status updated to online\n";
    
} catch (MqttClientException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n\n";
    
    $device->update(['status' => 'error', 'last_seen_at' => now()]);
    
    echo "🔧 CERTIFICATE ISSUE DETECTED\n";
    echo "   Your HiveMQ Cloud instance requires client certificates.\n\n";
    
    echo "📋 SOLUTIONS:\n";
    echo "   1. OBTAIN CERTIFICATES from HiveMQ Cloud:\n";
    echo "      - Log into your HiveMQ Cloud console\n";
    echo "      - Go to 'Access Management' > 'Client Certificates'\n";
    echo "      - Download client certificate, private key, and CA certificate\n";
    echo "      - Place them in: " . $certPath . "/\n";
    echo "        * client.crt (client certificate)\n";
    echo "        * client.key (private key)\n";
    echo "        * ca.crt (CA certificate)\n\n";
    
    echo "   2. ALTERNATIVE: Use HiveMQ Cloud without certificates:\n";
    echo "      - In HiveMQ Cloud console, disable 'Require Client Certificates'\n";
    echo "      - Use only username/password authentication\n\n";
    
    echo "   3. SWITCH TO DIFFERENT BROKER:\n";
    echo "      - Use a public MQTT broker that doesn't require certificates\n";
    echo "      - Examples: broker.emqx.io, test.mosquitto.org\n\n";
    
    // Create certificate directory if it doesn't exist
    if (!is_dir($certPath)) {
        mkdir($certPath, 0755, true);
        echo "📁 Created certificate directory: " . $certPath . "\n";
    }
    
}
