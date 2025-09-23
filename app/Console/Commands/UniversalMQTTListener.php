<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Services\MqttConnectionService;
use App\Services\MqttPayloadHandler;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UniversalMQTTListener extends Command
{
    protected $signature = 'mqtt:listen-all {--timeout=0} {--connection-timeout=5} {--skip-problematic} {--skip-tts} {--device-reload-interval=60}';
    protected $description = 'Listen to MQTT topics for all devices with MQTT connection type (queued processing with dynamic device reload)';

    private MqttConnectionService $connectionService;
    private MqttPayloadHandler $payloadHandler;
    private array $currentDeviceHash = [];
    private Carbon $lastDeviceReload;
    private int $reconnectAttempts = 0;
    private array $backoffDelays = [5, 10, 30, 60, 120]; // Exponential backoff delays in seconds

    public function __construct(MqttConnectionService $connectionService, MqttPayloadHandler $payloadHandler)
    {
        parent::__construct();
        $this->connectionService = $connectionService;
        $this->payloadHandler = $payloadHandler;
        $this->lastDeviceReload = now();
    }

    public function handle(): int
    {
        $timeout = (int) $this->option('timeout');
        $connectionTimeout = (int) $this->option('connection-timeout');
        $skipProblematic = $this->option('skip-problematic');
        $skipTts = $this->option('skip-tts');
        $deviceReloadInterval = (int) $this->option('device-reload-interval');

        Log::info('MQTT: Starting Universal MQTT Listener with Queue Processing');
        $this->info("🚀 Starting MQTT Listener with queued message processing");
        $this->info("📋 Device reload interval: {$deviceReloadInterval} seconds");
        
        if ($skipTts) {
            $this->warn("⚠️ The Things Stack connections will be skipped");
            Log::info('MQTT: The Things Stack connections will be skipped due to --skip-tts flag');
        }

        while (true) {
            try {
                // Get all MQTT devices and check for changes
                $devices = $this->getActiveDevices();
                
                if ($devices->isEmpty()) {
                    $this->warn("No active MQTT devices found. Retrying in 30 seconds...");
                    Log::warning('MQTT: No active MQTT devices found, retrying');
                    sleep(30);
                    continue;
                }

                // Check if devices have changed
                if ($this->devicesHaveChanged($devices)) {
                    $this->info("🔄 Device configuration changed, reconnecting...");
                    Log::info('MQTT: Device configuration changed, reconnecting');
                    
                    // Disconnect existing connections
                    $this->connectionService->disconnectAll();
                    
                    // Reset reconnect attempts on successful device reload
                    $this->reconnectAttempts = 0;
                }

                $this->displayDeviceInfo($devices);

                // Connect to all MQTT brokers with exponential backoff
                $connectionResults = $this->connectWithBackoff(
                    $devices, 
                    $connectionTimeout, 
                    $skipProblematic,
                    $skipTts
                );

                if ($this->connectionService->getConnectedClientsCount() === 0) {
                    $this->handleConnectionFailure();
                    continue;
                }

                $this->displayConnectionResults($connectionResults);
                $this->info("📡 Listening for messages (queued processing)... Press Ctrl+C to stop");
                Log::info('MQTT: Started listening with queued message processing');

                // Reset reconnect attempts on successful connection
                $this->reconnectAttempts = 0;

                // Keep the script running with periodic device checks
                if ($timeout > 0) {
                    $this->runWithTimeout($timeout, $deviceReloadInterval);
                } else {
                    $this->runIndefinitely($deviceReloadInterval);
                }

                // If we reach here, it means the loop was interrupted
                break;

            } catch (\Exception $e) {
                $this->handleMainLoopError($e);
                
                // Apply exponential backoff before retrying
                $delay = $this->getBackoffDelay();
                $this->warn("⏳ Retrying in {$delay} seconds...");
                Log::info("MQTT: Retrying main loop in {$delay} seconds");
                sleep($delay);
            }
        }

        $this->connectionService->disconnectAll();
        Log::info('MQTT: Universal MQTT Listener stopped');
        return 0;
    }

    /**
     * Get all active MQTT devices.
     */
    private function getActiveDevices()
    {
        return Device::where('connection_type', 'mqtt')
            ->where('is_active', true)
            ->whereNotNull('mqtt_host')
            ->whereNotNull('mqtt_topics')
            ->get();
    }

    /**
     * Display device information.
     */
    private function displayDeviceInfo($devices): void
    {
        $this->info("Found " . $devices->count() . " MQTT devices to monitor:");
        
        foreach ($devices as $device) {
            $brokerType = $this->connectionService->detectBrokerType($device);
            $library = $this->connectionService->selectMqttLibrary($device, $brokerType);
            $port = $this->connectionService->getDevicePort($device);
            
            $this->info("- {$device->name} ({$device->device_id}) - {$device->mqtt_host}:{$port} [{$brokerType}] -> {$library}");
        }
    }

    /**
     * Display connection results.
     */
    private function displayConnectionResults(array $connectionResults): void
    {
        $connected = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($connectionResults as $brokerKey => $result) {
            switch ($result['status']) {
                case 'connected':
                    $connected++;
                    $this->info("✅ Connected to: {$brokerKey}");
                    break;
                case 'failed':
                    $failed++;
                    $this->error("❌ Failed to connect to: {$brokerKey} - " . ($result['error'] ?? 'Unknown error'));
                    break;
                case 'skipped':
                    $skipped++;
                    $this->warn("⚠️ Skipped: {$brokerKey} - " . ($result['reason'] ?? 'Unknown reason'));
                    break;
            }
        }

        $this->info("🎯 Connection Summary: {$connected} connected, {$failed} failed, {$skipped} skipped");
        Log::info('MQTT: Connection Summary', [
            'connected' => $connected,
            'failed' => $failed,
            'skipped' => $skipped
        ]);
    }

    // Legacy methods removed - all functionality moved to services

    /**
     * Run the listener with a timeout and periodic device reload checks.
     */
    private function runWithTimeout(int $timeout, int $deviceReloadInterval): void
    {
        $startTime = time();
        $sleepMicroseconds = config('mqtt.message_processing_sleep', 100000);
        
        Log::info("MQTT: Running with timeout of {$timeout} seconds");
        
        while ((time() - $startTime) < $timeout && !$this->connectionService->isInterrupted()) {
            $this->connectionService->processMessages();
            
            // Check for device changes periodically
            if ($this->shouldReloadDevices($deviceReloadInterval)) {
                $this->checkAndReloadDevices();
            }
            
            usleep($sleepMicroseconds);
        }
        
        if ((time() - $startTime) >= $timeout) {
            $this->info("⏰ Timeout reached, shutting down...");
            Log::info('MQTT: Timeout reached, shutting down');
        }
    }

    /**
     * Run the listener indefinitely with periodic device reload checks.
     */
    private function runIndefinitely(int $deviceReloadInterval): void
    {
        $sleepMicroseconds = config('mqtt.message_processing_sleep', 100000);
        
        Log::info('MQTT: Running indefinitely until interrupted');
        
        while (!$this->connectionService->isInterrupted()) {
            $this->connectionService->processMessages();
            
            // Check for device changes periodically
            if ($this->shouldReloadDevices($deviceReloadInterval)) {
                $this->checkAndReloadDevices();
            }
            
            usleep($sleepMicroseconds);
        }
        
        $this->info("🛑 Interrupt received, shutting down gracefully...");
        Log::info('MQTT: Interrupt received, shutting down gracefully');
    }

    /**
     * Check if devices have changed since last load.
     */
    private function devicesHaveChanged($devices): bool
    {
        $newHash = $this->generateDeviceHash($devices);
        
        if (empty($this->currentDeviceHash) || $this->currentDeviceHash !== $newHash) {
            $this->currentDeviceHash = $newHash;
            $this->lastDeviceReload = now();
            return true;
        }
        
        return false;
    }

    /**
     * Generate a hash of current device configuration.
     */
    private function generateDeviceHash($devices): array
    {
        $hash = [];
        foreach ($devices as $device) {
            $hash[$device->id] = [
                'name' => $device->name,
                'mqtt_host' => $device->mqtt_host,
                'port' => $device->port,
                'mqtt_topics' => $device->mqtt_topics,
                'username' => $device->username,
                'password' => $device->password,
                'use_ssl' => $device->use_ssl,
                'is_active' => $device->is_active,
                'updated_at' => $device->updated_at?->timestamp
            ];
        }
        return $hash;
    }

    /**
     * Check if it's time to reload devices.
     */
    private function shouldReloadDevices(int $deviceReloadInterval): bool
    {
        return $this->lastDeviceReload->diffInSeconds(now()) >= $deviceReloadInterval;
    }

    /**
     * Check for device changes and reload if necessary.
     */
    private function checkAndReloadDevices(): void
    {
        $devices = $this->getActiveDevices();
        
        if ($this->devicesHaveChanged($devices)) {
            $this->info("🔄 Device configuration changed during runtime, triggering reconnection...");
            Log::info('MQTT: Device configuration changed during runtime');
            
            // Disconnect and let the main loop handle reconnection
            $this->connectionService->disconnectAll();
            
            // Force exit from current loop to trigger reconnection
            throw new \Exception('Device configuration changed, reconnection required');
        }
        
        $this->lastDeviceReload = now();
    }

    /**
     * Connect with exponential backoff on failure.
     */
    private function connectWithBackoff($devices, int $connectionTimeout, bool $skipProblematic, bool $skipTts = false): array
    {
        try {
            return $this->connectionService->connectToAllBrokers(
                $devices, 
                $connectionTimeout, 
                $skipProblematic,
                $skipTts
            );
        } catch (\Exception $e) {
            $this->reconnectAttempts++;
            Log::error('MQTT: Connection failed', [
                'attempt' => $this->reconnectAttempts,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle connection failure with exponential backoff.
     */
    private function handleConnectionFailure(): void
    {
        $this->reconnectAttempts++;
        $delay = $this->getBackoffDelay();
        
        $this->warn("⚠️ No successful connections established (attempt {$this->reconnectAttempts})");
        $this->warn("⏳ Retrying in {$delay} seconds...");
        
        Log::warning('MQTT: No successful connections established', [
            'attempt' => $this->reconnectAttempts,
            'delay' => $delay
        ]);
        
        sleep($delay);
    }

    /**
     * Handle main loop errors.
     */
    private function handleMainLoopError(\Exception $e): void
    {
        $this->reconnectAttempts++;
        
        $this->error("❌ MQTT Listener Error (attempt {$this->reconnectAttempts}): " . $e->getMessage());
        
        Log::error('MQTT: Main loop error', [
            'attempt' => $this->reconnectAttempts,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Disconnect all connections on error
        try {
            $this->connectionService->disconnectAll();
        } catch (\Exception $disconnectError) {
            Log::warning('MQTT: Error during disconnect: ' . $disconnectError->getMessage());
        }
    }

    /**
     * Get exponential backoff delay.
     */
    private function getBackoffDelay(): int
    {
        $maxIndex = count($this->backoffDelays) - 1;
        $index = min($this->reconnectAttempts - 1, $maxIndex);
        
        // Add some jitter to prevent thundering herd
        $baseDelay = $this->backoffDelays[$index];
        $jitter = rand(0, $baseDelay * 0.1); // 10% jitter
        
        return $baseDelay + $jitter;
    }


    // Note: All old methods have been removed and replaced with service-based architecture.
    // Message handling is now delegated to MqttPayloadHandler service.
    // Connection management is handled by MqttConnectionService.
    // Utility functions are available through MqttUtilities trait in the services.
}
