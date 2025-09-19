<?php

namespace App\Services;

use App\Models\MqttBroker;
use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class WebhookMqttBridge
{
    /**
     * Generate webhook URL for a device
     */
    public function generateWebhookUrl(Device $device): string
    {
        $baseUrl = config('app.url');
        $token = $this->generateSecureToken($device);

        return "{$baseUrl}/api/webhook/mqtt/{$device->device_id}?token={$token}";
    }

    /**
     * Generate secure token for webhook authentication
     */
    private function generateSecureToken(Device $device): string
    {
        return hash('sha256', $device->device_id . $device->user_id . config('app.key'));
    }

    /**
     * Validate webhook token
     */
    public function validateWebhookToken(Device $device, string $token): bool
    {
        $expectedToken = $this->generateSecureToken($device);
        return hash_equals($expectedToken, $token);
    }

    /**
     * Process incoming webhook data (simulating MQTT message)
     */
    public function processWebhookData(Device $device, array $data): array
    {
        try {
            Log::info('Webhook MQTT data received', [
                'device_id' => $device->device_id,
                'data' => $data
            ]);

            // Update device status
            $device->setOnline();

            // Process sensor readings
            $sensorsUpdated = $this->processSensorReadings($device, $data);

            return [
                'success' => true,
                'message' => "Processed {$sensorsUpdated} sensor readings",
                'sensors_updated' => $sensorsUpdated
            ];

        } catch (\Exception $e) {
            Log::error('Webhook MQTT processing error', [
                'device_id' => $device->device_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process webhook data: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process sensor readings from webhook data
     */
    private function processSensorReadings(Device $device, array $data): int
    {
        $sensorsUpdated = 0;
        $timestamp = Carbon::now();

        // Check if data has a 'sensors' array (structured format)
        if (isset($data['sensors']) && is_array($data['sensors'])) {
            foreach ($data['sensors'] as $sensorData) {
                if (!is_array($sensorData) || !isset($sensorData['type']) || !isset($sensorData['value'])) {
                    continue;
                }

                $sensorType = $sensorData['type'];
                $sensorValue = $sensorData['value'];
                $sensorSubtype = $sensorData['subtype'] ?? null;

                // Handle geolocation sensors with subtypes
                if ($sensorType === 'geolocation' && $sensorSubtype) {
                    $sensorType = $sensorSubtype;
                }

                // Parse value to extract numeric part and unit
                $parsedValue = $this->parseValueWithUnit($sensorValue);

                // Determine sensor info
                $sensorInfo = $this->determineSensorInfoFromType($sensorType, $parsedValue['unit']);

                // Find or create sensor
                $sensor = Sensor::firstOrCreate(
                    [
                        'device_id' => $device->id,
                        'sensor_type' => $sensorInfo['type'],
                        'sensor_name' => $sensorInfo['name']
                    ],
                    [
                        'user_id' => $device->user_id,
                        'description' => 'Webhook ' . $sensorInfo['name'] . ' sensor',
                        'unit' => $sensorInfo['unit'],
                        'enabled' => true,
                        'alert_enabled' => false,
                    ]
                );

                // Update sensor reading
                $sensor->updateReading($parsedValue['value'], $timestamp);
                $sensorsUpdated++;
            }
        } else {
            // Process flat data format
            foreach ($data as $key => $value) {
                // Skip non-sensor data
                if (in_array($key, ['timestamp', 'device_id', 'message_id', 'token'])) {
                    continue;
                }

                // Determine sensor info
                $sensorInfo = $this->determineSensorInfo($key, $value);

                if (!$sensorInfo) {
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
                        'description' => 'Webhook ' . $sensorInfo['name'] . ' sensor',
                        'unit' => $sensorInfo['unit'],
                        'enabled' => true,
                        'alert_enabled' => false,
                    ]
                );

                // Update sensor reading
                $sensor->updateReading($value, $timestamp);
                $sensorsUpdated++;
            }
        }

        return $sensorsUpdated;
    }

    /**
     * Parse value with unit (e.g., "56.4 celsius" -> ["value" => 56.4, "unit" => "celsius"])
     */
    private function parseValueWithUnit($valueString): array
    {
        if (is_numeric($valueString)) {
            return ['value' => (float)$valueString, 'unit' => ''];
        }

        $valueString = trim($valueString);

        // Try to extract numeric value and unit
        if (preg_match('/^([+-]?\d*\.?\d+)\s*(.*)$/', $valueString, $matches)) {
            $numericValue = (float)$matches[1];
            $unit = trim($matches[2]);

            return ['value' => $numericValue, 'unit' => $unit];
        }

        // If no numeric value found, return as is
        return ['value' => $valueString, 'unit' => ''];
    }

    /**
     * Determine sensor information from sensor type and detected unit
     */
    private function determineSensorInfoFromType($sensorType, $detectedUnit = ''): array
    {
        // Sensor type mappings
        $sensorMappings = [
            // Temperature sensors
            'thermal' => ['type' => 'thermal', 'name' => 'Thermal', 'unit' => '°C'],
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
            'light' => ['type' => 'light', 'name' => 'Light', 'unit' => '%'],
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
        ];

        $lowerType = strtolower($sensorType);

        if (isset($sensorMappings[$lowerType])) {
            $sensorInfo = $sensorMappings[$lowerType];

            // Override unit if we detected one from the value
            if (!empty($detectedUnit)) {
                $sensorInfo['unit'] = $this->normalizeUnit($detectedUnit);
            }

            return $sensorInfo;
        }

        // If not found in mappings, create generic sensor
        return [
            'type' => $lowerType,
            'name' => ucfirst(str_replace('_', ' ', $sensorType)),
            'unit' => !empty($detectedUnit) ? $this->normalizeUnit($detectedUnit) : ''
        ];
    }

    /**
     * Determine sensor information from key and value (legacy format)
     */
    private function determineSensorInfo($key, $value): ?array
    {
        // Sensor type mappings
        $sensorMappings = [
            'temperature' => ['type' => 'temperature', 'name' => 'Temperature', 'unit' => '°C'],
            'temp' => ['type' => 'temperature', 'name' => 'Temperature', 'unit' => '°C'],
            'humidity' => ['type' => 'humidity', 'name' => 'Humidity', 'unit' => '%'],
            'humid' => ['type' => 'humidity', 'name' => 'Humidity', 'unit' => '%'],
            'pressure' => ['type' => 'pressure', 'name' => 'Pressure', 'unit' => 'hPa'],
            'light' => ['type' => 'light', 'name' => 'Light', 'unit' => 'lux'],
            'motion' => ['type' => 'motion', 'name' => 'Motion', 'unit' => ''],
            'battery' => ['type' => 'battery', 'name' => 'Battery', 'unit' => '%'],
            'latitude' => ['type' => 'latitude', 'name' => 'Latitude', 'unit' => '°'],
            'longitude' => ['type' => 'longitude', 'name' => 'Longitude', 'unit' => '°'],
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
     * Normalize unit names to standard formats
     */
    private function normalizeUnit($unit): string
    {
        $unitMappings = [
            'celsius' => '°C',
            'fahrenheit' => '°F',
            'percent' => '%',
            'percentage' => '%',
            'degrees' => '°',
            'degree' => '°',
        ];

        $lowerUnit = strtolower(trim($unit));

        return $unitMappings[$lowerUnit] ?? $unit;
    }

    /**
     * Guess unit from value
     */
    private function guessUnitFromValue($value): string
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
     * Get webhook instructions for a device
     */
    public function getWebhookInstructions(Device $device): array
    {
        $webhookUrl = $this->generateWebhookUrl($device);

        return [
            'webhook_url' => $webhookUrl,
            'method' => 'POST',
            'content_type' => 'application/json',
            'instructions' => [
                'Send sensor data to the webhook URL using HTTP POST',
                'Include Content-Type: application/json header',
                'Data format options:',
                '1. Structured format: {"sensors": [{"type": "temperature", "value": 25.5}, {"type": "humidity", "value": 60}]}',
                '2. Flat format: {"temperature": 25.5, "humidity": 60, "battery": 85}',
                'The webhook will automatically create sensors and update readings'
            ],
            'example_curl' => "curl -X POST '{$webhookUrl}' -H 'Content-Type: application/json' -d '{\"temperature\": 25.5, \"humidity\": 60}'"
        ];
    }
}
