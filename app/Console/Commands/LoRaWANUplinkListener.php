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

class LoRaWANUplinkListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lorawan:uplink-listen {--device= : Optional specific device ID to listen for} {--broker= : Optional specific MQTT broker ID to connect to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for LoRaWAN uplink messages from all registered LoRaWAN devices';

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
            $this->info("🚀 Starting LoRaWAN Uplink Listener for specific device: {$specificDeviceId}");
        } else {
            $this->info("🚀 Starting LoRaWAN Uplink Listener for ALL registered LoRaWAN devices");
        }
        
        if ($specificBrokerId) {
            $this->info("📡 Connecting to specific MQTT broker ID: {$specificBrokerId}");
        } else {
            $this->info("📡 Connecting to ALL LoRaWAN MQTT brokers...");
        }

        try {
            // Main listening loop with broker discovery
            $this->startListeningWithDiscovery($specificBrokerId, $specificDeviceId);
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to start LoRaWAN uplink listener: " . $e->getMessage());
            Log::error('LoRaWAN Uplink Listener Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 1;
        }
        
        return 0;
    }

    /**
     * Discover LoRaWAN MQTT brokers and connect to them
     */
    private function discoverAndConnectToBrokers($specificBrokerId = null, $specificDeviceId = null)
    {
        // Query for LoRaWAN MQTT brokers
        $query = MqttBroker::where('status', 'active')
            ->where('type', 'lorawan');
        
        // If specific broker requested, filter by it
        if ($specificBrokerId) {
            $query->where('id', $specificBrokerId);
        }
        
        $brokers = $query->get();
        
        if ($brokers->isEmpty()) {
            if ($specificBrokerId) {
                $this->warn("⚠️ Specific LoRaWAN broker ID '{$specificBrokerId}' not found or not active");
            } else {
                $this->warn("⚠️ No active LoRaWAN brokers found in database");
            }
            return 0;
        }
        
        $connectedCount = 0;
        
        foreach ($brokers as $broker) {
            try {
                $this->connectToBroker($broker, $specificDeviceId);
                $connectedCount++;
            } catch (\Exception $e) {
                $this->warn("⚠️ Failed to connect to LoRaWAN broker '{$broker->name}': " . $e->getMessage());
                Log::warning('LoRaWAN Broker Connection Failed', [
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
     * Connect to a specific LoRaWAN MQTT broker
     */
    private function connectToBroker(MqttBroker $broker, $specificDeviceId = null)
    {
        $this->info("🔌 Connecting to LoRaWAN broker: {$broker->name} ({$broker->host}:{$broker->port})");
        
        // Create unique client ID
        $clientId = 'laravel_lorawan_' . $broker->id . '_' . uniqid();
        
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
            $this->info("🔐 Using authentication for LoRaWAN broker: {$broker->name}");
        }
        
        // Connect to broker
        $mqttClient->connect($connectionSettings, true);
        
        // Store client reference
        $this->mqttClients[$broker->id] = $mqttClient;
        $this->connectedBrokers[$broker->id] = $broker;
        
        // Subscribe to LoRaWAN device topics for this broker
        $this->subscribeToLoRaWANDeviceTopics($broker, $mqttClient, $specificDeviceId);
        
        $this->info("✅ Connected to LoRaWAN broker: {$broker->name}");
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
     * Subscribe to LoRaWAN device topics for a broker
     */
    private function subscribeToLoRaWANDeviceTopics(MqttBroker $broker, MqttClient $mqttClient, $specificDeviceId = null)
    {
        // Query for LoRaWAN devices using this broker
        $query = Device::where('mqtt_broker_id', $broker->id)
            ->where('is_active', true)
            ->where('protocol', 'lorawan');
        
        // If specific device requested, filter by it
        if ($specificDeviceId) {
            $query->where('device_id', $specificDeviceId);
        }
        
        $devices = $query->get();
        
        if ($devices->isEmpty()) {
            $this->warn("⚠️ No active LoRaWAN devices found for broker: {$broker->name}");
            return;
        }
        
        $subscribedCount = 0;
        
        foreach ($devices as $device) {
            try {
                $this->subscribeToLoRaWANDevice($device, $mqttClient, $broker);
                $subscribedCount++;
            } catch (\Exception $e) {
                $this->warn("⚠️ Failed to subscribe to LoRaWAN device '{$device->device_id}': " . $e->getMessage());
                Log::warning('LoRaWAN Device Subscription Failed', [
                    'device_id' => $device->device_id,
                    'broker_id' => $broker->id,
                    'error' => $e->getMessage()
                ]);
                // Continue with other devices
                continue;
            }
        }
        
        $this->info("📋 Subscribed to {$subscribedCount} LoRaWAN device(s) on broker: {$broker->name}");
    }

    /**
     * Subscribe to a specific LoRaWAN device's uplink topics
     */
    private function subscribeToLoRaWANDevice(Device $device, MqttClient $mqttClient, MqttBroker $broker)
    {
        // Get LoRaWAN uplink topics
        $topics = $this->getLoRaWANTopics($device, $broker);
        
        if (empty($topics)) {
            $this->warn("⚠️ No LoRaWAN topics configured for device: {$device->device_id}");
            return;
        }
        
        foreach ($topics as $topic) {
            $this->info("📋 Subscribing to LoRaWAN uplink topic: {$topic} (Device: {$device->device_id})");
            
            $mqttClient->subscribe($topic, function($receivedTopic, $message) use ($device, $broker) {
                $this->handleLoRaWANMessage($receivedTopic, $message, $device, $broker);
            }, 0);
            
            Log::info('LoRaWAN Device Topic Subscribed', [
                'device_id' => $device->device_id,
                'broker_id' => $broker->id,
                'topic' => $topic
            ]);
        }
    }

    /**
     * Get LoRaWAN uplink topics for a device
     */
    private function getLoRaWANTopics(Device $device, MqttBroker $broker)
    {
        $deviceId = $device->device_id;
        $username = $broker->username;
        
        // LoRaWAN uplink topic pattern: v3/{username}/devices/{device_id}/up
        return [
            "v3/{$username}/devices/{$deviceId}/up"
        ];
    }

    /**
     * Handle incoming LoRaWAN uplink message
     */
    private function handleLoRaWANMessage($topic, $message, Device $device, MqttBroker $broker)
    {
        try {
            $this->info("📨 LoRaWAN uplink received on topic: {$topic} (Device: {$device->device_id})");
            
            // Try to parse JSON message
            $payload = json_decode($message, true);
            
            if (!$payload) {
                $this->warn("⚠️ Could not parse LoRaWAN message as JSON");
                Log::warning('LoRaWAN Invalid JSON', [
                    'topic' => $topic,
                    'message' => $message,
                    'device_id' => $device->device_id
                ]);
                return;
            }
            
            // Log the received message
            Log::info('LoRaWAN Uplink Message Received', [
                'topic' => $topic,
                'device_id' => $device->device_id,
                'broker_id' => $broker->id,
                'payload' => $payload
            ]);
            
            $this->info("🔍 Parsed LoRaWAN payload for '{$device->device_id}': " . json_encode($payload, JSON_UNESCAPED_SLASHES));
            
            // Update device status
            $device->setOnline();
            $this->info("✅ Device '{$device->device_id}' status updated to online");
            
            // Process LoRaWAN uplink message
            $sensorsUpdated = $this->processLoRaWANUplink($device, $payload);
            
            $this->info("🎯 Updated {$sensorsUpdated} sensors for LoRaWAN device '{$device->device_id}'");
            
        } catch (\Exception $e) {
            $this->error("❌ Error processing LoRaWAN message: " . $e->getMessage());
            Log::error('LoRaWAN Message Processing Error', [
                'error' => $e->getMessage(),
                'topic' => $topic,
                'message' => $message,
                'device_id' => $device->device_id
            ]);
        }
    }

    /**
     * Process LoRaWAN uplink message
     */
    private function processLoRaWANUplink(Device $device, array $payload)
    {
        // Check if this is a valid LoRaWAN uplink message
        if (!isset($payload['uplink_message'])) {
            $this->warn("⚠️ No uplink_message found in LoRaWAN payload");
            return 0;
        }
        
        $uplinkMessage = $payload['uplink_message'];
        
        // Check if decoded payload exists
        if (!isset($uplinkMessage['decoded_payload'])) {
            $this->warn("⚠️ No decoded_payload found in LoRaWAN uplink message");
            return 0;
        }
        
        $decodedPayload = $uplinkMessage['decoded_payload'];
        $receivedAt = isset($payload['received_at']) ? Carbon::parse($payload['received_at']) : Carbon::now();
        
        $this->info("🔍 Processing LoRaWAN decoded payload: " . json_encode($decodedPayload));
        
        // Process sensor readings from the decoded payload
        return $this->processSensorReadings($device, $decodedPayload, $receivedAt);
    }

    /**
     * Process sensor readings from LoRaWAN decoded payload
     */
    private function processSensorReadings(Device $device, array $decodedPayload, Carbon $timestamp)
    {
        $sensorsUpdated = 0;
        
        Log::info('LoRaWAN: Processing decoded payload', [
            'device_id' => $device->device_id,
            'payload' => $decodedPayload,
            'sensor_count' => count($decodedPayload)
        ]);
        
        // Define sensor type mappings based on your payload structure
        $sensorMappings = [
            'temperature' => ['type' => 'temperature', 'name' => 'Temperature', 'unit' => '°C'],
            'humidity' => ['type' => 'humidity', 'name' => 'Humidity', 'unit' => '%'],
            'altitude' => ['type' => 'altitude', 'name' => 'Altitude', 'unit' => 'm'],
            'battery' => ['type' => 'battery', 'name' => 'Battery', 'unit' => '%'],
            'latitude' => ['type' => 'latitude', 'name' => 'Latitude', 'unit' => '°'],
            'longitude' => ['type' => 'longitude', 'name' => 'Longitude', 'unit' => '°'],
            'gps_fix' => ['type' => 'gps_fix', 'name' => 'GPS Fix', 'unit' => ''],
            'gps_fix_type' => ['type' => 'gps_fix_type', 'name' => 'GPS Fix Type', 'unit' => '']
        ];
        
        foreach ($decodedPayload as $sensorKey => $value) {
            if (!isset($sensorMappings[$sensorKey])) {
                $this->line("⚠️ Unknown sensor type, creating generic sensor: {$sensorKey}");
                
                // Create generic sensor for unknown types
                $mapping = [
                    'type' => strtolower($sensorKey),
                    'name' => ucfirst(str_replace('_', ' ', $sensorKey)),
                    'unit' => $this->guessUnitFromValue($value)
                ];
            } else {
                $mapping = $sensorMappings[$sensorKey];
            }
            
            // Find or create individual sensor for each type
            $sensor = Sensor::firstOrCreate(
                [
                    'device_id' => $device->id,
                    'sensor_type' => $mapping['type'],
                    'sensor_name' => $mapping['name']
                ],
                [
                    'user_id' => $device->user_id,
                    'description' => 'LoRaWAN ' . $mapping['name'] . ' sensor',
                    'location' => $device->location,
                    'unit' => $mapping['unit'],
                    'enabled' => true,
                    'alert_enabled' => false
                ]
            );
            
            // Update sensor reading with individual value
            $sensor->updateReading($value, $timestamp);
            $sensorsUpdated++;
            
            $this->line("  📊 {$sensor->sensor_name}: {$value} {$mapping['unit']}");
            
            Log::info('LoRaWAN sensor updated', [
                'device_id' => $device->device_id,
                'sensor_type' => $mapping['type'],
                'sensor_name' => $sensor->sensor_name,
                'value' => $value,
                'unit' => $mapping['unit'],
                'timestamp' => $timestamp->toDateTimeString()
            ]);
        }
        
        Log::info('LoRaWAN: Sensor processing completed', [
            'device_id' => $device->device_id,
            'sensors_updated' => $sensorsUpdated,
            'total_payload_items' => count($decodedPayload)
        ]);
        
        return $sensorsUpdated;
    }

    /**
     * Guess unit from value for unknown sensor types
     */
    private function guessUnitFromValue($value)
    {
        if (!is_numeric($value)) {
            return '';
        }
        
        $numValue = (float)$value;
        
        // Simple heuristics for LoRaWAN sensors
        if ($numValue >= 0 && $numValue <= 100) {
            return '%'; // Likely percentage
        }
        
        if ($numValue > 100 && $numValue < 1000) {
            return 'hPa'; // Likely pressure
        }
        
        if ($numValue > 1000) {
            return 'm'; // Likely altitude or distance
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
        
        $this->info("🔄 Starting continuous LoRaWAN uplink listening mode... (Press Ctrl+C to stop)");
        
        while ($this->isRunning) {
            try {
                $currentTime = time();
                
                // Periodically discover and connect to new brokers
                if ($currentTime - $lastDiscoveryTime >= $discoveryInterval) {
                    $brokersCount = $this->discoverAndConnectToBrokers($specificBrokerId, $specificDeviceId);
                    
                    if ($brokersCount > 0) {
                        if (!$hasConnectedBrokers) {
                            $this->info("✅ Connected to {$brokersCount} LoRaWAN broker(s) successfully!");
                            $hasConnectedBrokers = true;
                        }
                    } else {
                        if (!$hasConnectedBrokers) {
                            $this->warn("⚠️ No LoRaWAN brokers found - waiting for brokers to be registered...");
                        }
                    }
                    
                    $lastDiscoveryTime = $currentTime;
                }
                
                // Process LoRaWAN uplink messages for all connected brokers
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
                $this->error("❌ Error in LoRaWAN listening loop: " . $e->getMessage());
                Log::error('LoRaWAN Loop Error', ['error' => $e->getMessage()]);
                
                // Try to reconnect after error
                sleep(5);
                $this->attemptReconnection();
            }
        }
    }

    /**
     * Attempt to reconnect to failed LoRaWAN brokers
     */
    private function attemptReconnection()
    {
        $this->info("🔄 Attempting to reconnect to LoRaWAN brokers...");
        
        foreach ($this->connectedBrokers as $brokerId => $broker) {
            try {
                if (!isset($this->mqttClients[$brokerId])) {
                    $this->connectToBroker($broker);
                    $this->info("🔄 Reconnected to LoRaWAN broker: {$broker->name}");
                }
            } catch (\Exception $e) {
                $this->warn("❌ Failed to reconnect to LoRaWAN broker {$broker->name}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle graceful shutdown
     */
    public function handleShutdown($signal)
    {
        $this->info("\n🛑 Received shutdown signal. Closing LoRaWAN connections...");
        $this->isRunning = false;
        
        try {
            foreach ($this->mqttClients as $brokerId => $mqttClient) {
                $brokerName = $this->connectedBrokers[$brokerId]->name ?? "LoRaWAN Broker {$brokerId}";
                $mqttClient->disconnect();
                $this->info("✅ Disconnected from {$brokerName}");
            }
        } catch (\Exception $e) {
            $this->warn("⚠️ Error during LoRaWAN shutdown: " . $e->getMessage());
        }
        
        exit(0);
    }
}
