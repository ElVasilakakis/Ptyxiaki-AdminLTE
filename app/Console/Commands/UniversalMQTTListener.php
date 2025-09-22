<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\Sensor;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Bluerhinos\phpMQTT; // Ensure this is correctly included


class UniversalMQTTListener extends Command
{
    protected $signature = 'mqtt:listen-all {--timeout=0}';
    protected $description = 'Listen to MQTT topics for all devices with MQTT connection type';

    private $mqttClients = [];
    private $devices = [];

    // Define broker-specific client mappings
    private $brokerClientMap = [
        'the_things_stack' => 'bluerhinos',
        'thethings_stack' => 'bluerhinos',
        'ttn' => 'bluerhinos',
        'lorawan' => 'bluerhinos',
        'hivemq' => 'php-mqtt',
        'hivemq_cloud' => 'php-mqtt', 
        'emqx' => 'php-mqtt',
        'esp32' => 'php-mqtt',
    ];

    public function handle()
    {


        $timeout = (int) $this->option('timeout');

        // Get all MQTT devices
        $this->devices = Device::where('connection_type', 'mqtt')
            ->where('is_active', true)
            ->whereNotNull('mqtt_host')
            ->whereNotNull('mqtt_topics')
            ->get();

        if ($this->devices->isEmpty()) {
            $this->error("No active MQTT devices found.");
            return 1;
        }

        $this->info("Found " . $this->devices->count() . " MQTT devices to monitor:");
        foreach ($this->devices as $device) {
            $brokerType = $this->detectBrokerType($device);
            $clientLibrary = $this->getClientLibrary($brokerType);
            $this->info("- {$device->name} ({$device->device_id}) - {$device->mqtt_host} [{$brokerType}] -> {$clientLibrary}");
        }

        try {
            // Connect to all MQTT brokers
            $this->connectToAllBrokers();

            $this->info("Listening for messages from all devices... (Press Ctrl+C to stop)");

            // Keep the script running
            if ($timeout > 0) {
                $this->runWithTimeout($timeout);
            } else {
                $this->runIndefinitely();
            }

        } catch (\Exception $e) {
            $this->error("Universal MQTT Listener Error: " . $e->getMessage());
            return 1;
        } finally {
            $this->disconnectAll();
        }

        return 0;
    }

    private function detectBrokerType(Device $device): string
    {
        // Check if explicitly set in device configuration
        if ($device->connection_broker) {
            return strtolower($device->connection_broker);
        }

        // Auto-detect based on hostname
        $host = strtolower($device->mqtt_host);
        
        if (str_contains($host, 'thethings') || str_contains($host, 'ttn')) {
            return 'thethings_stack';
        }
        
        if (str_contains($host, 'hivemq')) {
            return 'hivemq';
        }
        
        if (str_contains($host, 'emqx')) {
            return 'emqx';
        }

        // Default fallback
        return 'emqx';
    }

    private function getClientLibrary(string $brokerType): string
    {
        $library = $this->brokerClientMap[$brokerType] ?? 'php-mqtt';
        
        // Debug log to verify mapping
        $this->info("🔍 Broker type '{$brokerType}' mapped to library '{$library}'");
        
        return $library;
    }

    private function connectToAllBrokers()
    {
        $brokerGroups = $this->groupDevicesByBroker();

        foreach ($brokerGroups as $brokerKey => $devices) {
            $firstDevice = $devices->first();
            $brokerType = $this->detectBrokerType($firstDevice);
            $clientLibrary = $this->getClientLibrary($brokerType);
            
            try {
                $this->info("🚀 Starting connection process for: {$firstDevice->mqtt_host}");
                $this->info("📋 Broker Type: {$brokerType} | Client Library: {$clientLibrary}");
                $this->logDeviceConfiguration($firstDevice);
                
                // Create appropriate client based on broker type
                if ($clientLibrary === 'bluerhinos') {
                    $this->info("🔧 Using BluerhiNos client for The Things Stack compatibility");
                    $this->connectBluerhinos($brokerKey, $devices, $firstDevice);
                } else {
                    $this->info("🔧 Using php-mqtt client for standard MQTT");
                    $this->connectPhpMqtt($brokerKey, $devices, $firstDevice);
                }

            } catch (\Exception $e) {
                $this->handleBrokerConnectionError($firstDevice, $devices, $e);
                continue;
            }
        }
        
        // Check if we have any successful connections
        if (empty($this->mqttClients)) {
            throw new \Exception("Failed to connect to any MQTT brokers!");
        }
        
        $this->info("🎯 Successfully connected to " . count($this->mqttClients) . " broker(s)");
    }

