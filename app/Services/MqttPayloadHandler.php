<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Sensor;
use App\Traits\MqttUtilities;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MqttPayloadHandler
{
    use MqttUtilities;

    /**
     * Handle incoming MQTT message.
     */
    public function handleMessage(Collection $devices, string $topic, string $message): void
    {
        // Find the device that matches this message topic
        $matchedDevice = null;
        foreach ($devices as $device) {
            foreach ($device->mqtt_topics as $deviceTopic) {
                if ($this->topicMatches($deviceTopic, $topic)) {
                    $matchedDevice = $device;
                    break 2;
                }
            }
        }

        if ($matchedDevice) {
            $this->processMessage($matchedDevice, $topic, $message);
        } else {
            Log::warning("MQTT: Received message on unmatched topic: {$topic}");
        }
    }

    /**
     * Process MQTT message for a specific device.
     */
    private function processMessage(Device $device, string $topic, string $message): void
    {
        $truncatedMessage = strlen($message) > 200 ? substr($message, 0, 200) . '...' : $message;
        Log::info("MQTT: [{$device->name}] Received message on topic '{$topic}': {$truncatedMessage}");

        try {
            // Try to decode JSON message
            $data = json_decode($message, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("MQTT: [{$device->name}] Message is not valid JSON, treating as plain text");
                $this->handlePlainTextMessage($device, $topic, $message);
                return;
            }

            $this->handleJsonPayload($device, $data, $topic);

            // Update device status
            $device->update([
                'status' => 'online',
                'last_seen_at' => now()
            ]);

        } catch (\Exception $e) {
            Log::error("MQTT: [{$device->name}] Error processing message: " . $e->getMessage());
        }
    }

    /**
     * Handle plain text messages.
     */
    private function handlePlainTextMessage(Device $device, string $topic, string $message): void
    {
        $topicParts = explode('/', $topic);
        $sensorType = end($topicParts);
        $this->createOrUpdateSensor($device, $sensorType, $message, null, $topic);
    }

    /**
     * Handle JSON payload based on broker type.
     */
    private function handleJsonPayload(Device $device, array $data, string $topic): void
    {
        $brokerType = $device->connection_broker ?? $this->detectBrokerType($device);
        Log::info("MQTT: [{$device->name}] Processing payload for broker type: {$brokerType}");
        
        switch (strtolower($brokerType)) {
            case 'the_things_stack':
            case 'thethings_stack':
            case 'ttn':
            case 'lorawan':
                $this->handleTheThingsStackPayload($device, $data, $topic);
                break;
                
            case 'hivemq':
            case 'hivemq_cloud':
                $this->handleBrokerSpecificPayload($device, $data, $topic, 'hivemq');
                break;
                
            case 'mosquitto':
            case 'mosquitto':
                $this->handleBrokerSpecificPayload($device, $data, $topic, 'mosquitto');
                break;
                
            case 'emqx':
            case 'esp32':
            default:
                $this->handleBrokerSpecificPayload($device, $data, $topic, 'emqx');
                break;
        }
    }

    /**
     * Handle The Things Stack payload format.
     */
    private function handleTheThingsStackPayload(Device $device, array $data, string $topic): void
    {
        Log::info("MQTT: [{$device->name}] Processing The Things Stack payload");
        
        $decodedPayload = $this->extractTheThingsStackData($data);

        if (!$decodedPayload) {
            Log::warning("MQTT: [{$device->name}] No decoded payload found in The Things Stack message");
            return;
        }

        foreach ($decodedPayload as $key => $value) {
            // Skip non-sensor data
            if (in_array(strtolower($key), ['gps_fix', 'gps_fix_type', 'warnings', 'errors'])) {
                continue;
            }

            $sensorType = $this->normalizeSensorType($key);
            $unit = $this->getUnitForSensorType($sensorType);
            $this->createOrUpdateSensor($device, $sensorType, $value, $unit, $topic);
        }
    }

    /**
     * Extract data from The Things Stack message structure.
     */
    private function extractTheThingsStackData(array $data): ?array
    {
        // Try different possible paths for decoded payload
        $possiblePaths = [
            'uplink_message.decoded_payload.data',
            'uplink_message.decoded_payload',
            'decoded_payload'
        ];

        foreach ($possiblePaths as $path) {
            $result = $this->getNestedValue($data, $path);
            if ($result && is_array($result)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Handle broker-specific payload formats (HiveMQ, EMQX, etc.).
     */
    private function handleBrokerSpecificPayload(Device $device, array $data, string $topic, string $brokerType): void
    {
        Log::info("MQTT: [{$device->name}] Processing {$brokerType} payload");
        
        // Check for ESP32-style sensor array format
        if (isset($data['sensors']) && is_array($data['sensors'])) {
            $this->handleESP32Payload($device, $data, $topic);
            return;
        }

        // Check for explicit sensor_type format
        if (isset($data['sensor_type']) && isset($data['value'])) {
            $this->handleExplicitSensorPayload($device, $data, $topic);
            return;
        }

        // Handle as simple key-value payload
        $this->handleSimplePayload($device, $data, $topic);
    }

    /**
     * Handle ESP32-style payload with sensors array.
     */
    private function handleESP32Payload(Device $device, array $data, string $topic): void
    {
        Log::info("MQTT: [{$device->name}] Processing ESP32 payload");
        
        foreach ($data['sensors'] as $sensorData) {
            if (!isset($sensorData['type']) || !isset($sensorData['value'])) {
                continue;
            }

            $sensorType = $this->normalizeSensorType($sensorData['type']);
            
            // Handle geolocation subtype
            if ($sensorData['type'] === 'geolocation' && isset($sensorData['subtype'])) {
                $sensorType = $sensorData['subtype'];
            }
            
            $cleanValue = $this->extractNumericValue($sensorData['value']);
            $unit = $this->extractUnit($sensorData['value']) ?: $this->getUnitForSensorType($sensorType);
            
            $this->createOrUpdateSensor($device, $sensorType, $cleanValue, $unit, $topic);
        }
    }

    /**
     * Handle payload with explicit sensor_type field.
     */
    private function handleExplicitSensorPayload(Device $device, array $data, string $topic): void
    {
        Log::info("MQTT: [{$device->name}] Processing explicit sensor payload");
        
        $sensorType = $this->normalizeSensorType($data['sensor_type']);
        $value = $data['value'];
        $unit = $data['unit'] ?? $this->getUnitForSensorType($sensorType);
        
        $this->createOrUpdateSensor($device, $sensorType, $value, $unit, $topic);
    }

    /**
     * Handle simple key-value payload.
     */
    private function handleSimplePayload(Device $device, array $data, string $topic): void
    {
        Log::info("MQTT: [{$device->name}] Processing simple key-value payload");
        
        foreach ($data as $key => $value) {
            // Skip metadata fields
            if (in_array(strtolower($key), ['timestamp', 'device_id', 'message_id', 'metadata'])) {
                continue;
            }
            
            $sensorType = $this->normalizeSensorType($key);
            $unit = $this->getUnitForSensorType($sensorType);
            $this->createOrUpdateSensor($device, $sensorType, $value, $unit, $topic);
        }
    }

    /**
     * Create or update sensor with the received data.
     */
    private function createOrUpdateSensor(Device $device, string $sensorType, $value, ?string $unit, string $topic): void
    {
        $cleanValue = $this->extractNumericValue($value);
        
        try {
            $sensor = Sensor::firstOrCreate(
                [
                    'device_id' => $device->id,
                    'sensor_type' => $sensorType,
                    'user_id' => $device->user_id,
                ],
                [
                    'sensor_name' => $this->generateSensorName($sensorType),
                    'description' => 'Auto-created from MQTT topic: ' . $topic,
                    'unit' => $unit,
                    'enabled' => true,
                ]
            );

            $sensor->updateReading($cleanValue, now());
            
            // Update unit if it has changed
            if ($unit && $sensor->unit !== $unit) {
                $sensor->update(['unit' => $unit]);
            }

            $unitDisplay = $unit ? " {$unit}" : "";
            Log::info("MQTT: [{$device->name}] Updated sensor '{$sensorType}' with value: {$cleanValue}{$unitDisplay}");
            
        } catch (\Exception $e) {
            Log::error("MQTT: [{$device->name}] Error creating/updating sensor '{$sensorType}': " . $e->getMessage());
        }
    }

    /**
     * Generate a human-readable sensor name.
     */
    private function generateSensorName(string $sensorType): string
    {
        $name = ucfirst(str_replace('_', ' ', $sensorType));
        return $name . ' Sensor';
    }

    /**
     * Get nested value from array using dot notation.
     */
    private function getNestedValue(array $array, string $path)
    {
        $keys = explode('.', $path);
        $current = $array;
        
        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }
        
        return $current;
    }

    /**
     * Validate sensor data before processing.
     */
    private function validateSensorData(string $sensorType, $value): bool
    {
        // Basic validation rules
        if (empty($sensorType)) {
            return false;
        }

        // Check for reasonable value ranges for common sensor types
        if (is_numeric($value)) {
            $numericValue = (float) $value;
            
            switch ($sensorType) {
                case 'temperature':
                    // Reasonable temperature range: -50°C to 100°C
                    return $numericValue >= -50 && $numericValue <= 100;
                    
                case 'humidity':
                    // Humidity percentage: 0% to 100%
                    return $numericValue >= 0 && $numericValue <= 100;
                    
                case 'battery':
                    // Battery percentage: 0% to 100%
                    return $numericValue >= 0 && $numericValue <= 100;
                    
                case 'light':
                case 'potentiometer':
                    // Percentage values: 0% to 100%
                    return $numericValue >= 0 && $numericValue <= 100;
                    
                case 'pressure':
                    // Atmospheric pressure: reasonable range in hPa
                    return $numericValue >= 800 && $numericValue <= 1200;
                    
                case 'latitude':
                    // Latitude: -90 to 90 degrees
                    return $numericValue >= -90 && $numericValue <= 90;
                    
                case 'longitude':
                    // Longitude: -180 to 180 degrees
                    return $numericValue >= -180 && $numericValue <= 180;
            }
        }
        
        // If no specific validation rule, accept the value
        return true;
    }

    /**
     * Log payload processing statistics.
     */
    public function logProcessingStats(Device $device, array $data, int $sensorsProcessed): void
    {
        Log::info("MQTT: [{$device->name}] Processing complete", [
            'sensors_processed' => $sensorsProcessed,
            'payload_size' => strlen(json_encode($data)),
            'device_status' => $device->status
        ]);
    }
}
