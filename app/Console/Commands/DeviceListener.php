<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\MqttBroker;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DeviceListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:listen {--device= : Optional specific device ID to listen for} {--broker= : Optional specific MQTT broker ID to connect to} {--type= : Optional device type filter (mqtt, lorawan, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Universal device listener for MQTT and LoRaWAN devices - listens to all registered devices and updates sensor data in real-time';

    private $mqttClients = [];
    private $isRunning = true;
    private $connectedBrokers = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specificDeviceId = $this->option('device');
        $specificBrokerId = $this->option('broker');
        $deviceType = $this->option('type') ?: 'all';
        
        $this->info("🚀 Starting Universal Device Listener");
        $this->info("📡 Device Type Filter: " . strtoupper($deviceType));
        
        if ($specificDeviceId) {
            $this->info("🎯 Specific Device: {$specificDeviceId}");
        } else {
            $this->info("🌐 Listening to ALL registered devices");
        }
        
        if ($specificBrokerId) {
            $this->info("🔗 Specific Broker ID: {$specificBrokerId}");
        } else {
            $this->info("🔗 Connecting to ALL active brokers");
        }

        try {
            // Discover and connect to brokers
            $brokersCount = $this->discoverAndConnectToBrokers($specificBrokerId, $specificDeviceId, $deviceType);
            
            if ($brokersCount === 0) {
                $this->warn("⚠️ No brokers found to connect to");
                return 1;
            }
            
            $this->info("✅ Connected to {$brokersCount} broker(s) successfully!");
            $this->info("🔄 Listening for messages from all device types... (Press Ctrl+C to stop)");
            
            // Main listening loop
            $this->startListening();
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to start device listener: " . $e->getMessage());
            Log::error('Device Listener Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 1;
        }
        
        return 0;
    }

    /**
     * Discover brokers and connect to them
     */
    private function discoverAndConnectToBrokers($specificBrokerId = null, $specificDeviceId = null, $deviceType = 'all')
    {
        // Query for brokers based on device type filter
        $query = MqttBroker::where('status', 'active');
        
        if ($deviceType !== 'all') {
            if ($deviceType === 'lorawan') {
                $query->where(function($q) {
                    $q->where('type', 'lorawan')
                      ->orWhere('host', 'like', '%thethings.industries%');
                });
            } elseif ($deviceType === 'mqtt') {
                $query->where(function($q) {
                    $q->where('type', '!=', 'lorawan')
                      ->orWhereNull('type')
                      ->orWhere('type', 'mqtt');
                });
            }
        }
        
        // If specific broker requested, filter by it
        if ($specificBrokerId) {
            $query->where('id', $specificBrokerId);
        }
        
        $brokers = $query->get();
        
        if ($brokers->isEmpty()) {
            if ($specificBrokerId) {
                $this->warn("⚠️ Specific broker ID '{$specificBrokerId}' not found or not active");
            } else {
                $this->warn("⚠️ No active brokers found for device type: {$deviceType}");
            }
            return 0;
        }
        
        $connectedCount = 0;
        
        foreach ($brokers as $broker) {
            try {
                $this->connectToBroker($broker, $specificDeviceId);
                $connectedCount++;
            } catch (\Exception $e) {
                $this->warn("⚠️ Failed to connect to broker '{$broker->name}': " . $e->getMessage());
                Log::warning('Broker Connection Failed', [
                    'broker_id' => $broker->id,
                    'broker_name' => $broker->name,
                    'error' => $e->getMessage()
                ]);
                // Continue with other brokers
                continue;
            }
        }
        
        return $connectedCount;
    }

    /**
     * Connect to a specific broker
     */
    private function connectToBroker(MqttBroker $broker, $specificDeviceId = null)
    {
        $brokerType = $this->determineBrokerType($broker);
        
        // Clean the host (remove protocol prefix if present)
        $cleanHost = $this->cleanHostname($broker->host);
        
        // Determine connection settings based on protocol
        $port = $this->getPortForProtocol($broker);
        $useTLS = $this->shouldUseTLS($broker);
        
        $this->info("🔌 Connecting to {$brokerType} broker: {$broker->name} ({$cleanHost}:{$port})");
        
        // Create unique client ID
        $clientId = 'laravel_universal_' . $broker->id . '_' . uniqid();
        
        // Determine connection settings based on protocol
        $port = $this->getPortForProtocol($broker);
        $useTLS = $this->shouldUseTLS($broker);
        
        // Create MQTT client
        $mqttClient = new MqttClient($cleanHost, $port, $clientId);
        
        // Configure connection settings
        $connectionSettings = (new ConnectionSettings())
            ->setUseTls($useTLS)
            ->setKeepAliveInterval($broker->keepalive ?: 60)
            ->setConnectTimeout(30)
            ->setSocketTimeout(30);
        
        // Configure TLS settings for secure connections
        if ($useTLS) {
            $connectionSettings->setTlsVerifyPeer(true)
                              ->setTlsSelfSignedAllowed(false);
        }
        
        // Add authentication - use hardcoded TTN credentials for LoRaWAN brokers
        if ($brokerType === 'LoRaWAN') {
            // Use hardcoded TTN credentials that work
            $connectionSettings->setUsername('laravel-backend@ptyxiakinetwork');
            $connectionSettings->setPassword('NNSXS.S44Q7UFP4YFNSADL3MINDUYCQZAO7QSW4BGWSWA.TMJ6IK457FJWIVMJY26D4ZNH5QTKZMQYJMUT4E63HJL4VHVW2WRQ');
            $this->info("🔐 Using TTN authentication for LoRaWAN broker: {$broker->name}");
        } elseif ($broker->username && $broker->password) {
            $connectionSettings->setUsername($broker->username);
            $connectionSettings->setPassword($broker->password);
            $this->info("🔐 Using authentication for broker: {$broker->name}");
        }
        
        // Connect to broker
        $mqttClient->connect($connectionSettings, true);
        
        // Store client reference
        $this->mqttClients[$broker->id] = $mqttClient;
        $this->connectedBrokers[$broker->id] = $broker;
        
        // Subscribe to device topics for this broker
        $this->subscribeToDeviceTopics($broker, $mqttClient, $specificDeviceId);
        
        $this->info("✅ Connected to {$brokerType} broker: {$broker->name}");
    }

    /**
     * Determine broker type
     */
    private function determineBrokerType(MqttBroker $broker)
    {
        if ($broker->type === 'lorawan' || str_contains($broker->host, 'thethings.industries')) {
            return 'LoRaWAN';
        }
        return 'MQTT';
    }

    /**
     * Clean hostname by removing protocol prefixes
     */
    private function cleanHostname($host)
    {
        // Remove protocol prefixes
        $host = preg_replace('/^(mqtt|mqtts|ws|wss):\/\//', '', $host);
        
        // Remove trailing slashes
        $host = rtrim($host, '/');
        
        return $host;
    }

    /**
     * Determine if TLS should be used
     */
    private function shouldUseTLS(MqttBroker $broker)
    {
        return $broker->use_ssl || 
               str_contains($broker->host, 'thethings.industries');
    }

    /**
     * Get the appropriate port for the protocol
     */
    private function getPortForProtocol(MqttBroker $broker)
    {
        // Special handling for TTN - always use secure port
        if (str_contains($broker->host, 'thethings.industries')) {
            return 8883; // TTN requires secure port
        }
        
        // For SSL brokers, use SSL port if available
        if ($broker->use_ssl && $broker->ssl_port) {
            return $broker->ssl_port;
        }
        
        // Default to the main port
        return $broker->port ?: 1883;
    }

    /**
     * Subscribe to device topics for a broker
     */
    private function subscribeToDeviceTopics(MqttBroker $broker, MqttClient $mqttClient, $specificDeviceId = null)
    {
        // Query for devices using this broker
        $query = Device::where('mqtt_broker_id', $broker->id)
            ->where('is_active', true);
        
        // If specific device requested, filter by it
        if ($specificDeviceId) {
            $query->where('device_id', $specificDeviceId);
        }
        
        $devices = $query->get();
        
        if ($devices->isEmpty()) {
            $this->warn("⚠️ No active devices found for broker: {$broker->name}");
            return;
        }
        
        $subscribedCount = 0;
        $brokerType = $this->determineBrokerType($broker);
        
        foreach ($devices as $device) {
            try {
                $this->subscribeToDevice($device, $mqttClient, $broker, $brokerType);
                $subscribedCount++;
            } catch (\Exception $e) {
                $this->warn("⚠️ Failed to subscribe to device '{$device->device_id}': " . $e->getMessage());
                Log::warning('Device Subscription Failed', [
                    'device_id' => $device->device_id,
                    'broker_id' => $broker->id,
                    'error' => $e->getMessage()
                ]);
                // Continue with other devices
                continue;
            }
        }
        
        $this->info("📋 Subscribed to {$subscribedCount} device(s) on {$brokerType} broker: {$broker->name}");
    }

    /**
     * Subscribe to a specific device's topics
     */
    private function subscribeToDevice(Device $device, MqttClient $mqttClient, MqttBroker $broker, $brokerType)
    {
        // Get device topics based on broker type
        if ($brokerType === 'LoRaWAN') {
            $topics = $this->getLoRaWANTopics($device, $broker);
        } else {
            $topics = $device->topics ?: $this->getDefaultMQTTTopics($device, $broker);
        }
        
        if (empty($topics)) {
            $this->warn("⚠️ No topics configured for device: {$device->device_id}");
            return;
        }
        
        foreach ($topics as $topic) {
            $this->info("📋 Subscribing to topic: {$topic} (Device: {$device->device_id}, Type: {$brokerType})");
            
            $mqttClient->subscribe($topic, function($receivedTopic, $message) use ($device, $broker, $brokerType) {
                $this->handleMessage($receivedTopic, $message, $device, $broker, $brokerType);
            }, 0);
            
            Log::info('Device Topic Subscribed', [
                'device_id' => $device->device_id,
                'broker_id' => $broker->id,
                'broker_type' => $brokerType,
                'topic' => $topic
            ]);
        }
    }

    /**
     * Get LoRaWAN topics for a device
     */
    private function getLoRaWANTopics(Device $device, MqttBroker $broker)
    {
        $deviceId = $device->device_id;
        // Always use the hardcoded TTN username for consistency
        $username = 'laravel-backend@ptyxiakinetwork';
        
        return [
            "v3/{$username}/devices/{$deviceId}/up"
        ];
    }

    /**
     * Get default MQTT topics for a device
     */
    private function getDefaultMQTTTopics(Device $device, MqttBroker $broker)
    {
        // Common MQTT topic patterns
        $deviceId = $device->device_id;
        
        return [
            "devices/{$deviceId}/sensors/+",
            "sensors/{$deviceId}/+",
            "{$deviceId}/sensors/+",
            "{$deviceId}/data",
            "device/{$deviceId}/+",
            $deviceId, // Simple device ID topic
        ];
    }

    /**
     * Handle incoming message
     */
    private function handleMessage($topic, $message, Device $device, MqttBroker $broker, $brokerType)
    {
        try {
            $this->info("📨 Message received on topic: {$topic} (Device: {$device->device_id}, Type: {$brokerType})");
            
            // Handle based on broker type
            if ($brokerType === 'LoRaWAN') {
                $this->handleLoRaWANMessage($topic, $message, $device, $broker);
            } else {
                $this->handleMQTTMessage($topic, $message, $device, $broker);
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error processing message: " . $e->getMessage());
            Log::error('Message Processing Error', [
                'error' => $e->getMessage(),
                'topic' => $topic,
                'message' => $message,
                'device_id' => $device->device_id,
                'broker_type' => $brokerType
            ]);
        }
    }

    /**
     * Handle LoRaWAN message
     */
    private function handleLoRaWANMessage($topic, $message, Device $device, MqttBroker $broker)
    {
        // Parse JSON message
        $payload = json_decode($message, true);
        
        if (!$payload) {
            $this->warn("⚠️ Failed to parse LoRaWAN JSON message");
            return;
        }
        
        // Log the received message
        Log::info('LoRaWAN Message Received', [
            'topic' => $topic,
            'device_id' => $device->device_id,
            'payload' => $payload
        ]);
        
        // Check if this is an uplink message with decoded payload
        if (!isset($payload['uplink_message']['decoded_payload'])) {
            $this->warn("⚠️ No decoded payload found in LoRaWAN message");
            return;
        }
        
        $decodedPayload = $payload['uplink_message']['decoded_payload'];
        $receivedAt = isset($payload['received_at']) ? 
            Carbon::parse($payload['received_at']) : 
            Carbon::now();
        
        $this->info("🔍 LoRaWAN decoded payload for '{$device->device_id}': " . json_encode($decodedPayload));
        
        // Update device status
        $device->setOnline();
        $this->info("✅ LoRaWAN device '{$device->device_id}' status updated to online");
        
        // Process sensor readings
        $sensorsUpdated = $this->processLoRaWANSensorReadings($device, $decodedPayload, $receivedAt);
        
        $this->info("🎯 Updated {$sensorsUpdated} sensors for LoRaWAN device '{$device->device_id}'");
    }

    /**
     * Handle regular MQTT message
     */
    private function handleMQTTMessage($topic, $message, Device $device, MqttBroker $broker)
    {
        // Try to parse JSON message
        $payload = json_decode($message, true);
        
        if (!$payload) {
            // If not JSON, treat as simple value
            $this->handleSimpleMQTTMessage($topic, $message, $device);
            return;
        }
        
        // Log the received message
        Log::info('MQTT Message Received', [
            'topic' => $topic,
            'device_id' => $device->device_id,
            'broker_id' => $broker->id,
            'payload' => $payload
        ]);
        
        $this->info("🔍 MQTT parsed payload for '{$device->device_id}': " . json_encode($payload));
        
        // Update device status
        $device->setOnline();
        $this->info("✅ MQTT device '{$device->device_id}' status updated to online");
        
        // Check if this is the new sensor array format
        if (isset($payload['sensors']) && is_array($payload['sensors'])) {
            $sensorsUpdated = $this->processSensorArrayMessage($device, $payload, $topic);
        } else {
            // Process as flat JSON payload
            $sensorsUpdated = $this->processMQTTSensorReadings($device, $payload, $topic);
        }
        
        $this->info("🎯 Updated {$sensorsUpdated} sensors for MQTT device '{$device->device_id}'");
    }

    /**
     * Handle simple MQTT message
     */
    private function handleSimpleMQTTMessage($topic, $message, Device $device)
    {
        // Extract sensor type from topic
        $sensorType = $this->extractSensorTypeFromTopic($topic);
        
        if (!$sensorType) {
            $this->warn("⚠️ Could not determine sensor type from topic: {$topic}");
            return;
        }
        
        // Try to parse numeric value
        $value = is_numeric($message) ? (float)$message : $message;
        
        $this->info("📊 Simple MQTT message - Sensor: {$sensorType}, Value: {$value}");
        
        // Create sensor data array
        $sensorData = [$sensorType => $value];
        
        // Process as sensor readings
        $sensorsUpdated = $this->processMQTTSensorReadings($device, $sensorData, $topic);
        
        $this->info("🎯 Updated {$sensorsUpdated} sensors for MQTT device '{$device->device_id}'");
    }

    /**
     * Process LoRaWAN sensor readings
     */
    private function processLoRaWANSensorReadings(Device $device, array $decodedPayload, Carbon $timestamp)
    {
        $sensorMappings = [
            'temperature' => ['type' => 'temperature', 'unit' => '°C'],
            'humidity' => ['type' => 'humidity', 'unit' => '%'],
            'altitude' => ['type' => 'altitude', 'unit' => 'm'],
            'battery' => ['type' => 'battery', 'unit' => '%'],
            'latitude' => ['type' => 'latitude', 'unit' => '°'],
            'longitude' => ['type' => 'longitude', 'unit' => '°'],
            'gps_fix' => ['type' => 'gps_fix', 'unit' => ''],
            'gps_fix_type' => ['type' => 'gps_fix_type', 'unit' => '']
        ];
        
        $sensorsUpdated = 0;
        
        foreach ($decodedPayload as $sensorKey => $value) {
            if (!isset($sensorMappings[$sensorKey])) {
                $this->warn("⚠️ Unknown LoRaWAN sensor type: {$sensorKey}");
                continue;
            }
            
            $mapping = $sensorMappings[$sensorKey];
            
            // Find or create sensor
            $sensor = Sensor::firstOrCreate(
                [
                    'device_id' => $device->id,
                    'sensor_type' => $mapping['type'],
                    'sensor_name' => ucfirst(str_replace('_', ' ', $sensorKey))
                ],
                [
                    'user_id' => $device->user_id,
                    'description' => 'LoRaWAN ' . ucfirst(str_replace('_', ' ', $sensorKey)) . ' sensor',
                    'location' => $device->location,
                    'unit' => $mapping['unit'],
                    'enabled' => true,
                    'alert_enabled' => false
                ]
            );
            
            // Update sensor reading
            $sensor->updateReading($value, $timestamp);
            $sensorsUpdated++;
            
            $this->line("  📊 {$sensor->sensor_name}: {$value} {$mapping['unit']}");
            
            Log::info('LoRaWAN Sensor Updated via Universal Listener', [
                'device_id' => $device->device_id,
                'sensor_type' => $mapping['type'],
                'sensor_name' => $sensor->sensor_name,
                'value' => $value,
                'timestamp' => $timestamp->toDateTimeString()
            ]);
        }
        
        return $sensorsUpdated;
    }

    /**
     * Process sensor array message format
     */
    private function processSensorArrayMessage(Device $device, array $payload, $topic)
    {
        $sensorsUpdated = 0;
        $timestamp = Carbon::now();
        
        if (!isset($payload['sensors']) || !is_array($payload['sensors'])) {
            $this->warn("⚠️ Invalid sensor array format");
            return 0;
        }
        
        foreach ($payload['sensors'] as $sensorData) {
            if (!isset($sensorData['type']) || !isset($sensorData['value'])) {
                $this->warn("⚠️ Sensor missing type or value: " . json_encode($sensorData));
                continue;
            }
            
            $sensorType = $sensorData['type'];
            $sensorValue = $sensorData['value'];
            
            // Handle geolocation sensors with subtype
            if ($sensorType === 'geolocation' && isset($sensorData['subtype'])) {
                $sensorType = $sensorData['subtype']; // Use latitude or longitude as type
            }
            
            // Parse value (remove units if present)
            $cleanValue = $this->parseValueFromString($sensorValue);
            
            // Determine sensor info
            $sensorInfo = $this->determineSensorInfoFromType($sensorType, $cleanValue);
            
            if (!$sensorInfo) {
                $this->warn("⚠️ Unknown sensor type: {$sensorType}");
                continue;
            }
            
            // Find or create sensor
            $sensor = Sensor::firstOrCreate(
                [
                    'device_id' => $device->id,
                    'sensor_type' => $sensorInfo['type'],
                    'sensor_name' => $sensorInfo['name']
                ],
                [
                    'user_id' => $device->user_id,
                    'description' => 'MQTT ' . $sensorInfo['name'] . ' sensor',
                    'location' => $device->location,
                    'unit' => $sensorInfo['unit'],
                    'enabled' => true,
                    'alert_enabled' => false,
                ]
            );
            
            // Update sensor reading
            $sensor->updateReading($cleanValue, $timestamp);
            $sensorsUpdated++;
            
            $this->line("  📊 {$sensor->sensor_name}: {$cleanValue} {$sensorInfo['unit']}");
            
            Log::info('MQTT Sensor Array Updated via Universal Listener', [
                'device_id' => $device->device_id,
                'sensor_type' => $sensorInfo['type'],
                'sensor_name' => $sensor->sensor_name,
                'value' => $cleanValue,
                'original_value' => $sensorValue,
                'timestamp' => $timestamp->toDateTimeString()
            ]);
        }
        
        return $sensorsUpdated;
    }

    /**
     * Parse numeric value from string (remove units)
     */
    private function parseValueFromString($value)
    {
        if (is_numeric($value)) {
            return (float)$value;
        }
        
        // Extract numeric value from strings like "25.3 celsius" or "65 percent"
        if (preg_match('/^([+-]?\d*\.?\d+)/', $value, $matches)) {
            return (float)$matches[1];
        }
        
        return $value; // Return as-is if not numeric
    }

    /**
     * Determine sensor info from sensor type
     */
    private function determineSensorInfoFromType($sensorType, $value)
    {
        $sensorMappings = [
            // Temperature sensors
            'thermal' => ['type' => 'thermal', 'name' => 'Thermal Sensor', 'unit' => '°C'],
            'temperature' => ['type' => 'temperature', 'name' => 'Temperature', 'unit' => '°C'],
            
            // Humidity sensors
            'humidity' => ['type' => 'humidity', 'name' => 'Humidity', 'unit' => '%'],
            
            // Light sensors
            'light' => ['type' => 'light', 'name' => 'Light', 'unit' => '%'],
            
            // Potentiometer
            'potentiometer' => ['type' => 'potentiometer', 'name' => 'Potentiometer', 'unit' => '%'],
            
            // GPS sensors
            'latitude' => ['type' => 'latitude', 'name' => 'Latitude', 'unit' => '°'],
            'longitude' => ['type' => 'longitude', 'name' => 'Longitude', 'unit' => '°'],
            
            // Generic geolocation
            'geolocation' => ['type' => 'geolocation', 'name' => 'Geolocation', 'unit' => '°'],
        ];
        
        $lowerType = strtolower($sensorType);
        
        if (isset($sensorMappings[$lowerType])) {
            return $sensorMappings[$lowerType];
        }
        
        // If not found in mappings, create generic sensor
        return [
            'type' => $lowerType,
            'name' => ucfirst(str_replace('_', ' ', $sensorType)),
            'unit' => $this->guessUnitFromValue($value)
        ];
    }

    /**
     * Process MQTT sensor readings
     */
    private function processMQTTSensorReadings(Device $device, array $payload, $topic)
    {
        $sensorsUpdated = 0;
        $timestamp = Carbon::now();
        
        foreach ($payload as $key => $value) {
            // Skip non-sensor data
            if (in_array($key, ['timestamp', 'device_id', 'message_id', 'qos', 'retain'])) {
                continue;
            }
            
            // Determine sensor type and unit
            $sensorInfo = $this->determineMQTTSensorInfo($key, $value, $topic);
            
            if (!$sensorInfo) {
                $this->warn("⚠️ Skipping unknown MQTT sensor data: {$key} = {$value}");
                continue;
            }
            
            // Find or create sensor
            $sensor = Sensor::firstOrCreate(
                [
                    'device_id' => $device->id,
                    'sensor_type' => $sensorInfo['type'],
                    'sensor_name' => $sensorInfo['name']
                ],
                [
                    'user_id' => $device->user_id,
                    'description' => 'MQTT ' . $sensorInfo['name'] . ' sensor',
                    'location' => $device->location,
                    'unit' => $sensorInfo['unit'],
                    'enabled' => true,
                    'alert_enabled' => false,
                ]
            );
            
            // Update sensor reading
            $sensor->updateReading($value, $timestamp);
            $sensorsUpdated++;
            
            $this->line("  📊 {$sensor->sensor_name}: {$value} {$sensorInfo['unit']}");
            
            Log::info('MQTT Sensor Updated via Universal Listener', [
                'device_id' => $device->device_id,
                'sensor_type' => $sensorInfo['type'],
                'sensor_name' => $sensor->sensor_name,
                'value' => $value,
                'timestamp' => $timestamp->toDateTimeString()
            ]);
        }
        
        return $sensorsUpdated;
    }

    /**
     * Extract sensor type from MQTT topic
     */
    private function extractSensorTypeFromTopic($topic)
    {
        // Common patterns to extract sensor type
        $patterns = [
            '/sensors\/[^\/]+\/([^\/]+)$/',  // sensors/device_id/sensor_type
            '/devices\/[^\/]+\/sensors\/([^\/]+)$/',  // devices/device_id/sensors/sensor_type
            '/[^\/]+\/sensors\/([^\/]+)$/',  // device_id/sensors/sensor_type
            '/[^\/]+\/([^\/]+)$/',  // device_id/sensor_type
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $topic, $matches)) {
                return $matches[1];
            }
        }
        
        // If no pattern matches, use the last part of the topic
        $parts = explode('/', $topic);
        return end($parts);
    }

    /**
     * Determine MQTT sensor information from key and value
     */
    private function determineMQTTSensorInfo($key, $value, $topic)
    {
        // Sensor type mappings
        $sensorMappings = [
            // Temperature sensors
            'temperature' => ['type' => 'temperature', 'name' => 'Temperature', 'unit' => '°C'],
            'temp' => ['type' => 'temperature', 'name' => 'Temperature', 'unit' => '°C'],
            'celsius' => ['type' => 'temperature', 'name' => 'Temperature', 'unit' => '°C'],
            
            // Humidity sensors
            'humidity' => ['type' => 'humidity', 'name' => 'Humidity', 'unit' => '%'],
            'humid' => ['type' => 'humidity', 'name' => 'Humidity', 'unit' => '%'],
            'rh' => ['type' => 'humidity', 'name' => 'Humidity', 'unit' => '%'],
            
            // Pressure sensors
            'pressure' => ['type' => 'pressure', 'name' => 'Pressure', 'unit' => 'hPa'],
            'press' => ['type' => 'pressure', 'name' => 'Pressure', 'unit' => 'hPa'],
            'atm' => ['type' => 'pressure', 'name' => 'Pressure', 'unit' => 'hPa'],
            
            // Light sensors
            'light' => ['type' => 'light', 'name' => 'Light', 'unit' => 'lux'],
            'lux' => ['type' => 'light', 'name' => 'Light', 'unit' => 'lux'],
            'brightness' => ['type' => 'light', 'name' => 'Light', 'unit' => 'lux'],
            
            // Motion sensors
            'motion' => ['type' => 'motion', 'name' => 'Motion', 'unit' => ''],
            'pir' => ['type' => 'motion', 'name' => 'Motion', 'unit' => ''],
            'movement' => ['type' => 'motion', 'name' => 'Motion', 'unit' => ''],
            
            // Battery sensors
            'battery' => ['type' => 'battery', 'name' => 'Battery', 'unit' => '%'],
            'bat' => ['type' => 'battery', 'name' => 'Battery', 'unit' => '%'],
            'power' => ['type' => 'battery', 'name' => 'Battery', 'unit' => '%'],
            
            // GPS sensors
            'latitude' => ['type' => 'latitude', 'name' => 'Latitude', 'unit' => '°'],
            'lat' => ['type' => 'latitude', 'name' => 'Latitude', 'unit' => '°'],
            'longitude' => ['type' => 'longitude', 'name' => 'Longitude', 'unit' => '°'],
            'lng' => ['type' => 'longitude', 'name' => 'Longitude', 'unit' => '°'],
            'lon' => ['type' => 'longitude', 'name' => 'Longitude', 'unit' => '°'],
            
            // Generic sensors
            'value' => ['type' => 'sensor', 'name' => 'Sensor Value', 'unit' => ''],
            'data' => ['type' => 'sensor', 'name' => 'Sensor Data', 'unit' => ''],
        ];
        
        $lowerKey = strtolower($key);
        
        if (isset($sensorMappings[$lowerKey])) {
            return $sensorMappings[$lowerKey];
        }
        
        // If not found in mappings, create generic sensor
        return [
            'type' => $lowerKey,
            'name' => ucfirst(str_replace('_', ' ', $key)),
            'unit' => $this->guessUnitFromValue($value)
        ];
    }

    /**
     * Guess unit from value
     */
    private function guessUnitFromValue($value)
    {
        if (!is_numeric($value)) {
            return '';
        }
        
        $numValue = (float)$value;
        
        // Simple heuristics
        if ($numValue >= 0 && $numValue <= 100) {
            return '%'; // Likely percentage
        }
        
        if ($numValue > 100 && $numValue < 1000) {
            return 'hPa'; // Likely pressure
        }
        
        return ''; // Unknown unit
    }

    /**
     * Start the main listening loop
     */
    private function startListening()
    {
        while ($this->isRunning) {
            try {
                // Process messages for all connected brokers
                foreach ($this->mqttClients as $brokerId => $mqttClient) {
                    $mqttClient->loop(true, true);
                }
                
                // Small delay to prevent high CPU usage
                usleep(100000); // 0.1 seconds
                
            } catch (\Exception $e) {
                $this->error("❌ Error in listening loop: " . $e->getMessage());
                Log::error('Universal Device Listener Loop Error', ['error' => $e->getMessage()]);
                
                // Try to reconnect after error
                sleep(5);
                $this->attemptReconnection();
            }
        }
    }

    /**
     * Attempt to reconnect to failed brokers
     */
    private function attemptReconnection()
    {
        $this->info("🔄 Attempting to reconnect to brokers...");
        
        foreach ($this->connectedBrokers as $brokerId => $broker) {
            try {
                if (!isset($this->mqttClients[$brokerId])) {
                    $this->connectToBroker($broker);
                    $this->info("🔄 Reconnected to broker: {$broker->name}");
                }
            } catch (\Exception $e) {
                $this->warn("❌ Failed to reconnect to broker {$broker->name}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle graceful shutdown
     */
    public function handleShutdown($signal)
    {
        $this->info("\n🛑 Received shutdown signal. Closing connections...");
        $this->isRunning = false;
        
        try {
            foreach ($this->mqttClients as $brokerId => $mqttClient) {
                $brokerName = $this->connectedBrokers[$brokerId]->name ?? "Broker {$brokerId}";
                $mqttClient->disconnect();
                $this->info("✅ Disconnected from {$brokerName}");
            }
        } catch (\Exception $e) {
            $this->warn("⚠️ Error during shutdown: " . $e->getMessage());
        }
        
        exit(0);
    }
}
