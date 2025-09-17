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
    // Note: Connection parameters are now dynamically loaded from device and broker models
    // The LoRaWAN uplink listener command handles all connections automatically

    // Debug methods removed - LoRaWAN connections are now handled by the LoRaWANUplinkListener command
    // which dynamically loads connection parameters from device and broker models

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
            $sensorsUpdated = $this->processSensorReadings($device, $decodedPayload, $receivedAt);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Sensor data processed successfully',
                'device_id' => $deviceId,
                'sensors_updated' => $sensorsUpdated,
                'payload_items' => count($decodedPayload)
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
                Log::info('LoRaWAN: Unknown sensor type, creating generic sensor', [
                    'sensor' => $sensorKey, 
                    'value' => $value
                ]);
                
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

    // getTopics method removed - topic generation is now handled by the LoRaWANUplinkListener command
    // which dynamically generates topics based on device and broker data
}