    private function connectBluerhinos(string $brokerKey, $devices, Device $firstDevice)
    {
        $this->info("🔥 Initializing BluerhiNos MQTT client for The Things Stack...");
        
        $clientId = 'laravel_tts_' . time() . '_' . substr(md5($brokerKey), 0, 8);
        $port = $firstDevice->port ?: ($firstDevice->use_ssl ? 8883 : 1883);
        
        // For SSL connections with The Things Stack, use ssl:// prefix
        $host = $firstDevice->mqtt_host;
        if ($firstDevice->use_ssl) {
            $host = 'ssl://' . $host;
            $this->info("🔒 Using SSL connection: {$host}");
        }
        
        $this->info("🏗️ Creating BluerhiNos MQTT client with ID: {$clientId}");
        $this->info("🔗 Host: {$host}, Port: {$port}, SSL: " . ($firstDevice->use_ssl ? 'Yes' : 'No'));
        
        // Create phpMQTT instance using the correct namespace
        // For SSL connections, Bluerhinos phpMQTT handles SSL internally
        $cafile = null; // We'll let it use default SSL settings
        if ($firstDevice->use_ssl) {
            $this->info("🔒 SSL connection will be handled by Bluerhinos phpMQTT library");
            // Remove ssl:// prefix as the library will handle it internally
            $host = str_replace('ssl://', '', $host);
        }
        
        $mqtt = new phpMQTT($host, $port, $clientId, $cafile);

        $this->info("⏳ Connecting to The Things Stack at {$firstDevice->mqtt_host}:{$port}");
        $startTime = microtime(true);
        
        // Connect with credentials (The Things Stack requires authentication)
        if (!$firstDevice->username || !$firstDevice->password) {
            throw new \Exception("The Things Stack requires username and password authentication");
        }
        
        $this->info("🔐 Authenticating with username: {$firstDevice->username}");
        $connected = $mqtt->connect(true, NULL, $firstDevice->username, $firstDevice->password);
        
        if (!$connected) {
            throw new \Exception("Failed to connect to The Things Stack: Authentication or connection failed");
        }
        
        $endTime = microtime(true);
        $connectionTime = round(($endTime - $startTime) * 1000, 2);
        $this->info("✅ Connected to The Things Stack successfully! ({$connectionTime}ms)");
        
        // Subscribe to topics for The Things Stack
        $this->subscribeBluerhinos($mqtt, $devices);
        
        // Store client
        $this->mqttClients[$brokerKey] = [
            'client' => $mqtt,
            'type' => 'bluerhinos',
            'devices' => $devices
        ];
    }

    private function connectPhpMqtt(string $brokerKey, $devices, Device $firstDevice)
    {
        $clientId = 'laravel_universal_' . time() . '_' . substr(md5($brokerKey), 0, 8);
        $port = $firstDevice->port ?: ($firstDevice->use_ssl ? 8883 : 1883);
        
        $this->info("🏗️ Creating php-mqtt client with ID: {$clientId}");
        
        // Create connection settings
        $connectionSettings = new ConnectionSettings();
        $connectionSettings->setKeepAliveInterval($firstDevice->keepalive ?: 60);
        $connectionSettings->setConnectTimeout(15);
        $connectionSettings->setSocketTimeout(15);
        $connectionSettings->setUseTls($firstDevice->use_ssl);
        
        // Configure for HiveMQ Cloud
        if (str_contains($firstDevice->mqtt_host, 'hivemq')) {
            $this->info("🔧 Configuring for HiveMQ Cloud");
            $connectionSettings->setTlsSelfSignedAllowed(true);
            $connectionSettings->setTlsVerifyPeer(true);
            $connectionSettings->setTlsVerifyPeerName(true);
        }
        
        // Configure for EMQX
        if (str_contains($firstDevice->mqtt_host, 'emqx')) {
            $this->info("🔧 Configuring for EMQX");
            $connectionSettings->setTlsSelfSignedAllowed(true);
            $connectionSettings->setTlsVerifyPeer(false);
            $connectionSettings->setTlsVerifyPeerName(false);
        }
        
        if ($firstDevice->username) {
            $connectionSettings->setUsername($firstDevice->username);
        }
        
        if ($firstDevice->password) {
            $connectionSettings->setPassword($firstDevice->password);
        }

        // Create MQTT client
        $mqtt = new MqttClient($firstDevice->mqtt_host, $port, $clientId);
        
        $this->info("⏳ Connecting to {$firstDevice->mqtt_host}:{$port}");
        $startTime = microtime(true);
        
        $mqtt->connect($connectionSettings, true);
        
        $endTime = microtime(true);
        $connectionTime = round(($endTime - $startTime) * 1000, 2);
        $this->info("✅ Connected to {$firstDevice->mqtt_host} successfully! ({$connectionTime}ms)");
        
        // Subscribe to topics
        $this->subscribePhpMqtt($mqtt, $devices);
        
        // Store client
        $this->mqttClients[$brokerKey] = [
            'client' => $mqtt,
            'type' => 'php-mqtt',
            'devices' => $devices
        ];
    }

