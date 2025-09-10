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

class MQTTListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:listen {--device= : Optional specific device ID to listen for (if not provided, listens to all MQTT devices)} {--broker= : Optional specific MQTT broker ID to connect to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actively listen for MQTT messages from all registered devices and update sensor data';

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
        
        if ($specificDeviceId) {
            $this->info("🚀 Starting MQTT Listener for specific device: {$specificDeviceId}");
        } else {
            $this->info("🚀 Starting MQTT Listener for ALL registered MQTT devices");
        }
        
        if ($specificBrokerId) {
            $this->info("📡 Connecting to specific MQTT broker ID: {$specificBrokerId}");
        } else {
            $this->info("📡 Connecting to ALL MQTT brokers...");
        }

        try {
            // Main listening loop with broker discovery
            $this->startListeningWithDiscovery($specificBrokerId, $specificDeviceId);
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to start MQTT listener: " . $e->getMessage());
            Log::error('MQTT Listener Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 1;
        }
        
        return 0;
    }

    /**
     * Discover MQTT brokers and connect to them
     */
    private function discoverAndConnectToBrokers($specificBrokerId = null, $specificDeviceId = null)
    {
        // Query for MQTT brokers (excluding LoRaWAN)
        $query = MqttBroker::where('status', 'active')
            ->where(function($q) {
                $q->where('type', '!=', 'lorawan')
                  ->orWhereNull('type')
                  ->orWhere('type', 'mqtt');
            });
        
        // If specific broker requested, filter by it
        if ($specificBrokerId) {
            $query->where('id', $specificBrokerId);
        }
        
        $brokers = $query->get();
        
        if ($brokers->isEmpty()) {
            if ($specificBrokerId) {
                $this->warn("⚠️ Specific MQTT broker ID '{$specificBrokerId}' not found or not active");
            } else {
                $this->warn("⚠️ No active MQTT brokers found in database");
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
                Log::warning('MQTT Broker Connection Failed', [
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
     * Connect to a specific MQTT broker
     */
    private function connectToBroker(MqttBroker $broker, $specificDeviceId = null)
    {
        $this->info("🔌 Connecting to MQTT broker: {$broker->name} ({$broker->host}:{$broker->port})");
        
        // Create unique client ID
        $clientId = 'laravel_mqtt_' . $broker->id . '_' . uniqid();
        
        // Determine connection settings based on protocol
        $port = $this->getPortForProtocol($broker);
        $useTLS = in_array($broker->protocol, ['mqtts', 'wss']);
        
        // Create MQTT client
        $mqttClient = new MqttClient($broker->host, $port, $clientId);
        
        // Configure connection settings
        $connectionSettings = (new ConnectionSettings())
            ->setUseTls($useTLS)
            ->setKeepAliveInterval($broker->keepalive ?: 60)
            ->setConnectTimeout(30)
            ->setSocketTimeout(30);
        
        // Add authentication if provided
        if ($broker->username && $broker->password) {
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
        
        $this->info("✅ Connected to MQTT broker: {$broker->name}");
    }

    /**
     * Get the appropriate port for the protocol
     */
    private function getPortForProtocol(MqttBroker $broker)
    {
        switch ($broker->protocol) {
            case 'mqtt':
                return $broker->mqtt_port ?: 1883;
            case 'mqtts':
                return $broker->mqtts_port ?: 8883;
            case 'ws':
                return $broker->ws_port ?: 8083;
            case 'wss':
                return $broker->wss_port ?: 8084;
            default:
                return $broker->port ?: 1883;
        }
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
        
        foreach ($devices as $device) {
            try {
                $this->subscribeToDevice($device, $mqttClient, $broker);
                $subscribedCount++;
            } catch (\Exception $e) {
                $this->warn("⚠️ Failed to subscribe to device '{$device->device_id}': " . $e->getMessage());
                Log::warning('MQTT Device Subscription Failed', [
                    'device_id' => $device->device_id,
                    'broker_id' => $broker->id,
                    'error' => $e->getMessage()
                ]);
                // Continue with other devices
                continue;
            }
        }
        
        $this->info("📋 Subscribed to {$subscribedCount} device(s) on broker: {$broker->name}");
    }

    /**
     * Subscribe to a specific device's topics
     */
    private function subscribeToDevice(Device $device, MqttClient $mqttClient, MqttBroker $broker)
    {
        // Get device topics or use default patterns
        $topics = $device->topics ?: $this->getDefaultTopics($device, $broker);
        
        if (empty($topics)) {
            $this->warn("⚠️ No topics configured for device: {$device->device_id}");
            return;
        }
        
        foreach ($topics as $topic) {
            $this->info("📋 Subscribing to topic: {$topic} (Device: {$device->device_id})");
            
            $mqttClient->subscribe($topic, function($receivedTopic, $message) use ($device, $broker) {
                $this->handleMessage($receivedTopic, $message, $device, $broker);
            }, 0);
            
            Log::info('MQTT Device Topic Subscribed', [
                'device_id' => $device->device_id,
                'broker_id' => $broker->id,
                'topic' => $topic
            ]);
        }
    }

    /**
     * Get default topics for a device
     */
    private function getDefaultTopics(Device $device, MqttBroker $broker)
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
     * Handle incoming MQTT message
     */
    private function handleMessage($topic, $message, Device $device, MqttBroker $broker)
    {
        try {
            $this->info("📨 Message received on topic: {$topic} (Device: {$device->device_id})");
            
            // Try to parse JSON message
            $payload = json_decode($message, true);
            
            if (!$payload) {
                // If not JSON, treat as simple value
                $this->handleSimpleMessage($topic, $message, $device);
                return;
            }
            
            // Log the received message
            Log::info('MQTT Message Received', [
                'topic' => $topic,
                'device_id' => $device->device_id,
                'broker_id' => $broker->id,
                'payload' => $payload
            ]);
            
            $this->info("🔍 Parsed payload for '{$device->device_id}': " . json_encode($payload));
            
            // Update device status
            $device->setOnline();
            $this->info("✅ Device '{$device->device_id}' status updated to online");
            
            // Process sensor readings
            $sensorsUpdated = $this->processSensorReadings($device, $payload, $topic);
            
            $this->info("🎯 Updated {$sensorsUpdated} sensors for device '{$device->device_id}'");
            
        } catch (\Exception $e) {
            $this->error("❌ Error processing message: " . $e->getMessage());
            Log::error('MQTT Message Processing Error', [
                'error' => $e->getMessage(),
                'topic' => $topic,
                'message' => $message,
                'device_id' => $device->device_id
            ]);
        }
    }

    /**
     * Handle simple (non-JSON) message
     */
    private function handleSimpleMessage($topic, $message, Device $device)
    {
        // Extract sensor type from topic
        $sensorType = $this->extractSensorTypeFromTopic($topic);
        
        if (!$sensorType) {
            $this->warn("⚠️ Could not determine sensor type from topic: {$topic}");
            return;
        }
        
        // Try to parse numeric value
        $value = is_numeric($message) ? (float)$message : $message;
        
        $this->info("📊 Simple message - Sensor: {$sensorType}, Value: {$value}");
        
        // Create sensor data array
        $sensorData = [$sensorType => $value];
        
        // Process as sensor readings
        $sensorsUpdated = $this->processSensorReadings($device, $sensorData, $topic);
        
        $this->info("🎯 Updated {$sensorsUpdated} sensors for device '{$device->device_id}'");
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
     * Process sensor readings from payload
     */
    private function processSensorReadings(Device $device, array $payload, $topic)
    {
        $sensorsUpdated = 0;
        $timestamp = Carbon::now();
        
        foreach ($payload as $key => $value) {
            // Skip non-sensor data
            if (in_array($key, ['timestamp', 'device_id', 'message_id', 'qos', 'retain'])) {
                continue;
            }
            
            // Determine sensor type and unit
            $sensorInfo = $this->determineSensorInfo($key, $value, $topic);
            
            if (!$sensorInfo) {
                $this->warn("⚠️ Skipping unknown sensor data: {$key} = {$value}");
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
            
            Log::info('MQTT Sensor Updated', [
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
     * Determine sensor information from key and value
     */
    private function determineSensorInfo($key, $value, $topic)
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
     * Start listening with continuous broker discovery
     */
    private function startListeningWithDiscovery($specificBrokerId = null, $specificDeviceId = null)
    {
        $lastDiscoveryTime = 0;
        $discoveryInterval = 30; // Check for new brokers every 30 seconds
        $hasConnectedBrokers = false;
        
        $this->info("🔄 Starting continuous listening mode... (Press Ctrl+C to stop)");
        
        while ($this->isRunning) {
            try {
                $currentTime = time();
                
                // Periodically discover and connect to new brokers
                if ($currentTime - $lastDiscoveryTime >= $discoveryInterval) {
                    $brokersCount = $this->discoverAndConnectToBrokers($specificBrokerId, $specificDeviceId);
                    
                    if ($brokersCount > 0) {
                        if (!$hasConnectedBrokers) {
                            $this->info("✅ Connected to {$brokersCount} MQTT broker(s) successfully!");
                            $hasConnectedBrokers = true;
                        }
                    } else {
                        if (!$hasConnectedBrokers) {
                            $this->warn("⚠️ No MQTT brokers found - waiting for brokers to be registered...");
                        }
                    }
                    
                    $lastDiscoveryTime = $currentTime;
                }
                
                // Process MQTT messages for all connected brokers
                if (!empty($this->mqttClients)) {
                    foreach ($this->mqttClients as $brokerId => $mqttClient) {
                        $mqttClient->loop(true, true);
                    }
                    // Small delay to prevent high CPU usage when processing messages
                    usleep(100000); // 0.1 seconds
                } else {
                    // Longer delay when no brokers are connected to reduce CPU usage
                    sleep(5);
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Error in listening loop: " . $e->getMessage());
                Log::error('MQTT Loop Error', ['error' => $e->getMessage()]);
                
                // Try to reconnect after error
                sleep(5);
                $this->attemptReconnection();
            }
        }
    }

    /**
     * Start the main listening loop
     */
    private function startListening()
    {
        while ($this->isRunning) {
            try {
                // Process MQTT messages for all connected brokers
                foreach ($this->mqttClients as $brokerId => $mqttClient) {
                    $mqttClient->loop(true, true);
                }
                
                // Small delay to prevent high CPU usage
                usleep(100000); // 0.1 seconds
                
            } catch (\Exception $e) {
                $this->error("❌ Error in listening loop: " . $e->getMessage());
                Log::error('MQTT Loop Error', ['error' => $e->getMessage()]);
                
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
        $this->info("🔄 Attempting to reconnect to MQTT brokers...");
        
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
