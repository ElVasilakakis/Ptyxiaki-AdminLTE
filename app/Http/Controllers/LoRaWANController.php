<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Http\Request;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LoRaWANController extends Controller
{
    // TTN Connection Parameters - Updated with your working credentials
    private const TTN_HOST = 'eu1.cloud.thethings.industries';
    private const TTN_PORT = 8883;
    private const TTN_USERNAME = 'laravel-backend@ptyxiakinetwork';
    private const DEVICE_ID = 'test-lorawan-1';
    private const API_KEY = 'NNSXS.S44Q7UFP4YFNSADL3MINDUYCQZAO7QSW4BGWSWA.TMJ6IK457FJWIVMJY26D4ZNH5QTKZMQYJMUT4E63HJL4VHVW2WRQ';

    public function debugConnection(Request $request)
    {
        $html = '<html><head><title>LoRaWAN Connection Test</title></head><body>';
        $html .= '<h1>LoRaWAN Connection Debug</h1>';
        $html .= '<div style="font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;">';
        
        try {
            $startTime = microtime(true);
            $html .= '<p><strong>🔧 Starting Connection Test...</strong></p>';
            
            // Use your working API key from Postman
            $apiKey = $request->input('api_key') ?? self::API_KEY;
            
            if (!$apiKey) {
                $html .= '<p style="color: red;">❌ No API key provided</p>';
                return $html . '</div></body></html>';
            }
            
            $html .= '<p>✅ API Key: ' . substr($apiKey, 0, 20) . '...</p>';
            
            // Connection parameters matching Postman
            $html .= '<p><strong>Connection Parameters (Matching Postman):</strong></p>';
            $html .= '<ul>';
            $html .= '<li>Host: ' . self::TTN_HOST . '</li>';
            $html .= '<li>Port: ' . self::TTN_PORT . '</li>';
            $html .= '<li>Protocol: MQTTS (TLS)</li>';
            $html .= '<li>Username: ' . self::TTN_USERNAME . '</li>';
            $html .= '<li>Device ID: ' . self::DEVICE_ID . '</li>';
            $html .= '<li>Client ID: laravel_debug_' . uniqid() . '</li>';
            $html .= '<li>MQTT Version: Default (v3.1.1)</li>';
            $html .= '<li>Clean Session: true</li>';
            $html .= '</ul>';
            
            // Create connection matching Postman settings
            $clientId = 'laravel_debug_' . uniqid();
            $mqttClient = new MqttClient(self::TTN_HOST, self::TTN_PORT, $clientId);
            
            // Configure exactly like Postman - REMOVED setMqttVersion()
            $connectionSettings = (new ConnectionSettings())
                ->setUseTls(true)
                ->setTlsVerifyPeer(true)
                ->setTlsSelfSignedAllowed(false)
                ->setUsername(self::TTN_USERNAME)
                ->setPassword($apiKey)
                ->setKeepAliveInterval(60)
                ->setConnectTimeout(30)
                ->setSocketTimeout(30);

            $html .= '<p><strong>🔗 Attempting Connection (Using Postman Settings)...</strong></p>';
            
            // Connect with clean session
            $cleanSession = true;
            $mqttClient->connect($connectionSettings, $cleanSession);
            
            $connectionTime = microtime(true) - $startTime;
            $html .= '<p style="color: green;">✅ Connection Successful! (' . round($connectionTime, 2) . 's)</p>';
            $html .= '<p style="color: green;">🎉 <strong>BREAKTHROUGH!</strong> Authentication working with new API key!</p>';
            
            // Test the exact same topic as Postman
            $topics = $this->getTopics();
            $html .= '<p><strong>📡 Testing Same Topic as Postman:</strong></p>';
            $html .= '<ul>';
            foreach ($topics as $topic) {
                $html .= '<li>' . $topic . '</li>';
            }
            $html .= '</ul>';
            
            // Subscribe to the working topic
            $html .= '<p><strong>🎯 Testing Subscription (Same as Postman)...</strong></p>';
            try {
                $testTopic = $topics[0]; // Up messages - same as Postman
                $receivedMessages = [];
                
                $mqttClient->subscribe($testTopic, function($topic, $message) use (&$receivedMessages) {
                    $receivedMessages[] = [
                        'topic' => $topic,
                        'message' => $message,
                        'time' => date('H:i:s')
                    ];
                    Log::info('LoRaWAN Message Received', ['topic' => $topic, 'message' => $message]);
                }, 0);
                
                $html .= '<p style="color: green;">✅ Successfully subscribed to: ' . $testTopic . '</p>';
            } catch (\Exception $e) {
                $html .= '<p style="color: orange;">⚠️ Subscription test failed: ' . $e->getMessage() . '</p>';
            }
            
            // Listen for messages like Postman
            $html .= '<p><strong>🔄 Listening for Messages (10 seconds)...</strong></p>';
            $loopStart = time();
            $messageCount = 0;
            
            while ((time() - $loopStart) < 10) {
                try {
                    $mqttClient->loop(true, true);
                    $messageCount++;
                    
                    usleep(250000); // 0.25 seconds
                } catch (\Exception $loopException) {
                    $html .= '<p style="color: orange;">⚠️ Loop interrupted: ' . $loopException->getMessage() . '</p>';
                    break;
                }
            }
            
            $html .= '<p>✅ Listening completed - ' . $messageCount . ' iterations</p>';
            
            if (!empty($receivedMessages)) {
                $html .= '<p style="color: green;"><strong>📨 Messages Received:</strong></p>';
                foreach ($receivedMessages as $msg) {
                    $html .= '<div style="background: #d4edda; padding: 10px; margin: 5px; border-radius: 5px;">';
                    $html .= '<p><strong>Time:</strong> ' . $msg['time'] . '</p>';
                    $html .= '<p><strong>Topic:</strong> ' . $msg['topic'] . '</p>';
                    $html .= '<p><strong>Message:</strong> ' . htmlspecialchars(substr($msg['message'], 0, 200)) . '...</p>';
                    $html .= '</div>';
                }
            } else {
                $html .= '<p style="color: orange;">📭 No messages received (device may not be sending data)</p>';
            }
            
            // Connection status check
            try {
                $isConnected = $mqttClient->isConnected();
                $html .= '<p>📊 Connection Status: ' . ($isConnected ? '🟢 Connected' : '🔴 Disconnected') . '</p>';
            } catch (\Exception $statusException) {
                $html .= '<p style="color: orange;">⚠️ Could not check connection status: ' . $statusException->getMessage() . '</p>';
            }
            
            // Clean disconnect
            try {
                $mqttClient->disconnect();
                $html .= '<p style="color: green;">✅ Disconnected cleanly</p>';
            } catch (\Exception $disconnectException) {
                $html .= '<p style="color: orange;">⚠️ Disconnect warning: ' . $disconnectException->getMessage() . '</p>';
            }
            
            $totalTime = microtime(true) - $startTime;
            $html .= '<p><strong>⏱️ Total test time: ' . round($totalTime, 2) . ' seconds</strong></p>';
            
        } catch (\Exception $e) {
            $html .= '<p style="color: red;">❌ Connection Failed!</p>';
            $html .= '<p style="color: red;"><strong>Error:</strong> ' . $e->getMessage() . '</p>';
            $html .= '<p style="color: red;"><strong>Error Code:</strong> ' . $e->getCode() . '</p>';
            
            // Show stack trace for debugging
            $html .= '<p><strong>🔍 Debug Information:</strong></p>';
            $html .= '<pre style="background: #ffe6e6; padding: 10px; border-radius: 5px; font-size: 12px;">';
            $html .= 'File: ' . $e->getFile() . "\n";
            $html .= 'Line: ' . $e->getLine() . "\n";
            $html .= 'Trace: ' . substr($e->getTraceAsString(), 0, 1000) . '...';
            $html .= '</pre>';
            
            $html .= '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;">';
            $html .= '<h4>🚨 PHP vs Postman Analysis</h4>';
            $html .= '<p><strong>Postman Status:</strong> ✅ Connected & Working</p>';
            $html .= '<p><strong>PHP Status:</strong> ❌ ' . $e->getMessage() . '</p>';
            $html .= '<p><strong>Progress Made:</strong></p>';
            $html .= '<ul>';
            $html .= '<li>✅ Fixed hostname parsing (removed mqtts://)</li>';
            $html .= '<li>✅ Fixed missing methods (removed setMqttVersion)</li>';
            $html .= '<li>✅ Using correct API key from Postman</li>';
            $html .= '<li>✅ TLS configuration matches Postman</li>';
            $html .= '<li>⚠️ Current issue: ' . $e->getMessage() . '</li>';
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '<hr>';
        
        // Show library information
        $html .= '<h3>📚 Library Information:</h3>';
        $html .= '<div style="background: #e2e3e5; padding: 10px; border-radius: 5px;">';
        
        try {
            $reflection = new \ReflectionClass('PhpMqtt\Client\ConnectionSettings');
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            $html .= '<p><strong>Available ConnectionSettings Methods:</strong></p>';
            $html .= '<ul>';
            foreach ($methods as $method) {
                if (strpos($method->getName(), 'set') === 0) {
                    $html .= '<li>' . $method->getName() . '</li>';
                }
            }
            $html .= '</ul>';
        } catch (\Exception $reflectionError) {
            $html .= '<p>Could not inspect ConnectionSettings class</p>';
        }
        
        $html .= '</div>';
        
        $html .= '<h3>✅ Postman Comparison:</h3>';
        $html .= '<div style="background: #d4edda; padding: 15px; border-radius: 5px;">';
        $html .= '<p><strong>Postman Results:</strong></p>';
        $html .= '<ul>';
        $html .= '<li>✅ Connected to broker successfully</li>';
        $html .= '<li>✅ Subscribed to topic: .../test-lorawan-1/up</li>';
        $html .= '<li>✅ Using API key: NNSXS.S44Q7UFP4YFNSADL3MINDUYCQZAO7QSW4BGWSWA...</li>';
        $html .= '<li>✅ TLS connection established</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        $html .= '<h3>🧪 Test Different API Keys:</h3>';
        $html .= '<form method="GET" action="/debug/lorawan-check">';
        $html .= '<input type="text" name="api_key" placeholder="Enter TTN API Key" style="width: 500px; padding: 5px;" value="' . htmlspecialchars($request->input('api_key') ?? self::API_KEY) . '">';
        $html .= '<button type="submit" style="padding: 5px 15px; margin-left: 10px;">Test Connection</button>';
        $html .= '</form>';
        
        $html .= '<hr>';
        $html .= '<p><small>Generated at: ' . date('Y-m-d H:i:s') . ' EEST</small></p>';
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Alternative test with minimal settings
     */
    public function simpleTest(Request $request)
    {
        try {
            $apiKey = self::API_KEY;
            $clientId = 'simple_test_' . uniqid();
            
            // Create the most basic connection possible
            $mqttClient = new MqttClient(self::TTN_HOST, self::TTN_PORT, $clientId);
            
            // Minimal settings
            $connectionSettings = (new ConnectionSettings())
                ->setUseTls(true)
                ->setUsername(self::TTN_USERNAME)
                ->setPassword($apiKey);
            
            $mqttClient->connect($connectionSettings, true);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Simple connection successful!',
                'client_id' => $clientId,
                'host' => self::TTN_HOST,
                'port' => self::TTN_PORT
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ]);
        }
    }

    /**
     * Webhook endpoint to receive LoRaWAN uplink data from The Things Stack
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('LoRaWAN Webhook Received', ['payload' => $request->all()]);
            
            // Get the payload data
            $payload = $request->all();
            
            // Validate that this is an uplink message
            if (!isset($payload['data']['@type']) || 
                $payload['data']['@type'] !== 'type.googleapis.com/ttn.lorawan.v3.ApplicationUp') {
                Log::warning('LoRaWAN Webhook: Not an uplink message', ['type' => $payload['data']['@type'] ?? 'unknown']);
                return response()->json(['status' => 'ignored', 'reason' => 'not_uplink'], 200);
            }
            
            // Check if uplink_message exists
            if (!isset($payload['data']['uplink_message'])) {
                Log::warning('LoRaWAN Webhook: No uplink_message found');
                return response()->json(['status' => 'ignored', 'reason' => 'no_uplink_message'], 200);
            }
            
            $uplinkMessage = $payload['data']['uplink_message'];
            $endDeviceIds = $payload['data']['end_device_ids'];
            
            // Extract device information
            $deviceId = $endDeviceIds['device_id'];
            $applicationId = $endDeviceIds['application_ids']['application_id'];
            $devEui = $endDeviceIds['dev_eui'];
            
            Log::info('Processing LoRaWAN uplink', [
                'device_id' => $deviceId,
                'application_id' => $applicationId,
                'dev_eui' => $devEui
            ]);
            
            // Find the device in our database
            $device = Device::where('device_id', $deviceId)->first();
            
            if (!$device) {
                Log::warning('LoRaWAN Webhook: Device not found in database', ['device_id' => $deviceId]);
                return response()->json(['status' => 'error', 'reason' => 'device_not_found'], 404);
            }
            
            // Update device status and last seen
            $device->setOnline();
            
            // Check if decoded payload exists
            if (!isset($uplinkMessage['decoded_payload'])) {
                Log::warning('LoRaWAN Webhook: No decoded payload found');
                return response()->json(['status' => 'ignored', 'reason' => 'no_decoded_payload'], 200);
            }
            
            $decodedPayload = $uplinkMessage['decoded_payload'];
            $receivedAt = Carbon::parse($payload['data']['received_at']);
            
            Log::info('LoRaWAN decoded payload', ['payload' => $decodedPayload]);
            
            // Process each sensor reading from the decoded payload
            $this->processSensorReadings($device, $decodedPayload, $receivedAt);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Sensor data processed successfully',
                'device_id' => $deviceId,
                'sensors_updated' => count($decodedPayload)
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('LoRaWAN Webhook Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payload' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Process sensor readings from LoRaWAN decoded payload
     */
    private function processSensorReadings(Device $device, array $decodedPayload, Carbon $timestamp)
    {
        // Define sensor type mappings based on your payload structure
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
        
        foreach ($decodedPayload as $sensorKey => $value) {
            if (!isset($sensorMappings[$sensorKey])) {
                Log::info('LoRaWAN: Unknown sensor type', ['sensor' => $sensorKey, 'value' => $value]);
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
            
            Log::info('LoRaWAN sensor updated', [
                'device_id' => $device->device_id,
                'sensor_type' => $mapping['type'],
                'sensor_name' => $sensor->sensor_name,
                'value' => $value,
                'timestamp' => $timestamp->toDateTimeString()
            ]);
        }
    }

    /**
     * Test webhook endpoint with sample data
     */
    public function testWebhook(Request $request)
    {
        // Use the exact JSON payload you provided
        $samplePayload = [
            "name" => "as.up.data.forward",
            "time" => "2025-09-10T11:37:28.540084910Z",
            "identifiers" => [
                [
                    "device_ids" => [
                        "device_id" => "test-lorawan-1",
                        "application_ids" => [
                            "application_id" => "laravel-backend"
                        ],
                        "dev_eui" => "70B3D57ED80048A2",
                        "dev_addr" => "27FCC0D0"
                    ]
                ]
            ],
            "data" => [
                "@type" => "type.googleapis.com/ttn.lorawan.v3.ApplicationUp",
                "end_device_ids" => [
                    "device_id" => "test-lorawan-1",
                    "application_ids" => [
                        "application_id" => "laravel-backend"
                    ],
                    "dev_eui" => "70B3D57ED80048A2",
                    "dev_addr" => "27FCC0D0"
                ],
                "correlation_ids" => [
                    "as:up:01K4SPN6PTJT68X2T4AAPF653C",
                    "rpc:/ttn.lorawan.v3.AppAs/SimulateUplink:3b058207-8d35-41c7-a3dc-87d26347cfbe"
                ],
                "received_at" => "2025-09-10T11:37:28.537951549Z",
                "uplink_message" => [
                    "f_port" => 1,
                    "frm_payload" => "E4gZllUCQAJA+K/JEAZAAA==",
                    "decoded_payload" => [
                        "altitude" => 1600,
                        "battery" => 85,
                        "gps_fix" => 0,
                        "gps_fix_type" => "No Fix",
                        "humidity" => 65.5,
                        "latitude" => 37.749312,
                        "longitude" => -122.697456,
                        "temperature" => 50
                    ],
                    "rx_metadata" => [
                        [
                            "gateway_ids" => [
                                "gateway_id" => "test"
                            ],
                            "rssi" => 42,
                            "channel_rssi" => 42,
                            "snr" => 4.2
                        ]
                    ],
                    "settings" => [
                        "data_rate" => [
                            "lora" => [
                                "bandwidth" => 125000,
                                "spreading_factor" => 7
                            ]
                        ],
                        "frequency" => "868000000"
                    ],
                    "locations" => [
                        "user" => [
                            "latitude" => 45.227372291465,
                            "longitude" => -110.836232887651,
                            "source" => "SOURCE_REGISTRY"
                        ]
                    ]
                ],
                "simulated" => true
            ],
            "correlation_ids" => [
                "as:up:01K4SPN6PTJT68X2T4AAPF653C",
                "rpc:/ttn.lorawan.v3.AppAs/SimulateUplink:3b058207-8d35-41c7-a3dc-87d26347cfbe"
            ],
            "origin" => "ip-10-23-15-240.eu-west-1.compute.internal",
            "context" => [
                "tenant-id" => "Cg9wdHl4aWFraW5ldHdvcms="
            ],
            "visibility" => [
                "rights" => [
                    "RIGHT_APPLICATION_TRAFFIC_READ"
                ]
            ],
            "unique_id" => "01K4SPN6PWVCQHH836EYKPE4TR"
        ];
        
        // Create a new request with the sample payload
        $testRequest = new Request($samplePayload);
        
        // Call the actual webhook method
        $response = $this->webhook($testRequest);
        
        return response()->json([
            'test_status' => 'completed',
            'webhook_response' => $response->getData(),
            'sample_payload_used' => true,
            'message' => 'Test webhook executed with your provided JSON data'
        ]);
    }

    /**
     * Get all topics for a device
     */
    private function getTopics($deviceId = self::DEVICE_ID)
    {
        $baseTopic = "v3/" . self::TTN_USERNAME . "/devices/{$deviceId}";
        
        return [
            "{$baseTopic}/up",           // Uplink messages - same as Postman
            "{$baseTopic}/join",         // Join messages
            "{$baseTopic}/down/push"     // Downlink confirmations
        ];
    }
}
