<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\Sensor;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;

class MQTTDeviceListener extends Command
{
    protected $signature = 'mqtt:listen {device_id} {--timeout=0}';
    protected $description = 'Listen to MQTT topics for a specific device and process sensor data';

    public function handle()
    {
        $deviceId = $this->argument('device_id');
        $timeout = (int) $this->option('timeout');

        // Find the device
        $device = Device::where('device_id', $deviceId)
            ->where('connection_type', 'mqtt')
            ->first();

        if (!$device) {
            $this->error("MQTT device with ID '{$deviceId}' not found.");
            return 1;
        }

        if (!$device->mqtt_host) {
            $this->error("Device '{$deviceId}' does not have MQTT host configured.");
            return 1;
        }

        if (!$device->mqtt_topics || empty($device->mqtt_topics)) {
            $this->error("Device '{$deviceId}' does not have MQTT topics configured.");
            return 1;
        }

        $this->info("Starting MQTT listener for device: {$device->name} ({$device->device_id})");
        $this->info("MQTT Host: {$device->mqtt_host}");
        $this->info("Port: " . ($device->port ?: ($device->use_ssl ? 8883 : 1883)));
        $this->info("Topics: " . implode(', ', $device->mqtt_topics));

        try {
            // Create connection settings
            $connectionSettings = new ConnectionSettings();
            $connectionSettings->setKeepAliveInterval($device->keepalive ?: 60);
            $connectionSettings->setConnectTimeout($device->timeout ?: 30);
            $connectionSettings->setUseTls($device->use_ssl);
            
            if ($device->username) {
                $connectionSettings->setUsername($device->username);
            }
            
            if ($device->password) {
                $connectionSettings->setPassword($device->password);
            }

            // Create MQTT client
            $clientId = $device->client_id ?: 'laravel_' . $device->device_id . '_' . time();
            $port = $device->port ?: ($device->use_ssl ? 8883 : 1883);
            
            $mqtt = new MqttClient($device->mqtt_host, $port, $clientId);

            // Connect to MQTT broker
            $this->info("Connecting to MQTT broker...");
            $mqtt->connect($connectionSettings, true);
            $this->info("Connected successfully!");

            // Update device status to online
            $device->update([
                'status' => 'online',
                'last_seen_at' => now()
            ]);

            // Subscribe to all configured topics
            foreach ($device->mqtt_topics as $topic) {
                $this->info("Subscribing to topic: {$topic}");
                $mqtt->subscribe($topic, function (string $topic, string $message) use ($device) {
                    $this->processMqttMessage($device, $topic, $message);
                }, 0);
            }

            $this->info("Listening for messages... (Press Ctrl+C to stop)");

            // Listen for messages
            if ($timeout > 0) {
                $mqtt->loop(true, $timeout);
            } else {
                $mqtt->loop(true);
            }

        } catch (\Exception $e) {
            $this->error("MQTT Error: " . $e->getMessage());
            
            // Update device status to error
            $device->update([
                'status' => 'error',
                'last_seen_at' => now()
            ]);
            
            return 1;
        } finally {
            if (isset($mqtt)) {
                try {
                    $mqtt->disconnect();
                    $this->info("Disconnected from MQTT broker.");
                } catch (\Exception $e) {
                    $this->warn("Error disconnecting: " . $e->getMessage());
                }
            }
        }

        return 0;
    }

