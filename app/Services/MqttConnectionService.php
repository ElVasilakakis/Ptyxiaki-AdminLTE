<?php

namespace App\Services;

use App\Jobs\ProcessMqttMessage;
use App\Models\Device;
use App\Traits\MqttUtilities;
use Bluerhinos\phpMQTT;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MqttConnectionService
{
    use MqttUtilities;

    private array $mqttClients = [];
    private bool $interruptFlag = false;

    public function __construct()
    {
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
                Log::info('MQTT: Received interrupt signal, shutting down gracefully...');
                $this->interruptFlag = true;
                foreach ($this->mqttClients as $clientData) {
                    if ($clientData['type'] === 'php-mqtt') {
                        $clientData['client']->interrupt();
                    }
                }
            });
        }
    }

    /**
     * Connect to all MQTT brokers for the given devices.
     */
    public function connectToAllBrokers(Collection $devices, int $connectionTimeout, bool $skipProblematic, bool $skipTts = false): array
    {
        $brokerGroups = $this->groupDevicesByBroker($devices);
        $connectionResults = [];

        foreach ($brokerGroups as $brokerKey => $deviceGroup) {
            $firstDevice = $deviceGroup->first();
            $brokerType = $this->detectBrokerType($firstDevice);
            
            // Skip The Things Stack if flag is set
            if ($skipTts && $brokerType === 'thethings_stack') {
                Log::warning("MQTT: Skipping The Things Stack broker due to --skip-tts flag: {$firstDevice->mqtt_host}");
                $this->updateDevicesStatus($deviceGroup, 'skipped');
                $connectionResults[$brokerKey] = ['status' => 'skipped', 'reason' => 'skip_tts_flag'];
                continue;
            }
            
            // Skip known problematic brokers if flag is set
            if ($skipProblematic && $this->isProblematicBroker($firstDevice->mqtt_host)) {
                Log::warning("MQTT: Skipping problematic broker: {$firstDevice->mqtt_host}");
                $this->updateDevicesStatus($deviceGroup, 'skipped');
                $connectionResults[$brokerKey] = ['status' => 'skipped', 'reason' => 'problematic_broker'];
                continue;
            }
            
            $port = $this->getDevicePort($firstDevice);
            Log::info("MQTT: Processing connection: {$firstDevice->mqtt_host}:{$port} [{$brokerType}]");
            
            try {
                $this->connectWithTimeout($brokerKey, $deviceGroup, $firstDevice, $brokerType, $connectionTimeout);
                $connectionResults[$brokerKey] = ['status' => 'connected'];
                Log::info("MQTT: Successfully connected to {$firstDevice->mqtt_host}:{$port}");
            } catch (\Exception $e) {
                $this->handleBrokerConnectionError($firstDevice, $deviceGroup, $e);
                $connectionResults[$brokerKey] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }
        
        Log::info("MQTT: Successfully connected to " . count($this->mqttClients) . " broker(s)");
        return $connectionResults;
    }

    /**
     * Connect to a broker with strict timeout protection.
     */
    private function connectWithTimeout(string $brokerKey, Collection $devices, Device $firstDevice, string $brokerType, int $connectionTimeout): void
    {
        $port = $this->getDevicePort($firstDevice);
        $library = $this->selectMqttLibrary($firstDevice, $brokerType);
        
        Log::info("MQTT: Attempting connection to: {$firstDevice->mqtt_host}:{$port} using {$library} (timeout: {$connectionTimeout}s)");
        $this->logDeviceConfiguration($firstDevice);
        
        $startTime = time();
        
        // For The Things Stack, implement strict timeout to prevent blocking
        if ($brokerType === 'thethings_stack') {
            $this->connectWithStrictTimeout($brokerKey, $devices, $firstDevice, $brokerType, $connectionTimeout);
        } else {
            if ($library === 'php-mqtt') {
                $this->connectPhpMqtt($brokerKey, $devices, $firstDevice, $brokerType, $connectionTimeout);
            } else {
                $this->connectBluerhinos($brokerKey, $devices, $firstDevice, $brokerType);
            }
        }
        
        $connectionTime = time() - $startTime;
        Log::info("MQTT: Connection completed for {$firstDevice->mqtt_host}:{$port} in {$connectionTime}s");
    }

    /**
     * Connect to The Things Stack with strict timeout to prevent blocking.
     */
    private function connectWithStrictTimeout(string $brokerKey, Collection $devices, Device $firstDevice, string $brokerType, int $connectionTimeout): void
    {
        $timeoutSeconds = min($connectionTimeout, 8); // Maximum 8 seconds for TTS
        
        Log::info("MQTT: Using strict timeout ({$timeoutSeconds}s) for The Things Stack connection");
        
        // Skip TTS connection entirely if it's known to be problematic
        if ($this->isProblematicBroker($firstDevice->mqtt_host)) {
            Log::warning("MQTT: Skipping The Things Stack connection - known to cause blocking issues");
            $this->updateDevicesStatus($devices, 'skipped');
            return;
        }
        
        // Use alarm signal for hard timeout (Unix systems only)
        $connected = false;
        $exception = null;
        $startTime = time();
        
        // Set up signal handler for timeout
        if (function_exists('pcntl_signal') && function_exists('pcntl_alarm')) {
            pcntl_signal(SIGALRM, function() {
                throw new \Exception("Connection timeout - SIGALRM triggered");
            });
            
            pcntl_alarm($timeoutSeconds);
            
            try {
                Log::info("MQTT: Attempting TTS connection with SIGALRM timeout");
                $this->connectBluerhinos($brokerKey, $devices, $firstDevice, $brokerType);
                $connected = true;
                pcntl_alarm(0); // Cancel alarm
            } catch (\Exception $e) {
                pcntl_alarm(0); // Cancel alarm
                $exception = $e;
            }
        } else {
            // Fallback: Skip TTS entirely on systems without pcntl
            Log::warning("MQTT: PCNTL not available, skipping The Things Stack to prevent blocking");
            $this->updateDevicesStatus($devices, 'skipped');
            return;
        }
        
        $elapsedTime = time() - $startTime;
        
        if (!$connected) {
            $errorMsg = $exception ? $exception->getMessage() : "Connection failed after {$elapsedTime}s";
            Log::warning("MQTT: The Things Stack connection failed: {$errorMsg}");
            
            // Update devices to error status but don't throw exception
            $this->updateDevicesStatus($devices, 'error');
            return;
        }
        
        Log::info("MQTT: The Things Stack connected successfully in {$elapsedTime}s");
    }

    /**
     * Connect using Bluerhinos phpMQTT library.
     */
    private function connectBluerhinos(string $brokerKey, Collection $devices, Device $firstDevice, string $brokerType): void
    {
        Log::info("MQTT: Initializing BluerhiNos MQTT client for {$brokerType}");
        
        $clientId = $this->generateClientId($brokerType, $brokerKey);
        $port = $this->getDevicePort($firstDevice);
        $host = $firstDevice->mqtt_host;
        
        // Create phpMQTT instance
        $mqtt = new phpMQTT($host, $port, $clientId);
        
        // Set keepalive based on broker type
        $mqtt->keepalive = $this->getKeepaliveForBroker($firstDevice, $brokerType);

        Log::info("MQTT: Connecting to {$brokerType} at {$host}:{$port} (keepalive: {$mqtt->keepalive}s)");
        $startTime = microtime(true);
        
        // Handle authentication
        $username = $firstDevice->username;
        $password = $firstDevice->password;
        
        if ($username || $password) {
            Log::info("MQTT: Authenticating with username: " . ($username ?: 'anonymous'));
        }
        
        // For The Things Stack, implement connection timeout protection
        if ($brokerType === 'thethings_stack') {
            // Set socket timeout for The Things Stack connections
            $mqtt->socket_timeout = 10; // 10 seconds timeout
            
            // Use stream_context for SSL connections with timeout
            if ($firstDevice->use_ssl) {
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                    'socket' => [
                        'timeout' => 10,
                    ]
                ]);
                $mqtt->context = $context;
            }
        }
        
        // Connect with appropriate parameters
        $connected = $mqtt->connect(true, null, $username, $password);
        
        if (!$connected) {
            throw new \Exception("Failed to connect to {$brokerType}: Connection failed");
        }
        
        $endTime = microtime(true);
        $connectionTime = round(($endTime - $startTime) * 1000, 2);
        Log::info("MQTT: Connected to {$brokerType} successfully! ({$connectionTime}ms)");
        
        // Subscribe to topics
        $this->subscribeBluerhinos($mqtt, $devices, $brokerType);
        
        // Store client
        $this->mqttClients[$brokerKey] = [
            'client' => $mqtt,
            'type' => 'bluerhinos',
            'broker_type' => $brokerType,
            'devices' => $devices
        ];
    }

    /**
     * Connect using php-mqtt/client library.
     */
    private function connectPhpMqtt(string $brokerKey, Collection $devices, Device $firstDevice, string $brokerType, int $connectionTimeout): void
    {
        // For known problematic brokers, skip to prevent blocking
        if ($brokerType === 'thethings_stack') {
            Log::warning("MQTT: The Things Stack detected - this broker often has connection timeouts");
            throw new \Exception("Skipping The Things Stack to prevent timeout blocking");
        }

        Log::info("MQTT: Initializing php-mqtt/client for SSL connection to {$brokerType}");
        
        $clientId = $this->generateClientId($brokerType, $brokerKey);
        $port = $this->getDevicePort($firstDevice);
        $host = $firstDevice->mqtt_host;
        
        // Create connection settings
        $connectionSettings = $this->createConnectionSettings($firstDevice, $brokerType, $connectionTimeout);
        
        Log::info("MQTT: Connecting to SSL {$brokerType} at {$host}:{$port} (timeout: {$connectionTimeout}s)");
        $startTime = microtime(true);
        
        // Create MQTT client
        $mqtt = new MqttClient($host, $port, $clientId, MqttClient::MQTT_3_1_1);
        
        try {
            // Connect to broker with timeout
            $mqtt->connect($connectionSettings, true);
            
            $endTime = microtime(true);
            $connectionTime = round(($endTime - $startTime) * 1000, 2);
            Log::info("MQTT: Connected to SSL {$brokerType} successfully! ({$connectionTime}ms)");
            
            // Subscribe to topics
            $this->subscribePhpMqtt($mqtt, $devices, $brokerType);
            
            // Store client
            $this->mqttClients[$brokerKey] = [
                'client' => $mqtt,
                'type' => 'php-mqtt',
                'broker_type' => $brokerType,
                'devices' => $devices
            ];
            
        } catch (MqttClientException $e) {
            throw new \Exception("Failed to connect to SSL {$brokerType}: " . $e->getMessage());
        }
    }

    /**
     * Create connection settings for php-mqtt/client.
     */
    private function createConnectionSettings(Device $device, string $brokerType, int $connectionTimeout): ConnectionSettings
    {
        $connectionSettings = new ConnectionSettings();
        
        // Set basic authentication
        if ($device->username) {
            $connectionSettings->setUsername($device->username);
        }
        if ($device->password) {
            $connectionSettings->setPassword($device->password);
        }
        
        // Configure SSL/TLS
        $connectionSettings->setUseTls(true);
        
        // Set keepalive
        $keepalive = $this->getKeepaliveForBroker($device, $brokerType);
        $connectionSettings->setKeepAliveInterval($keepalive);
        
        // Configure SSL certificates
        $this->configureSslCertificates($connectionSettings, $brokerType);
        
        // Set connection timeout
        $connectionSettings->setConnectTimeout($connectionTimeout);
        
        return $connectionSettings;
    }

    /**
     * Configure SSL certificates for the connection.
     */
    private function configureSslCertificates(ConnectionSettings $connectionSettings, string $brokerType): void
    {
        $sslConfig = config('mqtt.ssl');
        $certPath = $sslConfig['certificates_path'];
        $clientCert = $certPath . '/client.crt';
        $clientKey = $certPath . '/client.key';
        $caCert = $certPath . '/ca.crt';
        
        if (file_exists($clientCert) && file_exists($clientKey)) {
            Log::info("MQTT: Using client certificate authentication");
            $connectionSettings->setTlsClientCertificateFile($clientCert);
            $connectionSettings->setTlsClientCertificateKeyFile($clientKey);
            
            if (file_exists($caCert)) {
                $connectionSettings->setTlsCaCertificateFile($caCert);
                $connectionSettings->setTlsVerifyPeer(true);
                $connectionSettings->setTlsVerifyPeerName(true);
                Log::info("MQTT: Using CA certificate for verification");
            } else {
                $connectionSettings->setTlsVerifyPeer($sslConfig['verify_peer']);
                $connectionSettings->setTlsVerifyPeerName($sslConfig['verify_peer_name']);
                Log::info("MQTT: No CA certificate, using configured verification settings");
            }
            $connectionSettings->setTlsSelfSignedAllowed($sslConfig['allow_self_signed']);
        } else {
            // No certificates available - use configured settings
            if ($brokerType === 'hivemq') {
                $brokerConfig = $this->getBrokerConfig($brokerType);
                if ($brokerConfig['requires_certificates'] ?? false) {
                    Log::warning("MQTT: HiveMQ Cloud detected but no client certificates found");
                    Log::warning("MQTT: This connection will likely fail - place certificates in {$certPath}");
                }
            }
            
            $connectionSettings->setTlsVerifyPeer($sslConfig['verify_peer']);
            $connectionSettings->setTlsVerifyPeerName($sslConfig['verify_peer_name']);
            $connectionSettings->setTlsSelfSignedAllowed($sslConfig['allow_self_signed']);
        }
    }

    /**
     * Subscribe to topics using Bluerhinos library.
     */
    private function subscribeBluerhinos(phpMQTT $mqtt, Collection $devices, string $brokerType): void
    {
        Log::info("MQTT: Starting BluerhiNos topic subscriptions for {$brokerType}");
        
        // Collect all topics for this broker with device-specific callbacks
        $topics = [];
        foreach ($devices as $device) {
            foreach ($device->mqtt_topics as $topic) {
                // Create a closure that captures the specific device collection for this broker
                $brokerDevices = $devices; // This collection is already filtered for this broker
                $topics[$topic] = [
                    'qos' => 0,
                    'function' => function($receivedTopic, $message) use ($brokerDevices, $brokerType) {
                        Log::debug("MQTT: [{$brokerType}] Received message on topic: {$receivedTopic}");
                        $this->dispatchMessageJob($brokerDevices, $receivedTopic, $message);
                    }
                ];
                Log::info("MQTT: Added {$brokerType} topic for subscription: {$topic} (device: {$device->name})");
            }
        }
        
        if (!empty($topics)) {
            Log::info("MQTT: Subscribing to " . count($topics) . " {$brokerType} topics for " . $devices->count() . " devices");
            $mqtt->subscribe($topics, 0);
            Log::info("MQTT: Subscribed to " . count($topics) . " {$brokerType} topics successfully");
            
            // Update all devices status to online
            $this->updateDevicesStatus($devices, 'online');
        }
    }

    /**
     * Subscribe to topics using php-mqtt/client library.
     */
    private function subscribePhpMqtt(MqttClient $mqtt, Collection $devices, string $brokerType): void
    {
        Log::info("MQTT: Starting php-mqtt topic subscriptions for SSL {$brokerType}");
        
        $topicCount = 0;
        foreach ($devices as $device) {
            foreach ($device->mqtt_topics as $topic) {
                // Create a closure that captures the specific device collection for this broker
                $brokerDevices = $devices; // This collection is already filtered for this broker
                $mqtt->subscribe($topic, function ($receivedTopic, $message, $retained, $matchedWildcards) use ($brokerDevices, $brokerType) {
                    Log::debug("MQTT: [SSL {$brokerType}] Received message on topic: {$receivedTopic}");
                    $this->dispatchMessageJob($brokerDevices, $receivedTopic, $message);
                }, 0);
                
                Log::info("MQTT: Added SSL {$brokerType} topic for subscription: {$topic} (device: {$device->name})");
                $topicCount++;
            }
        }
        
        if ($topicCount > 0) {
            Log::info("MQTT: Subscribed to {$topicCount} SSL {$brokerType} topics for " . $devices->count() . " devices successfully");
            
            // Update all devices status to online
            $this->updateDevicesStatus($devices, 'online');
        }
    }

    /**
     * Process messages from all connected clients.
     */
    public function processMessages(): void
    {
        foreach ($this->mqttClients as $brokerKey => $clientData) {
            try {
                if ($clientData['type'] === 'bluerhinos') {
                    $clientData['client']->proc();
                } else if ($clientData['type'] === 'php-mqtt') {
                    $clientData['client']->loop(false, true);
                }
            } catch (\Exception $e) {
                Log::warning("MQTT: Loop error for {$brokerKey}: " . $e->getMessage());
                $this->attemptReconnection($brokerKey, $clientData);
            }
        }
    }

    /**
     * Attempt to reconnect to a failed broker.
     */
    private function attemptReconnection(string $brokerKey, array $clientData): void
    {
        Log::info("MQTT: Attempting to reconnect to {$brokerKey}");
        
        try {
            $devices = $clientData['devices'];
            $firstDevice = $devices->first();
            $brokerType = $clientData['broker_type'];
            
            // Remove failed client
            unset($this->mqttClients[$brokerKey]);
            
            // Attempt reconnection with shorter timeout
            $this->connectWithTimeout($brokerKey, $devices, $firstDevice, $brokerType, 3);
            
            Log::info("MQTT: Reconnected to {$brokerKey} successfully");
            
        } catch (\Exception $e) {
            Log::error("MQTT: Reconnection failed for {$brokerKey}: " . $e->getMessage());
            
            // Update devices to error status
            $this->updateDevicesStatus($clientData['devices'], 'error');
        }
    }

    /**
     * Disconnect from all brokers.
     */
    public function disconnectAll(): void
    {
        foreach ($this->mqttClients as $brokerKey => $clientData) {
            try {
                if ($clientData['type'] === 'bluerhinos') {
                    $clientData['client']->close();
                } else if ($clientData['type'] === 'php-mqtt') {
                    $clientData['client']->disconnect();
                }
                Log::info("MQTT: Disconnected from broker: {$brokerKey}");
            } catch (\Exception $e) {
                Log::warning("MQTT: Error disconnecting from {$brokerKey}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if interrupt flag is set.
     */
    public function isInterrupted(): bool
    {
        return $this->interruptFlag;
    }

    /**
     * Get connected clients count.
     */
    public function getConnectedClientsCount(): int
    {
        return count($this->mqttClients);
    }

    /**
     * Group devices by broker configuration.
     */
    private function groupDevicesByBroker(Collection $devices): Collection
    {
        return $devices->groupBy(function ($device) {
            $port = $this->getDevicePort($device);
            $brokerType = $this->detectBrokerType($device);
            return $device->mqtt_host . ':' . $port . ':' . ($device->username ?: 'anonymous') . ':' . $brokerType;
        });
    }

    /**
     * Update status for multiple devices.
     */
    private function updateDevicesStatus(Collection $devices, string $status): void
    {
        foreach ($devices as $device) {
            $device->update([
                'status' => $status,
                'last_seen_at' => now()
            ]);
            Log::info("MQTT: Device {$device->name} status updated to {$status}");
        }
    }

    /**
     * Log device configuration details.
     */
    private function logDeviceConfiguration(Device $device): void
    {
        $port = $this->getDevicePort($device);
        Log::info("MQTT: Device Configuration:", [
            'host' => $device->mqtt_host,
            'port' => $port . ($device->port ? ' (custom)' : ' (default)'),
            'use_ssl' => $device->use_ssl ? 'Yes' : 'No',
            'username' => $device->username ?: 'None',
            'password' => $device->password ? 'Set (length: ' . strlen($device->password) . ')' : 'None',
            'keepalive' => $device->keepalive ?: config('mqtt.default_keepalive', 60)
        ]);
    }

    /**
     * Handle broker connection errors.
     */
    private function handleBrokerConnectionError(Device $firstDevice, Collection $devices, \Exception $e): void
    {
        $port = $this->getDevicePort($firstDevice);
        Log::error("MQTT: Broker connection failed: {$firstDevice->mqtt_host}:{$port}", [
            'error' => $e->getMessage(),
            'device_count' => $devices->count()
        ]);
        
        // Update device status to error for all devices on this broker
        $this->updateDevicesStatus($devices, 'error');
    }

    /**
     * Dispatch message processing job for non-blocking handling.
     */
    private function dispatchMessageJob(Collection $devices, string $receivedTopic, string $message): void
    {
        // Find the device that matches this message topic
        $matchedDevice = null;
        foreach ($devices as $device) {
            foreach ($device->mqtt_topics as $deviceTopic) {
                if ($this->topicMatches($deviceTopic, $receivedTopic)) {
                    $matchedDevice = $device;
                    break 2;
                }
            }
        }

        if ($matchedDevice) {
            Log::debug("MQTT: Dispatching job for device {$matchedDevice->name} on topic {$receivedTopic}");
            
            // Dispatch the job with appropriate queue configuration
            $queueConnection = config('mqtt.queue.connection', config('queue.default'));
            ProcessMqttMessage::dispatch($matchedDevice, $receivedTopic, $message)
                ->onConnection($queueConnection);
        } else {
            Log::warning("MQTT: Received message on unmatched topic: {$receivedTopic}");
        }
    }
}
