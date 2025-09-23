<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Services\MqttConnectionService;
use App\Services\MqttPayloadHandler;
use Illuminate\Support\Facades\Log;

class UniversalMQTTListenerRefactored extends Command
{
    protected $signature = 'mqtt:listen-all-refactored {--timeout=0} {--connection-timeout=5} {--skip-problematic}';
    protected $description = 'Refactored MQTT listener with modular architecture for all devices with MQTT connection type';

    private MqttConnectionService $connectionService;
    private MqttPayloadHandler $payloadHandler;

    public function __construct(MqttConnectionService $connectionService, MqttPayloadHandler $payloadHandler)
    {
        parent::__construct();
        $this->connectionService = $connectionService;
        $this->payloadHandler = $payloadHandler;
    }

    public function handle(): int
    {
        $timeout = (int) $this->option('timeout');
        $connectionTimeout = (int) $this->option('connection-timeout');
        $skipProblematic = $this->option('skip-problematic');

        Log::info('MQTT: Starting Universal MQTT Listener (Refactored)');

        try {
            // Get all MQTT devices
            $devices = $this->getActiveDevices();
            
            if ($devices->isEmpty()) {
                $this->error("No active MQTT devices found.");
                Log::warning('MQTT: No active MQTT devices found');
                return 1;
            }

            $this->displayDeviceInfo($devices);

            // Connect to all MQTT brokers
            $connectionResults = $this->connectionService->connectToAllBrokers(
                $devices, 
                $connectionTimeout, 
                $skipProblematic
            );

            if ($this->connectionService->getConnectedClientsCount() === 0) {
                $this->warn("⚠️ No successful connections established. Exiting.");
                Log::warning('MQTT: No successful connections established');
                return 1;
            }

            $this->displayConnectionResults($connectionResults);
            $this->info("Listening for messages from all devices... (Press Ctrl+C to stop)");
            Log::info('MQTT: Started listening for messages from all connected devices');

            // Keep the script running
            if ($timeout > 0) {
                $this->runWithTimeout($timeout);
            } else {
                $this->runIndefinitely();
            }

        } catch (\Exception $e) {
            $this->error("Universal MQTT Listener Error: " . $e->getMessage());
            Log::error('MQTT: Universal MQTT Listener Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        } finally {
            $this->connectionService->disconnectAll();
            Log::info('MQTT: Universal MQTT Listener stopped');
        }

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
            $brokerType = app(MqttConnectionService::class)->detectBrokerType($device);
            $library = app(MqttConnectionService::class)->selectMqttLibrary($device, $brokerType);
            $port = app(MqttConnectionService::class)->getDevicePort($device);
            
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

    /**
     * Run the listener with a timeout.
     */
    private function runWithTimeout(int $timeout): void
    {
        $startTime = time();
        $sleepMicroseconds = config('mqtt.message_processing_sleep', 100000);
        
        Log::info("MQTT: Running with timeout of {$timeout} seconds");
        
        while ((time() - $startTime) < $timeout && !$this->connectionService->isInterrupted()) {
            $this->connectionService->processMessages();
            usleep($sleepMicroseconds);
        }
        
        if ((time() - $startTime) >= $timeout) {
            $this->info("⏰ Timeout reached, shutting down...");
            Log::info('MQTT: Timeout reached, shutting down');
        }
    }

    /**
     * Run the listener indefinitely.
     */
    private function runIndefinitely(): void
    {
        $sleepMicroseconds = config('mqtt.message_processing_sleep', 100000);
        
        Log::info('MQTT: Running indefinitely until interrupted');
        
        while (!$this->connectionService->isInterrupted()) {
            $this->connectionService->processMessages();
            usleep($sleepMicroseconds);
        }
        
        $this->info("🛑 Interrupt received, shutting down gracefully...");
        Log::info('MQTT: Interrupt received, shutting down gracefully');
    }

    /**
     * Display runtime statistics.
     */
    private function displayRuntimeStats(): void
    {
        $connectedClients = $this->connectionService->getConnectedClientsCount();
        $this->info("📊 Runtime Stats: {$connectedClients} active connections");
    }

    /**
     * Handle command interruption gracefully.
     */
    public function handleInterruption(): void
    {
        $this->info("\n🛑 Graceful shutdown initiated...");
        Log::info('MQTT: Graceful shutdown initiated');
        
        try {
            $this->connectionService->disconnectAll();
            $this->info("✅ All connections closed successfully");
            Log::info('MQTT: All connections closed successfully');
        } catch (\Exception $e) {
            $this->error("❌ Error during shutdown: " . $e->getMessage());
            Log::error('MQTT: Error during shutdown', ['error' => $e->getMessage()]);
        }
    }
}