    private function subscribeBluerhinos($mqtt, $devices)
    {
        $this->info("📡 Starting BluerhiNos topic subscriptions for The Things Stack...");
        
        // Collect all topics for this broker
        $topics = [];
        foreach ($devices as $device) {
            foreach ($device->mqtt_topics as $topic) {
                $topics[$topic] = [
                    'qos' => 0, // QoS 0 required by The Things Stack
                    'function' => function($receivedTopic, $message) use ($devices) {
                        $this->handleMqttMessage($devices, $receivedTopic, $message);
                    }
                ];
                $this->info("📋 Added TTS topic for subscription: {$topic}");
            }
        }
        
        if (!empty($topics)) {
            $this->info("🔔 Subscribing to " . count($topics) . " The Things Stack topics...");
            $mqtt->subscribe($topics, 0);
            $this->info("✅ Subscribed to " . count($topics) . " TTS topics successfully");
            
            // Update all devices status to online
            foreach ($devices as $device) {
                $device->update([
                    'status' => 'online',
                    'last_seen_at' => now()
                ]);
                $this->info("   📊 Device {$device->name} status updated to online");
            }
        }
    }

    private function subscribePhpMqtt($mqtt, $devices)
    {
        $this->info("📡 Starting php-mqtt topic subscriptions...");
        
        foreach ($devices as $device) {
            $this->info("🎯 Processing device: {$device->name}");
            foreach ($device->mqtt_topics as $topic) {
                $this->info("📋 Subscribing to topic: {$topic}");
                try {
                    $mqtt->subscribe($topic, function (string $topic, string $message) use ($devices) {
                        $this->handleMqttMessage($devices, $topic, $message);
                    }, 0);
                    $this->info("   ✅ Subscribed successfully");
                } catch (\Exception $subException) {
                    $this->warn("   ⚠️ Failed to subscribe: " . $subException->getMessage());
                }
            }
            
            // Update device status to online
            $device->update([
                'status' => 'online',
                'last_seen_at' => now()
            ]);
            $this->info("   📊 Device status updated to online");
        }
    }

    private function runWithTimeout($timeout)
    {
        $startTime = time();
        while ((time() - $startTime) < $timeout) {
            foreach ($this->mqttClients as $brokerKey => $clientData) {
                try {
                    if ($clientData['type'] === 'bluerhinos') {
                        $clientData['client']->proc();
                    } else {
                        $clientData['client']->loop(false, 1);
                    }
                } catch (\Exception $e) {
                    $this->warn("Loop error for {$brokerKey}: " . $e->getMessage());
                }
            }
            usleep(100000); // Sleep 100ms between loops
        }
    }

    private function runIndefinitely()
    {
        while (true) {
            foreach ($this->mqttClients as $brokerKey => $clientData) {
                try {
                    if ($clientData['type'] === 'bluerhinos') {
                        $clientData['client']->proc();
                    } else {
                        $clientData['client']->loop(false, 1);
                    }
                } catch (\Exception $e) {
                    $this->warn("Loop error for {$brokerKey}: " . $e->getMessage());
                    $this->attemptReconnection($brokerKey, $clientData);
                }
            }
            usleep(100000); // Sleep 100ms between loops
        }
    }

