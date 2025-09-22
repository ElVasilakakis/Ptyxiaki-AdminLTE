<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\Sensor;
use Bluerhinos\phpMQTT;

class UniversalMQTTListener extends Command
{
    protected $signature = 'mqtt:listen-all {--timeout=0}';
    protected $description = 'Listen to MQTT topics for all devices with MQTT connection type';

    private $mqttClients = [];
    private $devices = [];

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
            $this->info("- {$device->name} ({$device->device_id}) - {$device->mqtt_host} [{$brokerType}] -> bluerhinos");
        }

        try {
            // Connect to all MQTT brokers using BluerhiNos
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

    private function connectToAllBrokers()
    {
        $brokerGroups = $this->groupDevicesByBroker();

        foreach ($brokerGroups as $brokerKey => $devices) {
            $firstDevice = $devices->first();
            $brokerType = $this->detectBrokerType($firstDevice);
            
            try {
                $this->info("🚀 Starting connection process for: {$firstDevice->mqtt_host}");
                $this->info("📋 Broker Type: {$brokerType} | Client Library: bluerhinos");
                $this->logDeviceConfiguration($firstDevice);
                
                // Use BluerhiNos for all brokers
                $this->connectBluerhinos($brokerKey, $devices, $firstDevice, $brokerType);

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

    private function connectBluerhinos(string $brokerKey, $devices, Device $firstDevice, string $brokerType)
    {
        $this->info("🔥 Initializing BluerhiNos MQTT client for {$brokerType}...");
        
        $clientId = 'laravel_' . strtolower($brokerType) . '_' . time() . '_' . substr(md5($brokerKey), 0, 8);
        $port = $firstDevice->port ?: ($firstDevice->use_ssl ? 8883 : 1883);
        
        // Handle SSL connections
        $host = $firstDevice->mqtt_host;
        $cafile = null;
        
        if ($firstDevice->use_ssl) {
            $this->warn("⚠️ SSL connections are not fully supported by BluerhiNos phpMQTT library");
            $this->warn("   Skipping SSL device: {$brokerType} at {$host}:{$port}");
            $this->warn("   For The Things Stack, consider using webhook integration instead");
            
            // Update devices to indicate SSL limitation
            foreach ($devices as $device) {
                $device->update([
                    'status' => 'error',
                    'last_seen_at' => now()
                ]);
                $this->warn("   📊 Device {$device->name} status updated to error (SSL not supported)");
            }
            
            throw new \Exception("SSL connections not supported by BluerhiNos phpMQTT library");
        }
        
        $this->info("🏗️ Creating BluerhiNos MQTT client with ID: {$clientId}");
        $this->info("🔗 Host: {$host}, Port: {$port}, SSL: " . ($firstDevice->use_ssl ? 'Yes' : 'No'));
        
        // Create phpMQTT instance
        $mqtt = new phpMQTT($host, $port, $clientId, $cafile);
        
        // Set keepalive based on broker type
        switch ($brokerType) {
            case 'thethings_stack':
                $mqtt->keepalive = min($firstDevice->keepalive ?: 10, 10); // TTS prefers shorter keepalive
                break;
            case 'hivemq':
                $mqtt->keepalive = $firstDevice->keepalive ?: 60; // HiveMQ standard keepalive
                break;
            case 'emqx':
                $mqtt->keepalive = $firstDevice->keepalive ?: 60; // EMQX standard keepalive
                break;
            default:
                $mqtt->keepalive = $firstDevice->keepalive ?: 60;
        }

        $this->info("⏳ Connecting to {$brokerType} at {$host}:{$port} (keepalive: {$mqtt->keepalive}s)");
        $startTime = microtime(true);
        
        // Handle authentication
        $username = $firstDevice->username;
        $password = $firstDevice->password;
        
        // Some brokers don't require authentication
        if ($username || $password) {
            $this->info("🔐 Authenticating with username: " . ($username ?: 'anonymous'));
        } else {
            $this->info("🔓 Connecting without authentication");
        }
        
        // Connect with appropriate parameters
        $connected = $mqtt->connect(true, NULL, $username, $password);
        
        if (!$connected) {
            throw new \Exception("Failed to connect to {$brokerType}: Connection failed");
        }
        
        $endTime = microtime(true);
        $connectionTime = round(($endTime - $startTime) * 1000, 2);
        $this->info("✅ Connected to {$brokerType} successfully! ({$connectionTime}ms)");
        
        // Subscribe to topics
        $this->subscribeBluerhinos($mqtt, $devices, $brokerType);
        
        // Store client
        $this->mqttClients[$brokerKey] = [
            'client' => $mqtt,
            'type' => 'bluerhinos',
            'broker_type' => $brokerType,
            'devices' => $devices
        ];
    }

    private function subscribeBluerhinos($mqtt, $devices, string $brokerType)
    {
        $this->info("📡 Starting BluerhiNos topic subscriptions for {$brokerType}...");
        
        // Collect all topics for this broker
        $topics = [];
        foreach ($devices as $device) {
            foreach ($device->mqtt_topics as $topic) {
                $topics[$topic] = [
                    'qos' => 0, // QoS 0 for maximum compatibility
                    'function' => function($receivedTopic, $message) use ($devices) {
                        $this->handleMqttMessage($devices, $receivedTopic, $message);
                    }
                ];
                $this->info("📋 Added {$brokerType} topic for subscription: {$topic}");
            }
        }
        
        if (!empty($topics)) {
            $this->info("🔔 Subscribing to " . count($topics) . " {$brokerType} topics...");
            $mqtt->subscribe($topics, 0);
            $this->info("✅ Subscribed to " . count($topics) . " {$brokerType} topics successfully");
            
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

    private function runWithTimeout($timeout)
    {
        $startTime = time();
        while ((time() - $startTime) < $timeout) {
            foreach ($this->mqttClients as $brokerKey => $clientData) {
                try {
                    $clientData['client']->proc();
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
                    $clientData['client']->proc();
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
            $brokerType = $clientData['broker_type'];
            
            // Remove failed client
            unset($this->mqttClients[$brokerKey]);
            
            // Attempt reconnection
            $this->connectBluerhinos($brokerKey, $devices, $firstDevice, $brokerType);
            
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
                $clientData['client']->close();
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
        $brokerType = $device->connection_broker ?? $this->detectBrokerType($device);
        
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
        if (isset($data['uplink_message']['decoded_payload']['data'])) {
            $decodedPayload = $data['uplink_message']['decoded_payload']['data'];
        } elseif (isset($data['data']['uplink_message']['decoded_payload']['data'])) {
            $decodedPayload = $data['data']['uplink_message']['decoded_payload']['data'];
        } elseif (isset($data['decoded_payload']['data'])) {
            $decodedPayload = $data['decoded_payload']['data'];
        } elseif (isset($data['uplink_message']['decoded_payload'])) {
            $decodedPayload = $data['uplink_message']['decoded_payload'];
        } elseif (isset($data['data']['uplink_message']['decoded_payload'])) {
            $decodedPayload = $data['data']['uplink_message']['decoded_payload'];
        } elseif (isset($data['decoded_payload'])) {
            $decodedPayload = $data['decoded_payload'];
        } elseif (isset($data['data'])) {
            // Sometimes the payload is directly in 'data' field
            $decodedPayload = $data['data'];
        }

        if (!$decodedPayload) {
            $this->warn("[{$device->name}] No decoded payload found in The Things Stack message");
            $this->info("[{$device->name}] Raw message structure: " . json_encode(array_keys($data)));
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
        
        // Handle GPS fix status separately if needed
        if (isset($decodedPayload['gps_fix']) && isset($decodedPayload['gps_fix_type'])) {
            $this->info("[{$device->name}] GPS Status: {$decodedPayload['gps_fix_type']} (code: {$decodedPayload['gps_fix']})");
            
            // Store GPS fix quality as a sensor reading
            $this->createOrUpdateSensor($device, 'gps_quality', $decodedPayload['gps_fix'], 'fix_code', $topic);
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
            'gps_fix' => 'gps_quality',
            'gps_quality' => 'gps_quality',
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
            'gps_quality' => 'fix_code',
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
