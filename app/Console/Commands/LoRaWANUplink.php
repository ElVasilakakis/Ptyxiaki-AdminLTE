<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\Sensor;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class LoRaWANUplink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lorawan:uplink {--device=test-lorawan-1 : Device ID} {--payload=138819965502400240F8AFC91006400022 : Hex payload to send or "random" for random payloads} {--interval=10 : Interval in seconds} {--random : Generate random payloads}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send real uplink data to LoRaWAN device via The Things Stack and process responses';

    // TTN Connection Parameters
    private const TTN_HOST = 'eu1.cloud.thethings.industries';
    private const TTN_PORT = 8883;
    private const TTN_USERNAME = 'laravel-backend@ptyxiakinetwork';
    private const API_KEY = 'NNSXS.S44Q7UFP4YFNSADL3MINDUYCQZAO7QSW4BGWSWA.TMJ6IK457FJWIVMJY26D4ZNH5QTKZMQYJMUT4E63HJL4VHVW2WRQ';
    
    // TTN API endpoints
    private const TTN_API_BASE = 'https://eu1.cloud.thethings.industries/api/v3';

    private $mqttClient;
    private $isRunning = true;
    private $deviceId;
    private $payload;
    private $interval;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->deviceId = $this->option('device');
        $this->payload = $this->option('payload');
        $this->interval = (int) $this->option('interval');
        
        $this->info("🚀 Starting Real LoRaWAN Uplink for device: {$this->deviceId}");
        $this->info("📦 Payload: {$this->payload}");
        $this->info("⏱️ Interval: {$this->interval} seconds");
        $this->info("📡 Connecting to The Things Stack...");

        try {
            // Setup MQTT connection to listen for responses
            $this->setupMqttConnection();
            
            // Subscribe to device uplink topic to receive responses
            $this->subscribeToDeviceTopics();
            
            $this->info("✅ MQTT connected and subscribed successfully!");
            $this->info("🔄 Starting uplink transmission... (Press Ctrl+C to stop)");
            
            // Start the uplink loop
            $this->startUplinkLoop();
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to start LoRaWAN uplink: " . $e->getMessage());
            Log::error('LoRaWAN Uplink Error', [
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
        $clientId = 'laravel_uplink_' . uniqid();
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
    private function subscribeToDeviceTopics()
    {
        $baseTopic = "v3/" . self::TTN_USERNAME . "/devices/{$this->deviceId}";
        $uplinkTopic = "{$baseTopic}/up";
        
        $this->info("📋 Subscribing to topic: {$uplinkTopic}");
        
        $this->mqttClient->subscribe($uplinkTopic, function($topic, $message) {
            $this->handleUplinkResponse($topic, $message);
        }, 0);
    }

    /**
     * Start the uplink loop
     */
    private function startUplinkLoop()
    {
        $lastUplinkTime = 0;
        
        while ($this->isRunning) {
            try {
                $currentTime = time();
                
                // Send uplink every interval seconds
                if ($currentTime - $lastUplinkTime >= $this->interval) {
                    $this->sendRealUplink();
                    $lastUplinkTime = $currentTime;
                }
                
                // Process MQTT messages (responses from real device)
                $this->mqttClient->loop(true, true);
                
                // Small delay to prevent high CPU usage
                usleep(100000); // 0.1 seconds
                
            } catch (\Exception $e) {
                $this->error("❌ Error in uplink loop: " . $e->getMessage());
                Log::error('LoRaWAN Uplink Loop Error', ['error' => $e->getMessage()]);
                
                // Try to reconnect after error
                sleep(5);
                try {
                    $this->setupMqttConnection();
                    $this->subscribeToDeviceTopics();
                    $this->info("🔄 Reconnected to MQTT broker");
                } catch (\Exception $reconnectError) {
                    $this->error("❌ Failed to reconnect: " . $reconnectError->getMessage());
                }
            }
        }
    }

    /**
     * Send real uplink to The Things Stack via API
     */
    private function sendRealUplink()
    {
        try {
            // Generate random payload if not specified or if user wants random
            $currentPayload = $this->payload;
            if ($this->payload === 'random' || $this->option('random')) {
                $currentPayload = $this->generateRandomPayload();
            }
            
            $this->info("📤 Sending real uplink to device: {$this->deviceId}");
            $this->line("   📦 Payload: {$currentPayload}");
            
            // Use TTN's simulate uplink API
            $response = $this->sendToTTNAPI($currentPayload);
            
            if ($response) {
                $this->info("✅ Uplink sent to The Things Stack successfully");
            } else {
                $this->warn("⚠️ Failed to send uplink to The Things Stack");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error sending real uplink: " . $e->getMessage());
            Log::error('LoRaWAN Real Uplink Send Error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate random hex payload
     */
    private function generateRandomPayload()
    {
        $hexChars = '0123456789ABCDEF';
        $hexPayload = '';
        
        // Generate 24 character hex string (12 bytes) - same length as your example
        for ($i = 0; $i < 24; $i++) {
            $hexPayload .= $hexChars[rand(0, 15)];
        }
        
        return $hexPayload;
    }

    /**
     * Send uplink to The Things Stack API
     */
    private function sendToTTNAPI($currentPayload)
    {
        try {
            // TTN Simulate Uplink API endpoint
            $url = self::TTN_API_BASE . "/as/applications/laravel-backend/devices/{$this->deviceId}/up/simulate";
            
            $payload = [
                'uplink_message' => [
                    'f_port' => 1,
                    'frm_payload' => base64_encode(hex2bin($currentPayload)),
                    'rx_metadata' => [
                        [
                            'gateway_ids' => [
                                'gateway_id' => 'test-gateway'
                            ],
                            'rssi' => rand(-100, -30),  // Random RSSI
                            'channel_rssi' => rand(-100, -30),
                            'snr' => round(rand(-20, 20) / 2, 1),  // Random SNR
                            'uplink_token' => base64_encode(random_bytes(16))
                        ]
                    ],
                    'settings' => [
                        'data_rate' => [
                            'lora' => [
                                'bandwidth' => 125000,
                                'spreading_factor' => rand(7, 12)  // Random SF
                            ]
                        ],
                        'frequency' => '868100000'
                    ]
                ]
            ];
            
            $this->info("🌐 Calling TTN API: {$url}");
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . self::API_KEY,
                'Content-Type' => 'application/json'
            ])->post($url, $payload);
            
            if ($response->successful()) {
                $this->info("✅ TTN API call successful");
                Log::info('TTN API Success', ['response' => $response->json()]);
                return true;
            } else {
                $this->error("❌ TTN API call failed: " . $response->status());
                $this->error("Response: " . $response->body());
                Log::error('TTN API Error', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ TTN API Exception: " . $e->getMessage());
            Log::error('TTN API Exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Handle uplink response from real device via MQTT
     */
    private function handleUplinkResponse($topic, $message)
    {
        try {
            $this->info("📨 Received REAL uplink response from device!");
            
            // Parse JSON message
            $payload = json_decode($message, true);
            
            if (!$payload) {
                $this->warn("⚠️ Failed to parse JSON response");
                return;
            }
            
            // Log the received message
            Log::info('Real LoRaWAN MQTT Message Received', [
                'topic' => $topic,
                'device_id' => $this->deviceId,
                'payload' => $payload
            ]);
            
            // Extract decoded payload from real device response
            $decodedPayload = null;
            
            if (isset($payload['data']['uplink_message']['decoded_payload'])) {
                $decodedPayload = $payload['data']['uplink_message']['decoded_payload'];
            } elseif (isset($payload['uplink_message']['decoded_payload'])) {
                $decodedPayload = $payload['uplink_message']['decoded_payload'];
            }
            
            if (!$decodedPayload) {
                $this->warn("⚠️ No decoded payload found in real device response");
                $this->line("Raw message: " . substr($message, 0, 200) . "...");
                return;
            }
            
            $receivedAt = isset($payload['data']['received_at']) ? 
                Carbon::parse($payload['data']['received_at']) : 
                Carbon::now();
            
            $this->info("🔍 REAL Decoded payload: " . json_encode($decodedPayload));
            
            // Find the device in database
            $device = Device::where('device_id', $this->deviceId)->first();
            
            if (!$device) {
                $this->error("❌ Device '{$this->deviceId}' not found in database");
                return;
            }
            
            // Update device status
            $device->setOnline();
            $this->info("✅ Device '{$this->deviceId}' status updated to online");
            
            // Process REAL sensor readings
            $sensorsUpdated = $this->processSensorReadings($device, $decodedPayload, $receivedAt);
            
            $this->info("🎯 Updated {$sensorsUpdated} sensors from REAL device data");
            $this->line(""); // Empty line for readability
            
        } catch (\Exception $e) {
            $this->error("❌ Error processing real uplink response: " . $e->getMessage());
            Log::error('Real LoRaWAN Response Processing Error', [
                'error' => $e->getMessage(),
                'topic' => $topic,
                'message' => $message
            ]);
        }
    }

    /**
     * Process sensor readings from REAL decoded payload
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
                $this->warn("⚠️ Unknown sensor type from real device: {$sensorKey}");
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
            
            // Update sensor reading with REAL data
            $sensor->updateReading($value, $timestamp);
            $sensorsUpdated++;
            
            $this->line("  📊 REAL {$sensor->sensor_name}: {$value} {$mapping['unit']}");
            
            Log::info('LoRaWAN Sensor Updated from REAL Device', [
                'device_id' => $device->device_id,
                'sensor_type' => $mapping['type'],
                'sensor_name' => $sensor->sensor_name,
                'value' => $value,
                'timestamp' => $timestamp->toDateTimeString()
            ]);
        }
        
        return $sensorsUpdated;
    }
}