    private function attemptReconnection($brokerKey, $clientData)
    {
        $this->info("🔄 Attempting to reconnect to {$brokerKey}...");
        
        try {
            $devices = $clientData['devices'];
            $firstDevice = $devices->first();
            $brokerType = $this->detectBrokerType($firstDevice);
            $clientLibrary = $this->getClientLibrary($brokerType);
            
            // Remove failed client
            unset($this->mqttClients[$brokerKey]);
            
            // Attempt reconnection with appropriate client
            if ($clientLibrary === 'bluerhinos') {
                $this->connectBluerhinos($brokerKey, $devices, $firstDevice);
            } else {
                $this->connectPhpMqtt($brokerKey, $devices, $firstDevice);
            }
            
            $this->info("✅ Reconnected to {$brokerKey} successfully");
            
        } catch (\Exception $e) {
            $this->error("❌ Reconnection failed for {$brokerKey}: " . $e->getMessage());
            
            // Update devices to error status
            foreach ($clientData['devices'] as $device) {
                $device->update([
                    'status' => 'error',
                    'last_seen_at' => now()
                ]);
            }
        }
    }

    private function disconnectAll()
    {
        foreach ($this->mqttClients as $brokerKey => $clientData) {
            try {
                if ($clientData['type'] === 'bluerhinos') {
                    $clientData['client']->close();
                } else {
                    $clientData['client']->disconnect();
                }
                $this->info("Disconnected from broker: {$brokerKey}");
            } catch (\Exception $e) {
                $this->warn("Error disconnecting from {$brokerKey}: " . $e->getMessage());
            }
        }
    }

    private function logDeviceConfiguration(Device $firstDevice)
    {
        $this->info("📋 Device Configuration:");
        $this->info("   - Host: {$firstDevice->mqtt_host}");
        $this->info("   - Port: " . ($firstDevice->port ?: ($firstDevice->use_ssl ? 8883 : 1883)));
        $this->info("   - Use SSL: " . ($firstDevice->use_ssl ? 'Yes' : 'No'));
        $this->info("   - Username: " . ($firstDevice->username ?: 'None'));
        $this->info("   - Password: " . ($firstDevice->password ? 'Set (length: ' . strlen($firstDevice->password) . ')' : 'None'));
        $this->info("   - Keep Alive: " . ($firstDevice->keepalive ?: 60));
    }

    private function handleBrokerConnectionError(Device $firstDevice, $devices, \Exception $e)
    {
        $this->error("💥 Broker connection failed: {$firstDevice->mqtt_host}");
        $this->error("💥 Error: " . $e->getMessage());
        
        // Update device status to error for all devices on this broker
        foreach ($devices as $device) {
            $device->update([
                'status' => 'error',
                'last_seen_at' => now()
            ]);
            $this->warn("   📊 Device {$device->name} status updated to error");
        }
    }

    private function groupDevicesByBroker()
    {
        return $this->devices->groupBy(function ($device) {
            return $device->mqtt_host . ':' . ($device->port ?: ($device->use_ssl ? 8883 : 1883)) . ':' . ($device->username ?: 'anonymous');
        });
    }

    private function handleMqttMessage($devices, $topic, $message)
    {
        // Find the device that matches this message topic
        $matchedDevice = null;
        foreach ($devices as $device) {
            foreach ($device->mqtt_topics as $deviceTopic) {
                if ($this->topicMatches($deviceTopic, $topic)) {
                    $matchedDevice = $device;
                    break 2;
                }
            }
        }

        if ($matchedDevice) {
            $this->processMqttMessage($matchedDevice, $topic, $message);
        } else {
            $this->warn("⚠️ Received message on unmatched topic: {$topic}");
        }
    }

    private function topicMatches($pattern, $topic)
    {
        // Convert MQTT wildcards to regex
        $pattern = str_replace(['+', '#'], ['[^/]+', '.*'], $pattern);
        $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';
        
        return preg_match($pattern, $topic);
    }

