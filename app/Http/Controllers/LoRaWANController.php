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
    /**
     * Unified webhook endpoint to receive both MQTT and LoRaWAN data
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('Webhook Received', ['payload' => $request->all()]);
            
            $payload = $request->all();
            
            // Detect payload format and process accordingly
            if ($this->isMqttPayload($payload)) {
                return $this->processMqttPayload($payload);
            } elseif ($this->isLoRaWANPayload($payload)) {
                return $this->processLoRaWANPayload($payload);
            } else {
                Log::warning('Unknown payload format', ['payload_keys' => array_keys($payload)]);
                return response()->json(['status' => 'ignored', 'reason' => 'unknown_format'], 200);
            }
            
        } catch (\Exception $e) {
            Log::error('Webhook Error', [
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
     * Check if payload is MQTT format
     */
    private function isMqttPayload($payload)
    {
        return isset($payload['sensors']) && is_array($payload['sensors']) && isset($payload['timestamp']);
    }
    
    /**
     * Check if payload is LoRaWAN format
     */
    private function isLoRaWANPayload($payload)
    {
        return isset($payload['uplink_message']) || 
               (isset($payload['data']) && isset($payload['data']['uplink_message']));
    }
    
    /**
     * Process MQTT payload format
     */
    private function processMqttPayload($payload)
    {
        Log::info('Processing MQTT payload', ['sensor_count' => count($payload['sensors'])]);
        
        // For MQTT, we need to determine the device somehow
        $deviceId = $payload['device_id'] ?? 'mqtt-device-1';
        
        // Find or create device
        $device = Device::where('device_id', $deviceId)->first();
        if (!$device) {
            // Create a default MQTT device if it doesn't exist
            $device = Device::create([
                'device_id' => $deviceId,
                'device_name' => 'MQTT Device',
                'device_type' => 'mqtt',
                'user_id' => 1, // Adjust this to your user ID
                'location' => 'Unknown',
                'enabled' => true
            ]);
            Log::info('Created new MQTT device', ['device_id' => $deviceId]);
        }
        
        // Process timestamp
        $timestamp = isset($payload['timestamp']) 
            ? Carbon::createFromTimestamp($payload['timestamp'])
            : Carbon::now();
        
        // Process sensors array
        $sensorsUpdated = 0;
        foreach ($payload['sensors'] as $sensorData) {
            $sensorType = $sensorData['type'];
            $sensorValue = $this->extractValueFromString($sensorData['value']);
            $unit = $this->extractUnitFromString($sensorData['value']);
            
            // Handle geolocation separately
            if ($sensorType === 'geolocation') {
                $sensorType = $sensorData['subtype']; // latitude or longitude
            }
            
            // Map thermal to temperature
            if ($sensorType === 'thermal') {
                $sensorType = 'temperature';
            }
            
            // Find or create individual sensor for each type
            $sensor = Sensor::firstOrCreate(
                [
                    'device_id' => $device->id,
                    'sensor_type' => $sensorType,
                    'sensor_name' => ucfirst($sensorType)
                ],
                [
                    'user_id' => $device->user_id,
                    'description' => 'MQTT ' . ucfirst($sensorType) . ' sensor',
                    'location' => $device->location,
                    'unit' => $unit,
                    'enabled' => true,
                    'alert_enabled' => false
                ]
            );
            
            // Update sensor reading
            $sensor->updateReading($sensorValue, $timestamp);
            $sensorsUpdated++;
            
            Log::info('MQTT sensor updated', [
                'device_id' => $deviceId,
                'sensor_type' => $sensorType,
                'value' => $sensorValue,
                'unit' => $unit
            ]);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'MQTT sensor data processed successfully',
            'device_id' => $deviceId,
            'sensors_updated' => $sensorsUpdated,
            'payload_items' => count($payload['sensors'])
        ], 200);
    }
    
    /**
     * Process LoRaWAN payload format
     */
    private function processLoRaWANPayload($payload)
    {
        Log::info('Processing LoRaWAN payload');
        
        // Handle different LoRaWAN payload formats
        $data = null;
        if (isset($payload['data'])) {
            $data = $payload['data'];
        } elseif (isset($payload['uplink_message'])) {
            $data = $payload;
        } else {
            return response()->json(['status' => 'ignored', 'reason' => 'no_uplink_data'], 200);
        }
        
        // Check if uplink_message exists
        if (!isset($data['uplink_message'])) {
            Log::warning('LoRaWAN Webhook: No uplink_message found');
            return response()->json(['status' => 'ignored', 'reason' => 'no_uplink_message'], 200);
        }
        
        $uplinkMessage = $data['uplink_message'];
        $endDeviceIds = $data['end_device_ids'];
        
        // Extract device information
        $deviceId = $endDeviceIds['device_id'];
        $applicationId = $endDeviceIds['application_ids']['application_id'];
        
        // Find device
        $device = Device::where('device_id', $deviceId)->first();
        if (!$device) {
            Log::warning('LoRaWAN device not found', ['device_id' => $deviceId]);
            return response()->json(['status' => 'ignored', 'reason' => 'device_not_found'], 404);
        }
        
        // Process decoded payload
        if (!isset($uplinkMessage['decoded_payload'])) {
            Log::warning('LoRaWAN: No decoded payload found');
            return response()->json(['status' => 'ignored', 'reason' => 'no_decoded_payload'], 200);
        }
        
        $decodedPayload = $uplinkMessage['decoded_payload'];
        $timestamp = Carbon::parse($data['received_at']);
        
        $sensorsUpdated = $this->processLoRaWANSensorReadings($device, $decodedPayload, $timestamp);
        
        return response()->json([
            'status' => 'success',
            'message' => 'LoRaWAN sensor data processed successfully',
            'device_id' => $deviceId,
            'sensors_updated' => $sensorsUpdated,
            'payload_items' => count($decodedPayload)
        ], 200);
    }
    
    /**
     * Process LoRaWAN sensor readings
     */
    private function processLoRaWANSensorReadings(Device $device, array $decodedPayload, Carbon $timestamp)
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
     * Extract numeric value from string like "56.4 celsius"
     */
    private function extractValueFromString($valueString)
    {
        if (is_numeric($valueString)) {
            return (float)$valueString;
        }
        
        // Extract number from string like "56.4 celsius" or "40.0 percent"
        preg_match('/([0-9]*\.?[0-9]+)/', $valueString, $matches);
        return isset($matches[1]) ? (float)$matches[1] : 0;
    }
    
    /**
     * Extract unit from string like "56.4 celsius"
     */
    private function extractUnitFromString($valueString)
    {
        if (is_numeric($valueString)) {
            return '';
        }
        
        // Common unit mappings for MQTT sensors
        $unitMappings = [
            'celsius' => '°C',
            'percent' => '%',
            'percentage' => '%',
            'meters' => 'm',
            'meter' => 'm',
            'degrees' => '°',
            'degree' => '°',
            'fahrenheit' => '°F',
            'kelvin' => 'K',
            'pascal' => 'Pa',
            'bar' => 'bar',
            'lux' => 'lx',
            'volt' => 'V',
            'ampere' => 'A',
            'amp' => 'A',
            'watt' => 'W'
        ];
        
        $valueString = strtolower($valueString);
        foreach ($unitMappings as $text => $unit) {
            if (strpos($valueString, $text) !== false) {
                return $unit;
            }
        }
        
        return '';
    }
    
    /**
     * Guess unit from numeric value for unknown sensor types
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
     * Test webhook endpoint with sample MQTT data
     */
    public function testMqttWebhook(Request $request)
    {
        $sampleMqttPayload = [
            "device_id" => "mqtt-test-device",
            "sensors" => [
                [
                    "type" => "thermal",
                    "value" => "56.4 celsius"
                ],
                [
                    "type" => "humidity",
                    "value" => "40.0 percent"
                ],
                [
                    "type" => "light",
                    "value" => "24 percent"
                ],
                [
                    "type" => "potentiometer",
                    "value" => "100 percent"
                ],
                [
                    "type" => "geolocation",
                    "subtype" => "latitude",
                    "value" => "39.506240"
                ],
                [
                    "type" => "geolocation",
                    "subtype" => "longitude",
                    "value" => "-107.736337"
                ]
            ],
            "timestamp" => 60147
        ];
        
        // Create a new request with the sample payload
        $testRequest = new Request($sampleMqttPayload);
        
        // Call the actual webhook method
        $response = $this->webhook($testRequest);
        
        return response()->json([
            'test_status' => 'completed',
            'webhook_response' => $response->getData(),
            'sample_payload_used' => true,
            'message' => 'Test MQTT webhook executed successfully'
        ]);
    }
    
    /**
     * Test webhook endpoint with sample LoRaWAN data
     */
    public function testLoRaWANWebhook(Request $request)
    {
        $sampleLoRaWANPayload = [
            "name" => "as.up.data.forward",
            "time" => "2025-09-17T09:19:00.730681063Z",
            "end_device_ids" => [
                "device_id" => "test-lorawan-1",
                "application_ids" => [
                    "application_id" => "laravel-backend"
                ],
                "dev_eui" => "70B3D57ED80048A2",
                "dev_addr" => "27FCC0D0"
            ],
            "uplink_message" => [
                "f_port" => 1,
                "frm_payload" => "E4gWLlUCRm6s+OcLSAAPAg==",
                "decoded_payload" => [
                    "altitude" => 15,
                    "battery" => 85,
                    "gps_fix" => 2,
                    "gps_fix_type" => "3D Fix",
                    "humidity" => 56.78,
                    "latitude" => 38.170284,
                    "longitude" => -119.076024,
                    "temperature" => 50
                ]
            ],
            "received_at" => "2025-09-17T09:19:00.730681063Z",
            "simulated" => true
        ];
        
        // Create a new request with the sample payload
        $testRequest = new Request($sampleLoRaWANPayload);
        
        // Call the actual webhook method
        $response = $this->webhook($testRequest);
        
        return response()->json([
            'test_status' => 'completed',
            'webhook_response' => $response->getData(),
            'sample_payload_used' => true,
            'message' => 'Test LoRaWAN webhook executed successfully'
        ]);
    }
}
