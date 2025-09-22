<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\Sensor;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;

class UniversalMQTTListener extends Command
{
    protected $signature = 'mqtt:listen-all {--timeout=0}';
    protected $description = 'Listen to MQTT topics for all devices with MQTT connection type';

    private $mqttClients = [];
    private $devices = [];

    public function handle()
    {
        $timeout = (int) $this->option('timeout');

        // Get all MQTT devices
        $this->devices = Device::where('connection_type', 'mqtt')
            ->where('is_active', true)
            ->whereNotNull('mqtt_host')
            ->whereNotNull('mqtt_topics')
            ->get();

        if ($this->devices->isEmpty()) {
            $this->error("No active MQTT devices found.");
            return 1;
        }

        $this->info("Found " . $this->devices->count() . " MQTT devices to monitor:");
        foreach ($this->devices as $device) {
            $this->info("- {$device->name} ({$device->device_id}) - {$device->mqtt_host}");
        }

        try {
            // Connect to all MQTT brokers
            $this->connectToAllBrokers();

            $this->info("Listening for messages from all devices... (Press Ctrl+C to stop)");

            // Keep the script running
            if ($timeout > 0) {
                $this->runWithTimeout($timeout);
            } else {
                $this->runIndefinitely();
            }

        } catch (\Exception $e) {
            $this->error("Universal MQTT Listener Error: " . $e->getMessage());
            return 1;
        } finally {
            $this->disconnectAll();
        }

        return 0;
    }

    private function connectToAllBrokers()
    {
        $brokerGroups = $this->groupDevicesByBroker();

        foreach ($brokerGroups as $brokerKey => $devices) {
            $firstDevice = $devices->first();
            
            try {
                $this->info("Connecting to broker: {$firstDevice->mqtt_host}");
                
                // Create connection settings with SHORT timeouts
                $connectionSettings = new ConnectionSettings();
                $connectionSettings->setKeepAliveInterval($firstDevice->keepalive ?: 60);
                $connectionSettings->setConnectTimeout(5); // Only 5 seconds!
                $connectionSettings->setSocketTimeout(5);  // Only 5 seconds!
                $connectionSettings->setUseTls($firstDevice->use_ssl);
                
                if ($firstDevice->username) {
                    $connectionSettings->setUsername($firstDevice->username);
                }
                
                if ($firstDevice->password) {
                    $connectionSettings->setPassword($firstDevice->password);
                }

                // Create MQTT client
                $clientId = 'laravel_universal_' . time() . '_' . substr(md5($brokerKey), 0, 8);
                $port = $firstDevice->port ?: ($firstDevice->use_ssl ? 8883 : 1883);
                
                $this->info("⏳ Attempting connection to {$firstDevice->mqtt_host}:{$port} (timeout: 5s)");
                
                $mqtt = new MqttClient($firstDevice->mqtt_host, $port, $clientId);
                
                // Use a timeout wrapper for the connection
                $connected = false;
                $startTime = time();
                
                try {
                    $mqtt->connect($connectionSettings, true);
                    $connected = true;
                    $this->info("✅ Connected to {$firstDevice->mqtt_host} successfully!");
                } catch (\Exception $connectException) {
                    $this->warn("❌ Failed to connect to {$firstDevice->mqtt_host}: " . $connectException->getMessage());
                    throw $connectException;
                }
                
                if ($connected) {
                    $this->mqttClients[$brokerKey] = $mqtt;

                    // Subscribe to all topics for devices on this broker
                    foreach ($devices as $device) {
                        foreach ($device->mqtt_topics as $topic) {
                            $this->info("📋 Subscribing to topic: {$topic} (Device: {$device->name})");
                            try {
                                $mqtt->subscribe($topic, function (string $topic, string $message) use ($device) {
                                    $this->processMqttMessage($device, $topic, $message);
                                }, 0);
                            } catch (\Exception $subException) {
                                $this->warn("⚠️ Failed to subscribe to {$topic}: " . $subException->getMessage());
                            }
                        }
                        
                        // Update device status to online
                        $device->update([
                            'status' => 'online',
                            'last_seen_at' => now()
                        ]);
                    }
                }

            } catch (\Exception $e) {
                $this->error("❌ Broker connection failed: {$firstDevice->mqtt_host} - " . $e->getMessage());
                $this->warn("⚠️ Skipping broker {$firstDevice->mqtt_host} and continuing with others...");
                
                // Update device status to error for all devices on this broker
                foreach ($devices as $device) {
                    $device->update([
                        'status' => 'error',
                        'last_seen_at' => now()
                    ]);
                }
                
                // Continue to next broker instead of failing completely
                continue;
            }
        }
        
        // Check if we have any successful connections
        if (empty($this->mqttClients)) {
            throw new \Exception("Failed to connect to any MQTT brokers!");
        }
        
        $this->info("🎯 Successfully connected to " . count($this->mqttClients) . " broker(s)");
    }


    private function groupDevicesByBroker()
    {
        return $this->devices->groupBy(function ($device) {
            return $device->mqtt_host . ':' . ($device->port ?: ($device->use_ssl ? 8883 : 1883)) . ':' . ($device->username ?: 'anonymous');
        });
    }

