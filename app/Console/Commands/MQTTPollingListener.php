<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Services\MqttPayloadHandler;
use App\Traits\MqttUtilities;
use Bluerhinos\phpMQTT;
use Illuminate\Support\Facades\Log;

class MQTTPollingListener extends Command
{
    use MqttUtilities;

    protected $signature = 'mqtt:poll {--interval=10} {--timeout=0} {--device=}';
    protected $description = 'Poll MQTT devices every X seconds to read the last message without maintaining persistent connections';

    private MqttPayloadHandler $payloadHandler;
    private bool $shouldStop = false;

    public function __construct(MqttPayloadHandler $payloadHandler)
    {
        parent::__construct();
        $this->payloadHandler = $payloadHandler;
        $this->setupSignalHandlers();
    }

    /**
     * Set up signal handlers for graceful shutdown.
     */
    private function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () {
                $this->info("\n🛑 Interrupt received, shutting down gracefully...");
                $this->shouldStop = true;
            });
        }
    }

    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        $timeout = (int) $this->option('timeout');
        $deviceFilter = $this->option('device');

        Log::info('MQTT: Starting MQTT Polling Listener', [
            'interval' => $interval,
            'timeout' => $timeout,
            'device_filter' => $deviceFilter
        ]);

        try {
            // Get devices to monitor
            $devices = $this->getActiveDevices($deviceFilter);
            
            if ($devices->isEmpty()) {
                $this->error("No active MQTT devices found.");
                return 1;
            }

            $this->displayDeviceInfo($devices);
            $this->info("📡 Starting MQTT polling every {$interval} seconds... (Press Ctrl+C to stop)");

            $startTime = time();
            $pollCount = 0;

            while (!$this->shouldStop) {
                $pollCount++;
                $this->info("\n🔄 Poll #{$pollCount} - " . now()->format('H:i:s'));
                
                $this->pollAllDevices($devices);
                
                // Check timeout
                if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                    $this->info("⏰ Timeout reached, shutting down...");
                    break;
                }

                // Wait for next poll
                if (!$this->shouldStop) {
                    $this->info("⏳ Waiting {$interval} seconds until next poll...");
                    sleep($interval);
                }
            }

        } catch (\Exception $e) {
            $this->error("MQTT Polling Error: " . $e->getMessage());
            Log::error('MQTT: MQTT Polling Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        $this->info("✅ MQTT Polling Listener stopped gracefully");
        Log::info('MQTT: MQTT Polling Listener stopped');
        return 0;
    }

    /**
     * Get active MQTT devices, optionally filtered by device ID.
     */
    private function getActiveDevices(?string $deviceFilter)
    {
        $query = Device::where('connection_type', 'mqtt')
            ->where('is_active', true)
            ->whereNotNull('mqtt_host')
            ->whereNotNull('mqtt_topics');

        if ($deviceFilter) {
            $query->where('device_id', 'like', "%{$deviceFilter}%");
        }

        return $query->get();
    }

    /**
     * Display device information.
     */
    private function displayDeviceInfo($devices): void
    {
        $this->info("Found " . $devices->count() . " MQTT devices to poll:");
        
        foreach ($devices as $device) {
            $brokerType = $this->detectBrokerType($device);
            $port = $this->getDevicePort($device);
            $topics = implode(', ', $device->mqtt_topics);
            
            $this->info("- {$device->name} ({$device->device_id})");
            $this->info("  └─ {$device->mqtt_host}:{$port} [{$brokerType}]");
            $this->info("  └─ Topics: {$topics}");
        }
    }

    /**
     * Poll all devices for messages.
     */
    private function pollAllDevices($devices): void
    {
        foreach ($devices as $device) {
            if ($this->shouldStop) {
                break;
            }

            $this->pollDevice($device);
        }
    }

    /**
     * Poll a single device for messages.
     */
    private function pollDevice(Device $device): void
    {
        $brokerType = $this->detectBrokerType($device);
        $port = $this->getDevicePort($device);
        
        $this->info("  📱 Polling {$device->name} ({$brokerType})...");
        
        try {
            $messages = $this->connectAndReadMessages($device, $brokerType);
            
            if (empty($messages)) {
                $this->info("    └─ No new messages");
            } else {
                $this->info("    └─ Received " . count($messages) . " message(s)");
                
                // Count sensors before processing
                $sensorsBefore = $device->sensors()->count();
                
                // Process each message
                foreach ($messages as $message) {
                    $this->info("      📄 Processing: {$message['topic']}");
                    $this->payloadHandler->handleMessage(
                        collect([$device]), 
                        $message['topic'], 
                        $message['payload']
                    );
                }
                
                // Count sensors after processing and show updates
                $sensorsAfter = $device->sensors()->count();
                $newSensors = $sensorsAfter - $sensorsBefore;
                
                if ($newSensors > 0) {
                    $this->info("      ✨ Created {$newSensors} new sensor(s)");
                }
                
                // Show recent sensor readings
                $recentSensors = $device->sensors()
                    ->where('reading_timestamp', '>=', now()->subMinutes(1))
                    ->get();
                    
                if ($recentSensors->count() > 0) {
                    $this->info("      📊 Updated sensors:");
                    foreach ($recentSensors as $sensor) {
                        $this->info("        • {$sensor->sensor_type}: {$sensor->getFormattedValue()} ({$sensor->getTimeSinceLastReading()})");
                    }
                }
            }

            // Update device status
            $device->update([
                'status' => 'online',
                'last_seen_at' => now()
            ]);

        } catch (\Exception $e) {
            $this->warn("    └─ Error: " . $e->getMessage());
            Log::warning("MQTT: Polling error for device {$device->name}", [
                'device_id' => $device->device_id,
                'error' => $e->getMessage()
            ]);

            // Update device status to error
            $device->update([
                'status' => 'error',
                'last_seen_at' => now()
            ]);
        }
    }

    /**
     * Connect to device and read available messages.
     */
    private function connectAndReadMessages(Device $device, string $brokerType): array
    {
        $clientId = $this->generateClientId($brokerType, $device->device_id);
        $port = $this->getDevicePort($device);
        $host = $device->mqtt_host;
        
        // Create phpMQTT instance with short timeout
        $mqtt = new phpMQTT($host, $port, $clientId);
        $mqtt->keepalive = 10; // Short keepalive for polling
        
        // Set connection timeout
        if (property_exists($mqtt, 'socket_timeout')) {
            $mqtt->socket_timeout = 5; // 5 seconds timeout
        }

        Log::debug("MQTT: Connecting to {$brokerType} at {$host}:{$port} for polling");
        
        // Handle authentication
        $username = $device->username;
        $password = $device->password;
        
        // Connect with timeout protection
        $connected = false;
        $startTime = time();
        $maxConnectionTime = 10; // Maximum 10 seconds for connection
        
        try {
            $connected = $mqtt->connect(true, null, $username, $password);
            
            if (!$connected) {
                throw new \Exception("Failed to connect to {$brokerType}");
            }

            // Check if connection took too long
            if ((time() - $startTime) > $maxConnectionTime) {
                $mqtt->close();
                throw new \Exception("Connection timeout after " . (time() - $startTime) . " seconds");
            }

            Log::debug("MQTT: Connected to {$brokerType} for polling");
            
            // Subscribe to topics and collect messages
            $messages = [];
            $messageTimeout = 3; // Wait max 3 seconds for messages
            
            foreach ($device->mqtt_topics as $topic) {
                Log::debug("MQTT: Subscribing to topic: {$topic}");
                
                // Subscribe to topic
                $mqtt->subscribe([$topic => ['qos' => 0, 'function' => function($receivedTopic, $message) use (&$messages) {
                    $messages[] = [
                        'topic' => $receivedTopic,
                        'payload' => $message,
                        'timestamp' => now()
                    ];
                    Log::debug("MQTT: Collected message from topic: {$receivedTopic}");
                }]], 0);
            }
            
            // Process messages for a short time
            $messageStartTime = time();
            while ((time() - $messageStartTime) < $messageTimeout && !$this->shouldStop) {
                $mqtt->proc();
                usleep(100000); // 0.1 second sleep
            }
            
            // Disconnect
            $mqtt->close();
            Log::debug("MQTT: Disconnected from {$brokerType} after polling");
            
            return $messages;

        } catch (\Exception $e) {
            if ($connected && isset($mqtt)) {
                try {
                    $mqtt->close();
                } catch (\Exception $closeError) {
                    Log::debug("MQTT: Error closing connection: " . $closeError->getMessage());
                }
            }
            throw $e;
        }
    }
}
