<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MqttBroker;
use App\Models\Device;
use App\Services\WebhookMqttBridge;

echo "=== MQTT Webhook URLs for Your Devices ===\n\n";

try {
    // Find EMQX broker with ID 1
    $broker = MqttBroker::find(1);
    
    if (!$broker) {
        echo "❌ EMQX broker with ID 1 not found.\n";
        echo "Available brokers:\n";
        $brokers = MqttBroker::all();
        foreach ($brokers as $b) {
            echo "  - ID: {$b->id}, Name: {$b->name}, Type: {$b->type}\n";
        }
        exit(1);
    }
    
    echo "✅ Found EMQX Broker: {$broker->name} (ID: {$broker->id})\n";
    echo "   Type: {$broker->type}\n";
    echo "   Status: {$broker->status}\n\n";
    
    // Get devices for this broker
    $devices = $broker->devices;
    
    if ($devices->isEmpty()) {
        echo "❌ No devices found for this broker.\n";
        echo "Please add devices to your EMQX broker first.\n\n";
        
        echo "To add devices:\n";
        echo "1. Go to your dashboard: " . config('app.url') . "/app/devices/create\n";
        echo "2. Select your EMQX broker when creating the device\n";
        echo "3. Run this script again\n";
        exit(1);
    }
    
    echo "📱 Found " . $devices->count() . " device(s):\n\n";
    
    $webhookBridge = new WebhookMqttBridge();
    
    foreach ($devices as $device) {
        echo "🔧 Device: {$device->name}\n";
        echo "   Device ID: {$device->device_id}\n";
        echo "   Protocol: {$device->protocol}\n";
        echo "   Status: {$device->status}\n";
        
        // Get webhook instructions
        $instructions = $webhookBridge->getWebhookInstructions($device);
        
        echo "   📡 Webhook URL: {$instructions['webhook_url']}\n";
        echo "   📝 Method: {$instructions['method']}\n";
        echo "   📋 Content-Type: {$instructions['content_type']}\n\n";
        
        echo "   💻 Test with cURL:\n";
        echo "   {$instructions['example_curl']}\n\n";
        
        echo "   🔧 Arduino Code Snippet:\n";
        echo "   const char* webhookUrl = \"{$instructions['webhook_url']}\";\n\n";
        
        echo "   " . str_repeat("-", 60) . "\n\n";
    }
    
    echo "🎯 Configuration Summary:\n";
    echo "Base URL: " . config('app.url') . "\n";
    echo "Environment: " . config('app.env') . "\n\n";
    
    echo "📖 For detailed configuration instructions, see:\n";
    echo "- MQTTX_WEBHOOK_GUIDE.md\n";
    echo "- WEBHOOK_CONFIGURATION.md\n\n";
    
    echo "🧪 Test your webhook endpoint:\n";
    echo "curl -X POST '" . config('app.url') . "/api/webhook/test' -H 'Content-Type: application/json' -d '{\"test\": \"data\"}'\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    exit(1);
}
