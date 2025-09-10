<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\Sensor;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LoRaWANListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lorawan:listen {--device= : Optional specific device ID to listen for (if not provided, listens to all LoRaWAN devices)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Passively listen for real LoRaWAN MQTT messages from all registered devices and update sensor data';

    // TTN Connection Parameters
    private const TTN_HOST = 'eu1.cloud.thethings.industries';
    private const TTN_PORT = 8883;
    private const TTN_USERNAME = 'laravel-backend@ptyxiakinetwork';
    private const API_KEY = 'NNSXS.S44Q7UFP4YFNSADL3MINDUYCQZAO7QSW4BGWSWA.TMJ6IK457FJWIVMJY26D4ZNH5QTKZMQYJMUT4E63HJL4VHVW2WRQ';

    private $mqttClient;
    private $isRunning = true;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specificDeviceId = $this->option('device');
        
        if ($specificDeviceId) {
            $this->info("🚀 Starting LoRaWAN MQTT Listener for specific device: {$specificDeviceId}");
        } else {
            $this->info("🚀 Starting LoRaWAN MQTT Listener for ALL registered LoRaWAN devices");
        }
        
        $this->info("📡 Connecting to The Things Stack...");

        try {
            // Setup MQTT connection
            $this->setupMqttConnection();
            
            // Main listening loop with device discovery
            $this->startListeningWithDiscovery($specificDeviceId);
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to start MQTT listener: " . $e->getMessage());
            Log::error('LoRaWAN MQTT Listener Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 1;
        }
        
        return 0;
    }

    /**
     * Setup MQTT connection
     */
    private function setupMqttConnection()
    {
        $clientId = 'laravel_listener_' . uniqid();
        $this->mqttClient = new MqttClient(self::TTN_HOST, self::TTN_PORT, $clientId);
        
        $connectionSettings = (new ConnectionSettings())
            ->setUseTls(true)
            ->setTlsVerifyPeer(true)
            ->setTlsSelfSignedAllowed(false)
            ->setUsername(self::TTN_USERNAME)
            ->setPassword(self::API_KEY)
            ->setKeepAliveInterval(60)
            ->setConnectTimeout(30)
            ->setSocketTimeout(30);

        $this->mqttClient->connect($connectionSettings, true);
    }

    /**
     * Discover LoRaWAN devices and subscribe to their topics
     */
    private function discoverAndSubscribeToDevices($specificDeviceId = null)
    {
        // Query for LoRaWAN devices
        $query = Device::with('mqttBroker')
            ->whereHas('mqttBroker', function($q) {
                $q->where('type', 'lorawan')
                  ->orWhere('host', 'like', '%thethings.industries%');
            })
            ->where('is_active', true);
        
        // If specific device requested, filter by it
        if ($specificDeviceId) {
            $query->where('device_id', $specificDeviceId);
        }
        
        $devices = $query->get();
        
        if ($devices->isEmpty()) {
            if ($specificDeviceId) {
                $this->warn("⚠️ Specific device '{$specificDeviceId}' not found or not a LoRaWAN device");
            } else {
                $this->warn("⚠️ No active LoRaWAN devices found in database");
            }
            return 0;
        }
        
        $subscribedCount = 0;
        
        foreach ($devices as $device) {
            try {
                $this->subscribeToDeviceTopics($device);
                $subscribedCount++;
            } catch (\Exception $e) {
                $this->warn("⚠️ Failed to subscribe to device '{$device->device_id}': " . $e->getMessage());
                Log::warning('LoRaWAN Device Subscription Failed', [
                    'device_id' => $device->device_id,
                    'error' => $e->getMessage()
                ]);
                // Continue with other devices
                continue;
            }
        }
        
        return $subscribedCount;
    }

    /**
     * Subscribe to device topics
     */
    private function subscribeToDeviceTopics(Device $device)
    {
        $deviceId = $device->device_id;
        $baseTopic = "v3/" . self::TTN_USERNAME . "/devices/{$deviceId}";
        $uplinkTopic = "{$baseTopic}/up";
        
        $this->info("📋 Subscribing to device '{$deviceId}' topic: {$uplinkTopic}");
        
        $this->mqttClient->subscribe($uplinkTopic, function($topic, $message) {
            $this->handleMessage($topic, $message);
        }, 0);
        
        Log::info('LoRaWAN Device Subscribed', [
            'device_id' => $deviceId,
            'topic' => $uplinkTopic
        ]);
    }

    /**
     * Handle incoming MQTT message
     */
    private function handleMessage($topic, $message)
    {
        try {
            $this->info("📨 Message received on topic: {$topic}");
            
            // Extract device ID from topic
            // Topic format: v3/laravel-backend@ptyxiakinetwork/devices/{device_id}/up
            $deviceId = $this->extractDeviceIdFromTopic($topic);
            
            if (!$deviceId) {
                $this->warn("⚠️ Could not extract device ID from topic: {$topic}");
                return;
            }
            
            // Parse JSON message
            $payload = json_decode($message, true);
            
            if (!$payload) {
                $this->warn("⚠️ Failed to parse JSON message for device '{$deviceId}'");
                return;
            }
            
            // Log the received message
            Log::info('LoRaWAN MQTT Message Received', [
                'topic' => $topic,
                'device_id' => $deviceId,
                'payload' => $payload
            ]);
            
            // Check if this is an uplink message with decoded payload
            if (!isset($payload['uplink_message']['decoded_payload'])) {
                $this->warn("⚠️ No decoded payload found in message for device '{$deviceId}'");
                return;
            }
            
            $decodedPayload = $payload['uplink_message']['decoded_payload'];
            $receivedAt = isset($payload['received_at']) ? 
                Carbon::parse($payload['received_at']) : 
                Carbon::now();
            
            $this->info("🔍 Decoded payload for '{$deviceId}': " . json_encode($decodedPayload));
            
            // Find the device in database
            $device = Device::where('device_id', $deviceId)->first();
            
            if (!$device) {
                $this->warn("⚠️ Device '{$deviceId}' not found in database - skipping");
                return;
            }
            
            // Update device status
            $device->setOnline();
            $this->info("✅ Device '{$deviceId}' status updated to online");
            
            // Process sensor readings
            $sensorsUpdated = $this->processSensorReadings($device, $decodedPayload, $receivedAt);
            
            $this->info("🎯 Updated {$sensorsUpdated} sensors for device '{$deviceId}'");
            
        } catch (\Exception $e) {
            $this->error("❌ Error processing message: " . $e->getMessage());
            Log::error('LoRaWAN Message Processing Error', [
                'error' => $e->getMessage(),
                'topic' => $topic,
                'message' => $message
            ]);
        }
    }

    /**
     * Extract device ID from MQTT topic
     */
    private function extractDeviceIdFromTopic($topic)
    {
        // Topic format: v3/laravel-backend@ptyxiakinetwork/devices/{device_id}/up
        $pattern = '/v3\/[^\/]+\/devices\/([^\/]+)\/up/';
        
        if (preg_match($pattern, $topic, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Process sensor readings from decoded payload
     */
    private function processSensorReadings(Device $device, array $decodedPayload, Carbon $timestamp)
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
                $this->warn("⚠️ Unknown sensor type: {$sensorKey}");
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
            
            Log::info('LoRaWAN Sensor Updated via MQTT', [
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
     * Start listening with continuous device discovery
     */
    private function startListeningWithDiscovery($specificDeviceId = null)
    {
        $lastDiscoveryTime = 0;
        $discoveryInterval = 30; // Check for new devices every 30 seconds
        $hasConnectedDevices = false;
        
        $this->info("🔄 Starting continuous listening mode... (Press Ctrl+C to stop)");
        
        while ($this->isRunning) {
            try {
                $currentTime = time();
                
                // Periodically discover and subscribe to new devices
                if ($currentTime - $lastDiscoveryTime >= $discoveryInterval) {
                    $devicesCount = $this->discoverAndSubscribeToDevices($specificDeviceId);
                    
                    if ($devicesCount > 0) {
                        if (!$hasConnectedDevices) {
                            $this->info("✅ Connected and subscribed to {$devicesCount} device(s) successfully!");
                            $hasConnectedDevices = true;
                        }
                    } else {
                        if (!$hasConnectedDevices) {
                            $this->warn("⚠️ No LoRaWAN devices found - waiting for devices to be registered...");
                        }
                    }
                    
                    $lastDiscoveryTime = $currentTime;
                }
                
                // Process MQTT messages if we have an active connection
                if ($this->mqttClient) {
                    $this->mqttClient->loop(true, true);
                    // Small delay to prevent high CPU usage when processing messages
                    usleep(100000); // 0.1 seconds
                } else {
                    // Longer delay when no connection is available to reduce CPU usage
                    sleep(5);
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Error in listening loop: " . $e->getMessage());
                Log::error('LoRaWAN MQTT Loop Error', ['error' => $e->getMessage()]);
                
                // Try to reconnect after error
                sleep(5);
                try {
                    $this->setupMqttConnection();
                    $this->info("🔄 Reconnected to MQTT broker");
                } catch (\Exception $reconnectError) {
                    $this->error("❌ Failed to reconnect: " . $reconnectError->getMessage());
                }
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
                // Process MQTT messages
                $this->mqttClient->loop(true, true);
                
                // Small delay to prevent high CPU usage
                usleep(100000); // 0.1 seconds
                
            } catch (\Exception $e) {
                $this->error("❌ Error in listening loop: " . $e->getMessage());
                Log::error('LoRaWAN MQTT Loop Error', ['error' => $e->getMessage()]);
                
                // Try to reconnect after error
                sleep(5);
                try {
                    $this->setupMqttConnection();
                    $this->info("🔄 Reconnected to MQTT broker");
                } catch (\Exception $reconnectError) {
                    $this->error("❌ Failed to reconnect: " . $reconnectError->getMessage());
                }
            }
        }
    }

    /**
     * Handle graceful shutdown
     */
    public function handleShutdown($signal)
    {
        $this->info("\n🛑 Received shutdown signal. Closing connection...");
        $this->isRunning = false;
        
        try {
            if ($this->mqttClient) {
                $this->mqttClient->disconnect();
                $this->info("✅ MQTT connection closed gracefully");
            }
        } catch (\Exception $e) {
            $this->warn("⚠️ Error during shutdown: " . $e->getMessage());
        }
        
        exit(0);
    }
}
