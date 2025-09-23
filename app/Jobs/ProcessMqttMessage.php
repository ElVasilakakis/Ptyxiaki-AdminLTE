<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\Sensor;
use App\Traits\MqttUtilities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMqttMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MqttUtilities;

    public $timeout = 30; // Job timeout in seconds
    public $tries = 3; // Number of retry attempts
    public $backoff = [5, 10, 30]; // Exponential backoff in seconds

    private Device $device;
    private string $topic;
    private string $message;

    /**
     * Create a new job instance.
     */
    public function __construct(Device $device, string $topic, string $message)
    {
        $this->device = $device;
        $this->topic = $topic;
        $this->message = $message;
        
        // Set queue based on broker type for prioritization
        $brokerType = $this->detectBrokerType($device);
        if ($brokerType === 'thethings_stack') {
            $this->onQueue('tts'); // Separate queue for TTS to prevent blocking
        } else {
            $this->onQueue('mqtt');
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $truncatedMessage = strlen($this->message) > 200 ? substr($this->message, 0, 200) . '...' : $this->message;
        Log::info("MQTT Job: [{$this->device->name}] Processing message on topic '{$this->topic}': {$truncatedMessage}");

        try {
            // Set time limit for TTS processing to prevent hanging
            $brokerType = $this->detectBrokerType($this->device);
            if ($brokerType === 'thethings_stack') {
                set_time_limit(5); // 5 second limit for TTS
                Log::info("MQTT Job: [{$this->device->name}] Set 5s time limit for TTS processing");
            }

            $this->processMessage();

            // Update device status on successful processing
            $this->device->update([
                'status' => 'online',
                'last_seen_at' => now()
            ]);

            Log::info("MQTT Job: [{$this->device->name}] Message processed successfully");

        } catch (\Exception $e) {
            Log::error("MQTT Job: [{$this->device->name}] Error processing message: " . $e->getMessage(), [
                'topic' => $this->topic,
                'attempt' => $this->attempts(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update device status to error if all retries exhausted
            if ($this->attempts() >= $this->tries) {
                $this->device->update([
                    'status' => 'error',
                    'last_seen_at' => now()
                ]);
            }

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Process the MQTT message.
     */
    private function processMessage(): void
    {
        try {
            // Try to decode JSON message
            $data = json_decode($this->message, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("MQTT Job: [{$this->device->name}] Message is not valid JSON, treating as plain text");
                $this->handlePlainTextMessage();
                return;
            }

            $this->handleJsonPayload($data);

        } catch (\Exception $e) {
            Log::error("MQTT Job: [{$this->device->name}] Error in processMessage: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle plain text messages.
     */
    private function handlePlainTextMessage(): void
    {
        $topicParts = explode('/', $this->topic);
        $sensorType = end($topicParts);
        $this->createOrUpdateSensor($sensorType, $this->message, null);
    }

    /**
     * Handle JSON payload based on broker type.
     */
    private function handleJsonPayload(array $data): void
    {
        $brokerType = $this->device->connection_broker ?? $this->detectBrokerType($this->device);
        Log::info("MQTT Job: [{$this->device->name}] Processing payload for broker type: {$brokerType}");
        
        switch (strtolower($brokerType)) {
            case 'the_things_stack':
            case 'thethings_stack':
            case 'ttn':
            case 'lorawan':
                $this->handleTheThingsStackPayload($data);
                break;
                
            case 'hivemq':
            case 'hivemq_cloud':
                $this->handleBrokerSpecificPayload($data, 'hivemq');
                break;
                
            case 'emqx':
            case 'esp32':
            default:
                $this->handleBrokerSpecificPayload($data, 'emqx');
                break;
        }
    }

    /**
     * Handle The Things Stack payload format with timeout protection.
     */
    private function handleTheThingsStackPayload(array $data): void
    {
        Log::info("MQTT Job: [{$this->device->name}] Processing The Things Stack payload with timeout protection");
        
        try {
            // Set a shorter time limit specifically for TTS payload extraction
            $originalTimeLimit = ini_get('max_execution_time');
            set_time_limit(3); // 3 seconds for TTS payload processing
            
            $decodedPayload = $this->extractTheThingsStackData($data);

            if (!$decodedPayload) {
                Log::warning("MQTT Job: [{$this->device->name}] No decoded payload found in The Things Stack message");
                return;
            }

            foreach ($decodedPayload as $key => $value) {
                // Skip non-sensor data
                if (in_array(strtolower($key), ['gps_fix', 'gps_fix_type', 'warnings', 'errors'])) {
                    continue;
                }

                $sensorType = $this->normalizeSensorType($key);
                $unit = $this->getUnitForSensorType($sensorType);
                $this->createOrUpdateSensor($sensorType, $value, $unit);
            }
            
            // Restore original time limit
            set_time_limit($originalTimeLimit);
            
        } catch (\Exception $e) {
            Log::error("MQTT Job: [{$this->device->name}] TTS payload processing failed: " . $e->getMessage());
            throw $e;
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
    private function handleBrokerSpecificPayload(array $data, string $brokerType): void
    {
        Log::info("MQTT Job: [{$this->device->name}] Processing {$brokerType} payload");
        
        // Check for ESP32-style sensor array format
        if (isset($data['sensors']) && is_array($data['sensors'])) {
            $this->handleESP32Payload($data);
            return;
        }

        // Check for explicit sensor_type format
        if (isset($data['sensor_type']) && isset($data['value'])) {
            $this->handleExplicitSensorPayload($data);
            return;
        }

        // Handle as simple key-value payload
        $this->handleSimplePayload($data);
    }

    /**
     * Handle ESP32-style payload with sensors array.
     */
    private function handleESP32Payload(array $data): void
    {
        Log::info("MQTT Job: [{$this->device->name}] Processing ESP32 payload");
        
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
            
            $this->createOrUpdateSensor($sensorType, $cleanValue, $unit);
        }
    }

    /**
     * Handle payload with explicit sensor_type field.
     */
    private function handleExplicitSensorPayload(array $data): void
    {
        Log::info("MQTT Job: [{$this->device->name}] Processing explicit sensor payload");
        
        $sensorType = $this->normalizeSensorType($data['sensor_type']);
        $value = $data['value'];
        $unit = $data['unit'] ?? $this->getUnitForSensorType($sensorType);
        
        $this->createOrUpdateSensor($sensorType, $value, $unit);
    }

    /**
     * Handle simple key-value payload.
     */
    private function handleSimplePayload(array $data): void
    {
        Log::info("MQTT Job: [{$this->device->name}] Processing simple key-value payload");
        
        foreach ($data as $key => $value) {
            // Skip metadata fields
            if (in_array(strtolower($key), ['timestamp', 'device_id', 'message_id', 'metadata'])) {
                continue;
            }
            
            $sensorType = $this->normalizeSensorType($key);
            $unit = $this->getUnitForSensorType($sensorType);
            $this->createOrUpdateSensor($sensorType, $value, $unit);
        }
    }

    /**
     * Create or update sensor with the received data.
     */
    private function createOrUpdateSensor(string $sensorType, $value, ?string $unit): void
    {
        $cleanValue = $this->extractNumericValue($value);
        
        try {
            $sensor = Sensor::firstOrCreate(
                [
                    'device_id' => $this->device->id,
                    'sensor_type' => $sensorType,
                    'user_id' => $this->device->user_id,
                ],
                [
                    'sensor_name' => $this->generateSensorName($sensorType),
                    'description' => 'Auto-created from MQTT topic: ' . $this->topic,
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
            Log::info("MQTT Job: [{$this->device->name}] Updated sensor '{$sensorType}' with value: {$cleanValue}{$unitDisplay}");
            
        } catch (\Exception $e) {
            Log::error("MQTT Job: [{$this->device->name}] Error creating/updating sensor '{$sensorType}': " . $e->getMessage());
            throw $e;
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
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("MQTT Job: [{$this->device->name}] Job failed permanently", [
            'topic' => $this->topic,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Update device status to error
        $this->device->update([
            'status' => 'error',
            'last_seen_at' => now()
        ]);
    }
}
