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
    protected $signature = 'lorawan:listen {--device=test-lorawan-1 : Device ID to listen for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for LoRaWAN MQTT messages and update sensor data';

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
        $deviceId = $this->option('device');
        
        $this->info("🚀 Starting LoRaWAN MQTT Listener for device: {$deviceId}");
        $this->info("📡 Connecting to The Things Stack...");

        try {
            // Setup MQTT connection
            $this->setupMqttConnection();
            
            // Subscribe to device topics
            $this->subscribeToDeviceTopics($deviceId);
            
            $this->info("✅ Connected and subscribed successfully!");
            $this->info("🔄 Listening for messages... (Press Ctrl+C to stop)");
            
            // Main listening loop
            $this->startListening();
            
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
     * Subscribe to device topics
     */
    private function subscribeToDeviceTopics($deviceId)
    {
        $baseTopic = "v3/" . self::TTN_USERNAME . "/devices/{$deviceId}";
        $uplinkTopic = "{$baseTopic}/up";
        
        $this->info("📋 Subscribing to topic: {$uplinkTopic}");
        
        $this->mqttClient->subscribe($uplinkTopic, function($topic, $message) use ($deviceId) {
            $this->handleMessage($topic, $message, $deviceId);
        }, 0);
    }

    /**
     * Handle incoming MQTT message
     */
    private function handleMessage($topic, $message, $deviceId)
    {
        try {
            $this->info("📨 Message received on topic: {$topic}");
            
            // Parse JSON message
            $payload = json_decode($message, true);
            
            if (!$payload) {
                $this->warn("⚠️ Failed to parse JSON message");
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
                $this->warn("⚠️ No decoded payload found in message");
                return;
            }
            
            $decodedPayload = $payload['uplink_message']['decoded_payload'];
            $receivedAt = isset($payload['received_at']) ? 
                Carbon::parse($payload['received_at']) : 
                Carbon::now();
            
            $this->info("🔍 Decoded payload: " . json_encode($decodedPayload));
            
            // Find the device in database
            $device = Device::where('device_id', $deviceId)->first();
            
            if (!$device) {
                $this->error("❌ Device '{$deviceId}' not found in database");
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
