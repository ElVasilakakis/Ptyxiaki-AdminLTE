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

class MultiBrokerMqttListener extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mqtt:listen-multi {--device= : Optional specific device ID} {--broker= : Optional specific broker ID} {--timeout=3600} {--memory=256}';

    /**
     * The console command description.
     */
    protected $description = 'Listen to multiple active MQTT brokers from database and update sensor data';

    private $mqttConnections = [];
    private $isRunning = true;
    private $lastBrokerScan = 0;
    private $brokerScanInterval = 30; // Check for new brokers every 30 seconds
    private $startTime;
    private $maxRunTime;
    private $maxMemory;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specificDeviceId = $this->option('device');
        $specificBrokerId = $this->option('broker');
        
        // Set limits
        $this->maxRunTime = (int) $this->option('timeout');
        $this->maxMemory = (int) $this->option('memory');
        $this->startTime = time();

        $this->info("🚀 Starting Multi-Broker MQTT Listener (timeout: {$this->maxRunTime}s, memory: {$this->maxMemory}MB)");
        
        if ($specificBrokerId) {
            $this->info("📡 Listening to specific broker ID: {$specificBrokerId}");
        } else {
            $this->info("📡 Listening to ALL active MQTT brokers");
        }

        if ($specificDeviceId) {
            $this->info("🎯 Filtering for specific device: {$specificDeviceId}");
        }

        // Set up signal handlers for graceful shutdown
        $this->registerSignalHandlers();

        try {
            $this->startMultiBrokerListening($specificDeviceId, $specificBrokerId);
        } catch (\Exception $e) {
            $this->error("❌ Failed to start multi-broker listener: " . $e->getMessage());
            Log::error('Multi-Broker MQTT Listener Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Register signal handlers for graceful shutdown
     */
    private function registerSignalHandlers()
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }
    }

    /**
     * Start listening to multiple brokers
     */
    private function startMultiBrokerListening($specificDeviceId = null, $specificBrokerId = null)
    {
        $this->info("🔄 Starting multi-broker listening mode... (Press Ctrl+C to stop)");

        while ($this->isRunning) {
            try {
                // Handle signals
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                // Check time limit
                if ((time() - $this->startTime) >= $this->maxRunTime) {
                    $this->info("⏰ Time limit reached ({$this->maxRunTime}s) - stopping gracefully");
                    break;
                }

                // Check memory limit
                $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
                if ($memoryUsage > $this->maxMemory) {
                    $this->warn("🧠 Memory limit reached ({$memoryUsage}MB/{$this->maxMemory}MB) - stopping gracefully");
                    break;
                }

                $currentTime = time();

                // Periodically scan for new or updated brokers
                if ($currentTime - $this->lastBrokerScan >= $this->brokerScanInterval) {
                    $this->scanAndConnectBrokers($specificBrokerId);
                    $this->lastBrokerScan = $currentTime;
                }

                // Process messages from all active connections
                $this->processAllConnections();

                // Small delay to prevent high CPU usage
                usleep(100000); // 0.1 seconds

            } catch (\Exception $e) {
                $this->error("❌ Error in multi-broker listening loop: " . $e->getMessage());
                Log::error('Multi-Broker MQTT Loop Error', ['error' => $e->getMessage()]);
                
                // Clean up broken connections
                $this->cleanupBrokenConnections();
                sleep(5);
            }
        }

        // Cleanup before exit
        $this->cleanup();
    }

    /**
     * Scan for active brokers and establish connections
     */
    private function scanAndConnectBrokers($specificBrokerId = null)
    {
        $query = MqttBroker::active()->with('devices');

        if ($specificBrokerId) {
            $query->where('id', $specificBrokerId);
        }

        $activeBrokers = $query->get();

        if ($activeBrokers->isEmpty()) {
            $this->warn("⚠️ No active MQTT brokers found");
            return;
        }

        foreach ($activeBrokers as $broker) {
            $connectionKey = "broker_{$broker->id}";

            // Skip if already connected and healthy
            if (isset($this->mqttConnections[$connectionKey]) && 
                $this->mqttConnections[$connectionKey]['client'] &&
                $this->isConnectionHealthy($connectionKey)) {
                continue;
            }

            try {
                $this->connectToBroker($broker);
            } catch (\Exception $e) {
                $this->warn("⚠️ Failed to connect to broker '{$broker->name}': " . $e->getMessage());
                Log::warning('MQTT Broker Connection Failed', [
                    'broker_id' => $broker->id,
                    'broker_name' => $broker->name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Remove connections for inactive brokers
        $this->removeInactiveBrokerConnections($activeBrokers);
    }

    /**
     * Check if connection is healthy
     */
    private function isConnectionHealthy($connectionKey): bool
    {
        if (!isset($this->mqttConnections[$connectionKey])) {
            return false;
        }

        $connection = $this->mqttConnections[$connectionKey];
        $lastActivity = $connection['last_activity'] ?? 0;
        
        // Consider connection stale if no activity for 5 minutes
        return (time() - $lastActivity) < 300;
    }

    /**
     * Connect to a specific broker
     */
    private function connectToBroker(MqttBroker $broker)
    {
        $connectionKey = "broker_{$broker->id}";
        
        $this->info("🔗 Connecting to broker: {$broker->name} ({$broker->host}:{$broker->getConnectionPort()})");

        $clientId = "laravel_multi_{$broker->id}_" . uniqid();
        $client = new MqttClient($broker->host, $broker->getConnectionPort(), $clientId);

        $connectionSettings = $this->buildConnectionSettings($broker);
        $client->connect($connectionSettings, true);

        $this->mqttConnections[$connectionKey] = [
            'broker' => $broker,
            'client' => $client,
            'connected_at' => time(),
            'last_activity' => time(),
            'message_count' => 0
        ];

        // Subscribe to device topics for this broker
        $this->subscribeToDeviceTopics($broker, $client);

        $this->info("✅ Connected to broker: {$broker->name}");
        Log::info('MQTT Broker Connected', [
            'broker_id' => $broker->id,
            'broker_name' => $broker->name,
            'connection_key' => $connectionKey
        ]);
    }

    /**
     * Build connection settings for a broker
     */
    private function buildConnectionSettings(MqttBroker $broker): ConnectionSettings
    {
        $settings = (new ConnectionSettings())
            ->setKeepAliveInterval($broker->keepalive ?? 60)
            ->setConnectTimeout($broker->timeout ?? 30)
            ->setSocketTimeout($broker->timeout ?? 30);

        if ($broker->username) {
            $settings->setUsername($broker->username);
        }

        if ($broker->password) {
            $settings->setPassword($broker->password);
        }

        if ($broker->use_ssl) {
            $settings->setUseTls(true)
                    ->setTlsVerifyPeer(true)
                    ->setTlsSelfSignedAllowed(false);
        }

        return $settings;
    }

    /**
     * Subscribe to device topics for a broker
     */
    private function subscribeToDeviceTopics(MqttBroker $broker, MqttClient $client)
    {
        $devices = $broker->devices()->where('is_active', true)->get();

        if ($devices->isEmpty()) {
            $this->warn("⚠️ No active devices found for broker: {$broker->name}");
            return;
        }

        foreach ($devices as $device) {
            try {
                $topics = $this->getDeviceTopics($broker, $device);
                
                foreach ($topics as $topic) {
                    $this->info("📋 Subscribing to topic: {$topic}");
                    
                    // Add connection test
                    if (!$client->isConnected()) {
                        $this->error("❌ Client not connected when subscribing to {$topic}");
                        continue;
                    }
                    
                    $client->subscribe($topic, function($topic, $message) use ($broker) {
                        // Add immediate debug output
                        $this->info("🔔 RAW MESSAGE RECEIVED!");
                        $this->info("Topic: {$topic}");
                        $this->info("Message: " . substr($message, 0, 500));
                        $this->handleMessage($broker, $topic, $message);
                    }, 0);
                    
                    $this->info("✅ Successfully subscribed to {$topic}");
                }

            } catch (\Exception $e) {
                $this->warn("⚠️ Failed to subscribe to device '{$device->device_id}': " . $e->getMessage());
            }
        }

        $this->info("📡 Subscribed to {$devices->count()} device(s) for broker: {$broker->name}");
    }

    /**
     * Get device topics based on broker type
     */
    private function getDeviceTopics(MqttBroker $broker, Device $device): array
    {
        $deviceId = $device->device_id;
        
        switch ($broker->type) {
            case 'lorawan':
                // The Things Stack format
                $username = $broker->username ?? 'laravel-backend@ptyxiakinetwork';
                return ["v3/{$username}/devices/{$deviceId}/up"];
                
            case 'mosquitto':
            case 'mqtt':
            default:
                // Generic MQTT topics
                return [
                    "devices/{$deviceId}/sensors/+",
                    "devices/{$deviceId}/status",
                    "{$deviceId}/+/+",
                    "{$deviceId}/data"
                ];
        }
    }

    /**
     * Process messages from all active connections
     */
    private function processAllConnections()
    {
        foreach ($this->mqttConnections as $connectionKey => $connection) {
            if (!$connection['client']) {
                continue;
            }

            try {
                $connection['client']->loop(false, true);
                $this->mqttConnections[$connectionKey]['last_activity'] = time();
            } catch (\Exception $e) {
                $this->warn("⚠️ Error processing connection {$connectionKey}: " . $e->getMessage());
                $this->markConnectionAsBroken($connectionKey);
            }
        }
    }

    /**
     * Handle incoming MQTT message
     */
    private function handleMessage(MqttBroker $broker, $topic, $message)
    {
        try {
            $connectionKey = "broker_{$broker->id}";
            
            // Update connection stats
            if (isset($this->mqttConnections[$connectionKey])) {
                $this->mqttConnections[$connectionKey]['message_count']++;
                $this->mqttConnections[$connectionKey]['last_activity'] = time();
            }

            // Store last message time for health monitoring
            cache(['mqtt_last_message_time' => now()], now()->addMinutes(10));

            $this->info("📨 Message from broker '{$broker->name}' on topic: {$topic}");

            $deviceId = $this->extractDeviceIdFromTopic($broker, $topic);
            
            if (!$deviceId) {
                $this->warn("⚠️ Could not extract device ID from topic: {$topic}");
                return;
            }

            $payload = json_decode($message, true);
            
            if (!$payload) {
                $this->warn("⚠️ Failed to parse JSON message for device '{$deviceId}'");
                $this->info("Raw message: " . substr($message, 0, 200));
                return;
            }

            Log::info('Multi-Broker MQTT Message Received', [
                'broker_id' => $broker->id,
                'broker_name' => $broker->name,
                'topic' => $topic,
                'device_id' => $deviceId,
                'payload_keys' => array_keys($payload)
            ]);

            // Find the device
            $device = Device::where('device_id', $deviceId)
                           ->where('mqtt_broker_id', $broker->id)
                           ->first();

            if (!$device) {
                $this->warn("⚠️ Device '{$deviceId}' not found for broker '{$broker->name}'");
                return;
            }

            // Process based on broker type
            $this->processMessageByBrokerType($broker, $device, $payload, $topic);

        } catch (\Exception $e) {
            $this->error("❌ Error processing message: " . $e->getMessage());
            Log::error('Multi-Broker Message Processing Error', [
                'broker_id' => $broker->id,
                'error' => $e->getMessage(),
                'topic' => $topic,
                'message' => substr($message, 0, 500)
            ]);
        }
    }

    /**
     * Process message based on broker type
     */
    private function processMessageByBrokerType(MqttBroker $broker, Device $device, array $payload, string $topic)
    {
        $receivedAt = Carbon::now();

        switch ($broker->type) {
            case 'lorawan':
                $this->processLoRaWANMessage($device, $payload, $receivedAt);
                break;
                
            case 'mosquitto':
            case 'mqtt':
            default:
                $this->processGenericMqttMessage($device, $payload, $topic, $receivedAt);
                break;
        }

        // Update device status
        $device->setOnline();
        $this->info("✅ Device '{$device->device_id}' status updated");
    }

    /**
     * Process LoRaWAN message
     */
    private function processLoRaWANMessage(Device $device, array $payload, Carbon $timestamp)
    {
        if (!isset($payload['uplink_message']['decoded_payload'])) {
            $this->warn("⚠️ No decoded payload found in LoRaWAN message");
            $this->info("Available keys: " . implode(', ', array_keys($payload)));
            return;
        }

        $decodedPayload = $payload['uplink_message']['decoded_payload'];
        $this->info("🔍 Processing decoded payload: " . json_encode($decodedPayload));
        
        $sensorsUpdated = $this->processSensorReadings($device, $decodedPayload, $timestamp);
        
        $this->info("🎯 Updated {$sensorsUpdated} sensors for LoRaWAN device '{$device->device_id}'");
    }

    /**
     * Process generic MQTT message
     */
    private function processGenericMqttMessage(Device $device, array $payload, string $topic, Carbon $timestamp)
    {
        // Extract sensor data from topic and payload
        $sensorData = $this->extractSensorDataFromGenericMqtt($topic, $payload);
        
        if (!empty($sensorData)) {
            $sensorsUpdated = $this->processSensorReadings($device, $sensorData, $timestamp);
            $this->info("🎯 Updated {$sensorsUpdated} sensors for device '{$device->device_id}'");
        }
    }

    /**
     * Extract sensor data from generic MQTT - FIXED REGEX INDICES
     */
    private function extractSensorDataFromGenericMqtt(string $topic, array $payload): array
    {
        // Handle different MQTT topic patterns
        $sensorData = [];

        // Pattern: devices/{device_id}/sensors/{sensor_type}
        if (preg_match('/devices\/[^\/]+\/sensors\/(.+)/', $topic, $matches)) {
            $sensorType = $matches[7]; // FIXED: was $matches[8]
            $sensorData[$sensorType] = $payload['value'] ?? $payload;
        }
        // Pattern: {device_id}/{sensor_type}/{value}
        elseif (preg_match('/[^\/]+\/(.+)\/(.+)/', $topic, $matches)) {
            $sensorType = $matches[7]; // FIXED: was $matches[8]
            $sensorData[$sensorType] = $matches[9]; // FIXED: was $matches[10]
        }
        // Direct payload with sensor readings
        else {
            $sensorData = $payload;
        }

        return $sensorData;
    }

    /**
     * Extract device ID from topic based on broker type - FIXED REGEX INDICES
     */
    private function extractDeviceIdFromTopic(MqttBroker $broker, string $topic): ?string
    {
        switch ($broker->type) {
            case 'lorawan':
                // v3/{username}/devices/{device_id}/up
                if (preg_match('/v3\/[^\/]+\/devices\/([^\/]+)\/up/', $topic, $matches)) {
                    return $matches[7]; // FIXED: was $matches[8]
                }
                break;
                
            case 'mosquitto':
            case 'mqtt':
            default:
                // devices/{device_id}/sensors/{sensor_type}
                if (preg_match('/devices\/([^\/]+)\//', $topic, $matches)) {
                    return $matches[7]; // FIXED: was $matches[8]
                }
                // {device_id}/{sensor_type}/{value}
                if (preg_match('/^([^\/]+)\//', $topic, $matches)) {
                    return $matches[7]; // FIXED: was $matches[8]
                }
                break;
        }

        return null;
    }

    /**
     * Process sensor readings from decoded payload
     */
    private function processSensorReadings(Device $device, array $sensorData, Carbon $timestamp): int
    {
        $sensorMappings = [
            'temperature' => ['type' => 'temperature', 'unit' => '°C'],
            'humidity' => ['type' => 'humidity', 'unit' => '%'],
            'altitude' => ['type' => 'altitude', 'unit' => 'm'],
            'battery' => ['type' => 'battery', 'unit' => '%'],
            'latitude' => ['type' => 'latitude', 'unit' => '°'],
            'longitude' => ['type' => 'longitude', 'unit' => '°'],
            'pressure' => ['type' => 'pressure', 'unit' => 'hPa'],
            'light' => ['type' => 'light', 'unit' => 'lux'],
            'motion' => ['type' => 'motion', 'unit' => ''],
            'co2' => ['type' => 'co2', 'unit' => 'ppm'],
            'gps_fix' => ['type' => 'gps_fix', 'unit' => ''],
            'gps_fix_type' => ['type' => 'gps_fix_type', 'unit' => '']
        ];

        $sensorsUpdated = 0;

        foreach ($sensorData as $sensorKey => $value) {
            if ($value === null || $value === '') {
                continue; // Skip empty values
            }

            $mapping = $sensorMappings[$sensorKey] ?? [
                'type' => $sensorKey, 
                'unit' => ''
            ];

            // Find or create sensor
            $sensor = Sensor::firstOrCreate(
                [
                    'device_id' => $device->id,
                    'sensor_type' => $mapping['type'],
                    'sensor_name' => ucfirst(str_replace('_', ' ', $sensorKey))
                ],
                [
                    'user_id' => $device->user_id,
                    'description' => ucfirst(str_replace('_', ' ', $sensorKey)) . ' sensor',
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
        }

        return $sensorsUpdated;
    }

    /**
     * Remove connections for inactive brokers
     */
    private function removeInactiveBrokerConnections($activeBrokers)
    {
        $activeBrokerIds = $activeBrokers->pluck('id')->toArray();
        
        foreach ($this->mqttConnections as $connectionKey => $connection) {
            $brokerId = $connection['broker']->id;
            
            if (!in_array($brokerId, $activeBrokerIds)) {
                $this->disconnectBroker($connectionKey);
            }
        }
    }

    /**
     * Clean up broken connections
     */
    private function cleanupBrokenConnections()
    {
        foreach ($this->mqttConnections as $connectionKey => $connection) {
            if (!$connection['client'] || !$this->isConnectionHealthy($connectionKey)) {
                $this->disconnectBroker($connectionKey);
            }
        }
    }

    /**
     * Mark connection as broken
     */
    private function markConnectionAsBroken($connectionKey)
    {
        if (isset($this->mqttConnections[$connectionKey])) {
            $this->mqttConnections[$connectionKey]['client'] = null;
        }
    }

    /**
     * Disconnect from a broker
     */
    private function disconnectBroker($connectionKey)
    {
        if (!isset($this->mqttConnections[$connectionKey])) {
            return;
        }

        $connection = $this->mqttConnections[$connectionKey];
        
        try {
            if ($connection['client']) {
                $connection['client']->disconnect();
            }
            $this->info("🔌 Disconnected from broker: {$connection['broker']->name}");
        } catch (\Exception $e) {
            $this->warn("⚠️ Error disconnecting from broker: " . $e->getMessage());
        }

        unset($this->mqttConnections[$connectionKey]);
    }

    /**
     * Cleanup all connections
     */
    private function cleanup()
    {
        foreach ($this->mqttConnections as $connectionKey => $connection) {
            $this->disconnectBroker($connectionKey);
        }
        $this->info("🧹 All connections cleaned up");
    }

    /**
     * Handle graceful shutdown
     */
    public function handleShutdown($signal = null)
    {
        $this->info("\n🛑 Received shutdown signal. Closing all connections...");
        $this->isRunning = false;

        $this->cleanup();

        $this->info("✅ All MQTT connections closed gracefully");
        exit(0);
    }
}
