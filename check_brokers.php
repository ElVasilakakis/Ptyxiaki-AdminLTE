<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MqttBroker;

echo "=== MQTT Brokers in Database ===\n\n";

$brokers = MqttBroker::all();

if ($brokers->isEmpty()) {
    echo "No MQTT brokers found in database.\n";
} else {
    foreach ($brokers as $broker) {
        echo "ID: {$broker->id}\n";
        echo "Name: {$broker->name}\n";
        echo "Type: {$broker->type}\n";
        echo "Host: {$broker->host}\n";
        echo "Port: {$broker->port}\n";
        echo "SSL: " . ($broker->use_ssl ? 'Yes' : 'No') . "\n";
        echo "SSL Port: {$broker->ssl_port}\n";
        echo "WebSocket Port: {$broker->websocket_port}\n";
        echo "Username: " . ($broker->username ? $broker->username : 'None') . "\n";
        echo "Status: {$broker->status}\n";
        echo "Last Connected: " . ($broker->last_connected_at ? $broker->last_connected_at : 'Never') . "\n";
        echo "User ID: {$broker->user_id}\n";
        echo "---\n";
    }
}

echo "\n=== Testing Connections ===\n\n";

foreach ($brokers as $broker) {
    echo "Testing connection to {$broker->name} ({$broker->host}:{$broker->port})...\n";

    $host = $broker->host;
    $port = $broker->use_ssl ? ($broker->ssl_port ?? 8883) : $broker->port;
    $timeout = $broker->timeout ?? 10;

    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

    if ($connection) {
        echo "✅ Connection successful to {$host}:{$port}\n";
        fclose($connection);
    } else {
        echo "❌ Connection failed to {$host}:{$port} - {$errstr} ({$errno})\n";
    }
    echo "---\n";
}