    private function runWithTimeout($timeout)
    {
        $startTime = time();
        while ((time() - $startTime) < $timeout) {
            foreach ($this->mqttClients as $mqtt) {
                try {
                    $mqtt->loop(false, 1); // Non-blocking loop with 1 second timeout
                } catch (\Exception $e) {
                    $this->warn("Loop error: " . $e->getMessage());
                }
            }
            usleep(100000); // Sleep 100ms between loops
        }
    }

    private function runIndefinitely()
    {
        while (true) {
            foreach ($this->mqttClients as $mqtt) {
                try {
                    $mqtt->loop(false, 1); // Non-blocking loop with 1 second timeout
                } catch (\Exception $e) {
                    $this->warn("Loop error: " . $e->getMessage());
                }
            }
            usleep(100000); // Sleep 100ms between loops
        }
    }

    private function disconnectAll()
    {
        foreach ($this->mqttClients as $brokerKey => $mqtt) {
            try {
                $mqtt->disconnect();
                $this->info("Disconnected from broker: {$brokerKey}");
            } catch (\Exception $e) {
                $this->warn("Error disconnecting from {$brokerKey}: " . $e->getMessage());
            }
        }
    }

    private function processMqttMessage(Device $device, string $topic, string $message)
    {
        $this->info("[{$device->name}] Received message on topic '{$topic}': " . substr($message, 0, 200) . (strlen($message) > 200 ? '...' : ''));

        try {
            // Try to decode JSON message
            $data = json_decode($message, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn("[{$device->name}] Message is not valid JSON, treating as plain text");
                // If not JSON, treat as plain text and extract sensor type from topic
                $topicParts = explode('/', $topic);
                $sensorType = end($topicParts);
                $this->createOrUpdateSensor($device, $sensorType, $message, null, $topic);
                return;
            }

            // Determine device type and handle accordingly
            $this->handleDevicePayload($device, $data, $topic);

            // Update device last seen
            $device->update([
                'status' => 'online',
                'last_seen_at' => now()
            ]);

        } catch (\Exception $e) {
            $this->error("[{$device->name}] Error processing message: " . $e->getMessage());
            \Log::error('Universal MQTT message processing error', [
                'device_id' => $device->device_id,
                'topic' => $topic,
                'message' => substr($message, 0, 500),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleDevicePayload(Device $device, array $data, string $topic)
    {
        // Handle payload based on broker type
        $brokerType = $device->connection_broker ?? 'emqx'; // Default to emqx
        
        $this->info("[{$device->name}] Processing payload for broker type: {$brokerType}");
        
        switch (strtolower($brokerType)) {
            case 'the_things_stack':
            case 'ttn':
            case 'lorawan':
                $this->handleTheThingsStackPayload($device, $data, $topic);
                break;
                
            case 'emqx':
            case 'esp32':
            default:
                // Handle ESP32/EMQX format
                if (isset($data['sensors']) && is_array($data['sensors'])) {
                    $this->handleESP32Payload($device, $data, $topic);
                } else {
                    $this->handleSimplePayload($device, $data, $topic);
                }
                break;
        }
    }

    private function handleTheThingsStackPayload(Device $device, array $data, string $topic)
    {
        $this->info("[{$device->name}] Processing The Things Stack payload");
        
        // Extract decoded payload from The Things Stack structure
        $decodedPayload = null;
        
        // Handle your specific The Things Stack structure
        if (isset($data['data']['uplink_message']['decoded_payload'])) {
            $decodedPayload = $data['data']['uplink_message']['decoded_payload'];
        } elseif (isset($data['uplink_message']['decoded_payload'])) {
            $decodedPayload = $data['uplink_message']['decoded_payload'];
        } elseif (isset($data['decoded_payload'])) {
            $decodedPayload = $data['decoded_payload'];
        }

        if (!$decodedPayload) {
            $this->warn("[{$device->name}] No decoded payload found in The Things Stack message");
            return;
        }

        $this->info("[{$device->name}] Decoded payload: " . json_encode($decodedPayload));

        // Process each sensor value in the decoded payload
        foreach ($decodedPayload as $key => $value) {
            // Skip non-sensor fields
            if (in_array(strtolower($key), ['gps_fix', 'gps_fix_type', 'warnings', 'errors'])) {
                continue;
            }

            $sensorType = $this->normalizeSensorType($key);
            $unit = $this->getUnitForSensorType($sensorType);
            
            $this->createOrUpdateSensor($device, $sensorType, $value, $unit, $topic);
        }
    }

    private function handleESP32Payload(Device $device, array $data, string $topic)
    {
        $this->info("[{$device->name}] Processing ESP32 payload");
        
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

    private function handleSimplePayload(Device $device, array $data, string $topic)
    {
        $this->info("[{$device->name}] Processing simple key-value payload");
        
        // Check if it's a single sensor reading with sensor_type field
        if (isset($data['sensor_type']) && isset($data['value'])) {
            $this->createOrUpdateSensor($device, $data['sensor_type'], $data['value'], $data['unit'] ?? null, $topic);
            return;
        }

        // Handle multiple sensor readings in one message
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

        $this->info("[{$device->name}] Updated sensor '{$sensorType}' with value: {$cleanValue}" . ($unit ? " {$unit}" : ""));
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
            'battery' => 'battery',
            'altitude' => 'altitude',
            'alt' => 'altitude',
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
            'battery' => '%',
            'altitude' => 'm',
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