    private function processMqttMessage(Device $device, string $topic, string $message)
    {
        $this->info("[{$device->name}] Received message on topic '{$topic}': " . substr($message, 0, 200) . (strlen($message) > 200 ? '...' : ''));

        try {
            // Try to decode JSON message
            $data = json_decode($message, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn("[{$device->name}] Message is not valid JSON, treating as plain text");
                // If not JSON, treat as plain text and extract sensor type from topic
                $topicParts = explode('/', $topic);
                $sensorType = end($topicParts);
                $this->createOrUpdateSensor($device, $sensorType, $message, null, $topic);
                return;
            }

            // Determine device type and handle accordingly
            $this->handleDevicePayload($device, $data, $topic);

            // Update device last seen
            $device->update([
                'status' => 'online',
                'last_seen_at' => now()
            ]);

        } catch (\Exception $e) {
            $this->error("[{$device->name}] Error processing message: " . $e->getMessage());
            \Log::error('Universal MQTT message processing error', [
                'device_id' => $device->device_id,
                'topic' => $topic,
                'message' => substr($message, 0, 500),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleDevicePayload(Device $device, array $data, string $topic)
    {
        // Handle payload based on broker type
        $brokerType = $device->connection_broker ?? 'emqx'; // Default to emqx
        
        $this->info("[{$device->name}] Processing payload for broker type: {$brokerType}");
        
        switch (strtolower($brokerType)) {
            case 'the_things_stack':
            case 'thethings_stack':
            case 'ttn':
            case 'lorawan':
                $this->handleTheThingsStackPayload($device, $data, $topic);
                break;
                
            case 'hivemq':
            case 'hivemq_cloud':
                // Handle HiveMQ format (usually simple key-value or ESP32-style)
                if (isset($data['sensors']) && is_array($data['sensors'])) {
                    $this->handleESP32Payload($device, $data, $topic);
                } else {
                    $this->handleSimplePayload($device, $data, $topic);
                }
                break;
                
            case 'emqx':
            case 'esp32':
            default:
                // Handle ESP32/EMQX format
                if (isset($data['sensors']) && is_array($data['sensors'])) {
                    $this->handleESP32Payload($device, $data, $topic);
                } else {
                    $this->handleSimplePayload($device, $data, $topic);
                }
                break;
        }
    }

    private function handleTheThingsStackPayload(Device $device, array $data, string $topic)
    {
        $this->info("[{$device->name}] Processing The Things Stack payload");
        
        // Extract decoded payload from The Things Stack structure
        $decodedPayload = null;
        
        // Handle your specific The Things Stack structure
        if (isset($data['data']['uplink_message']['decoded_payload'])) {
            $decodedPayload = $data['data']['uplink_message']['decoded_payload'];
        } elseif (isset($data['uplink_message']['decoded_payload'])) {
            $decodedPayload = $data['uplink_message']['decoded_payload'];
        } elseif (isset($data['decoded_payload'])) {
            $decodedPayload = $data['decoded_payload'];
        }

        if (!$decodedPayload) {
            $this->warn("[{$device->name}] No decoded payload found in The Things Stack message");
            return;
        }

        $this->info("[{$device->name}] Decoded payload: " . json_encode($decodedPayload));

        // Process each sensor value in the decoded payload
        foreach ($decodedPayload as $key => $value) {
            // Skip non-sensor fields
            if (in_array(strtolower($key), ['gps_fix', 'gps_fix_type', 'warnings', 'errors'])) {
                continue;
            }

            $sensorType = $this->normalizeSensorType($key);
            $unit = $this->getUnitForSensorType($sensorType);
            
            $this->createOrUpdateSensor($device, $sensorType, $value, $unit, $topic);
        }
    }

    private function handleESP32Payload(Device $device, array $data, string $topic)
    {
        $this->info("[{$device->name}] Processing ESP32 payload");
        
        foreach ($data['sensors'] as $sensorData) {
            if (isset($sensorData['type']) && isset($sensorData['value'])) {
                $sensorType = $this->normalizeSensorType($sensorData['type']);
                
                // Handle geolocation with subtype
                if ($sensorData['type'] === 'geolocation' && isset($sensorData['subtype'])) {
                    $sensorType = $sensorData['subtype']; // latitude or longitude
                }
                
                $cleanValue = $this->extractNumericValue($sensorData['value']);
                $unit = $this->extractUnit($sensorData['value']) ?: $this->getUnitForSensorType($sensorType);
                
                $this->createOrUpdateSensor($device, $sensorType, $cleanValue, $unit, $topic);
            }
        }
    }

    private function handleSimplePayload(Device $device, array $data, string $topic)
    {
        $this->info("[{$device->name}] Processing simple key-value payload");
        
        // Check if it's a single sensor reading with sensor_type field
        if (isset($data['sensor_type']) && isset($data['value'])) {
            $this->createOrUpdateSensor($device, $data['sensor_type'], $data['value'], $data['unit'] ?? null, $topic);
            return;
        }

        // Handle multiple sensor readings in one message
        foreach ($data as $key => $value) {
            // Skip non-sensor fields
            if (in_array(strtolower($key), ['timestamp', 'device_id', 'message_id'])) {
                continue;
            }
            
            // Handle different sensor naming conventions
            $sensorType = $this->normalizeSensorType($key);
            $unit = $this->getUnitForSensorType($sensorType);
            
            $this->createOrUpdateSensor($device, $sensorType, $value, $unit, $topic);
        }
    }

    private function createOrUpdateSensor(Device $device, string $sensorType, $value, ?string $unit, string $topic)
    {
        // Remove units from value if they're included (e.g., "24.0°C" -> "24.0")
        $cleanValue = $this->extractNumericValue($value);
        
        // Find or create sensor
        $sensor = Sensor::firstOrCreate(
            [
                'device_id' => $device->id,
                'sensor_type' => $sensorType,
                'user_id' => $device->user_id,
            ],
            [
                'sensor_name' => ucfirst(str_replace('_', ' ', $sensorType)) . ' Sensor',
                'description' => 'Auto-created from MQTT topic: ' . $topic,
                'unit' => $unit,
                'enabled' => true,
            ]
        );

        // Update sensor reading
        $sensor->updateReading($cleanValue, now());
        
        // Update unit if provided and different
        if ($unit && $sensor->unit !== $unit) {
            $sensor->update(['unit' => $unit]);
        }

        $this->info("[{$device->name}] Updated sensor '{$sensorType}' with value: {$cleanValue}" . ($unit ? " {$unit}" : ""));
    }

    private function normalizeSensorType(string $key): string
    {
        // Convert common sensor field names to standard types
        $key = strtolower($key);
        
        $mappings = [
            'temp' => 'temperature',
            'temperature' => 'temperature',
            'humid' => 'humidity',
            'humidity' => 'humidity',
            'light' => 'light',
            'potentiometer' => 'potentiometer',
            'pot' => 'potentiometer',
            'lat' => 'latitude',
            'latitude' => 'latitude',
            'lng' => 'longitude',
            'lon' => 'longitude',
            'longitude' => 'longitude',
            'pressure' => 'pressure',
            'soil_moisture' => 'soil_moisture',
            'ph' => 'ph',
            'battery' => 'battery',
            'altitude' => 'altitude',
            'alt' => 'altitude',
        ];

        return $mappings[$key] ?? $key;
    }

    private function getUnitForSensorType(string $sensorType): ?string
    {
        $units = [
            'temperature' => '°C',
            'humidity' => '%',
            'light' => '%',
            'potentiometer' => '%',
            'pressure' => 'hPa',
            'soil_moisture' => '%',
            'latitude' => '°',
            'longitude' => '°',
            'battery' => '%',
            'altitude' => 'm',
        ];

        return $units[$sensorType] ?? null;
    }

    private function extractNumericValue($value): float
    {
        // If it's already numeric, return as is
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Extract numeric value from strings like "24.0°C" or "40.0%"
        if (is_string($value)) {
            preg_match('/(-?\d+\.?\d*)/', $value, $matches);
            return isset($matches[0]) ? (float) $matches[0] : 0.0;
        }

        return 0.0;
    }

    private function extractUnit($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        // Extract unit from strings like "24.0 celsius", "40.0 percent"
        $unitMappings = [
            'celsius' => '°C',
            'fahrenheit' => '°F',
            'percent' => '%',
            'percentage' => '%',
            'degrees' => '°',
        ];

        foreach ($unitMappings as $text => $symbol) {
            if (stripos($value, $text) !== false) {
                return $symbol;
            }
        }

        return null;
    }
}