    private function processMqttMessage(Device $device, string $topic, string $message)
    {
        $this->info("Received message on topic '{$topic}': {$message}");

        try {
            // Try to decode JSON message
            $data = json_decode($message, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn("Message is not valid JSON, treating as plain text");
                // If not JSON, treat as plain text and extract sensor type from topic
                $topicParts = explode('/', $topic);
                $sensorType = end($topicParts);
                $this->createOrUpdateSensor($device, $sensorType, $message, null, $topic);
                return;
            }

            // Handle your ESP32's specific JSON format
            if (isset($data['sensors']) && is_array($data['sensors'])) {
                // Handle ESP32 format: {"sensors":[{"type":"thermal","value":"24.0 celsius"},...]}
                foreach ($data['sensors'] as $sensorData) {
                    if (isset($sensorData['type']) && isset($sensorData['value'])) {
                        $sensorType = $this->normalizeSensorType($sensorData['type']);
                        
                        // Handle geolocation with subtype
                        if ($sensorData['type'] === 'geolocation' && isset($sensorData['subtype'])) {
                            $sensorType = $sensorData['subtype']; // latitude or longitude
                        }
                        
                        $cleanValue = $this->extractNumericValue($sensorData['value']);
                        $unit = $this->extractUnit($sensorData['value']) ?: $this->getUnitForSensorType($sensorType);
                        
                        $this->createOrUpdateSensor($device, $sensorType, $cleanValue, $unit, $topic);
                    }
                }
            }
            // Handle simple key-value format
            elseif (is_array($data)) {
                // Check if it's a single sensor reading with sensor_type field
                if (isset($data['sensor_type']) && isset($data['value'])) {
                    $this->createOrUpdateSensor($device, $data['sensor_type'], $data['value'], $data['unit'] ?? null, $topic);
                }
                // Check if it's multiple sensor readings in one message
                else {
                    foreach ($data as $key => $value) {
                        // Skip non-sensor fields
                        if (in_array(strtolower($key), ['timestamp', 'device_id', 'message_id'])) {
                            continue;
                        }
                        
                        // Handle different sensor naming conventions
                        $sensorType = $this->normalizeSensorType($key);
                        $unit = $this->getUnitForSensorType($sensorType);
                        
                        $this->createOrUpdateSensor($device, $sensorType, $value, $unit, $topic);
                    }
                }
            }

            // Update device last seen
            $device->update([
                'status' => 'online',
                'last_seen_at' => now()
            ]);

        } catch (\Exception $e) {
            $this->error("Error processing message: " . $e->getMessage());
            $this->error("Topic: {$topic}, Message: {$message}");
            \Log::error('MQTT message processing error', [
                'device_id' => $device->device_id,
                'topic' => $topic,
                'message' => $message,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function createOrUpdateSensor(Device $device, string $sensorType, $value, ?string $unit, string $topic)
    {
        // Remove units from value if they're included (e.g., "24.0°C" -> "24.0")
        $cleanValue = $this->extractNumericValue($value);
        
        // Find or create sensor
        $sensor = Sensor::firstOrCreate(
            [
                'device_id' => $device->id,
                'sensor_type' => $sensorType,
                'user_id' => $device->user_id,
            ],
            [
                'sensor_name' => ucfirst(str_replace('_', ' ', $sensorType)) . ' Sensor',
                'description' => 'Auto-created from MQTT topic: ' . $topic,
                'unit' => $unit,
                'enabled' => true,
            ]
        );

        // Update sensor reading
        $sensor->updateReading($cleanValue, now());
        
        // Update unit if provided and different
        if ($unit && $sensor->unit !== $unit) {
            $sensor->update(['unit' => $unit]);
        }

        $this->info("Updated sensor '{$sensorType}' with value: {$cleanValue}" . ($unit ? " {$unit}" : ""));
    }

    private function normalizeSensorType(string $key): string
    {
        // Convert common sensor field names to standard types
        $key = strtolower($key);
        
        $mappings = [
            'temp' => 'temperature',
            'temperature' => 'temperature',
            'humid' => 'humidity',
            'humidity' => 'humidity',
            'light' => 'light',
            'potentiometer' => 'potentiometer',
            'pot' => 'potentiometer',
            'lat' => 'latitude',
            'latitude' => 'latitude',
            'lng' => 'longitude',
            'lon' => 'longitude',
            'longitude' => 'longitude',
            'pressure' => 'pressure',
            'soil_moisture' => 'soil_moisture',
            'ph' => 'ph',
        ];

        return $mappings[$key] ?? $key;
    }

    private function getUnitForSensorType(string $sensorType): ?string
    {
        $units = [
            'temperature' => '°C',
            'humidity' => '%',
            'light' => '%',
            'potentiometer' => '%',
            'pressure' => 'hPa',
            'soil_moisture' => '%',
            'latitude' => '°',
            'longitude' => '°',
        ];

        return $units[$sensorType] ?? null;
    }

    private function extractNumericValue($value): float
    {
        // If it's already numeric, return as is
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Extract numeric value from strings like "24.0°C" or "40.0%"
        if (is_string($value)) {
            preg_match('/(-?\d+\.?\d*)/', $value, $matches);
            return isset($matches[0]) ? (float) $matches[0] : 0.0;
        }

        return 0.0;
    }

    private function extractUnit($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        // Extract unit from strings like "24.0 celsius", "40.0 percent"
        $unitMappings = [
            'celsius' => '°C',
            'fahrenheit' => '°F',
            'percent' => '%',
            'percentage' => '%',
            'degrees' => '°',
        ];

        foreach ($unitMappings as $text => $symbol) {
            if (stripos($value, $text) !== false) {
                return $symbol;
            }
        }

        return null;
    }
}
