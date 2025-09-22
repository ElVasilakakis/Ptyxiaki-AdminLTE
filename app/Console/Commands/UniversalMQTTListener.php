<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\Sensor;
use Bluerhinos\phpMQTT;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;

class UniversalMQTTListener extends Command
{
    protected $signature = 'mqtt:listen-all {--timeout=0} {--connection-timeout=5} {--skip-problematic}';
    protected $description = 'Listen to MQTT topics for all devices with MQTT connection type (hybrid SSL/non-SSL support)';

    private $mqttClients = [];
    private $devices = [];
    private $interruptFlag = false;

    public function handle()
    {
        $timeout = (int) $this->option('timeout');
        $connectionTimeout = (int) $this->option('connection-timeout');
        $skipProblematic = $this->option('skip-problematic');

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
            $brokerType = $this->detectBrokerType($device);
            $library = $device->use_ssl ? 'php-mqtt/client' : 'bluerhinos';
            $port = $this->getDevicePort($device); // Use actual device port
            $this->info("- {$device->name} ({$device->device_id}) - {$device->mqtt_host}:{$port} [{$brokerType}] -> {$library}");
        }

        // Set up signal handler for graceful shutdown
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () {
                $this->info("\n🛑 Received interrupt signal, shutting down gracefully...");
                $this->interruptFlag = true;
                foreach ($this->mqttClients as $clientData) {
                    if ($clientData['type'] === 'php-mqtt') {
                        $clientData['client']->interrupt();
                    }
                }
            });
        }

        try {
            // Connect to all MQTT brokers using hybrid approach with timeout protection
            $this->connectToAllBrokersNonBlocking($connectionTimeout, $skipProblematic);

            if (empty($this->mqttClients)) {
                $this->warn("⚠️ No successful connections established. Exiting.");
                return 1;
            }

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

    /**
     * Get the actual port for a device, respecting custom port configurations
     */
    private function getDevicePort(Device $device): int
    {
        // Use the device's configured port if available
        if ($device->port && is_numeric($device->port)) {
            return (int) $device->port;
        }
        
        // Fall back to standard ports based on SSL setting only if no port is configured
        return $device->use_ssl ? 8883 : 1883;
    }

    private function connectToAllBrokersNonBlocking($connectionTimeout, $skipProblematic)
    {
        $brokerGroups = $this->groupDevicesByBroker();
        $knownProblematicBrokers = [
            'eu1.cloud.thethings.industries' // Known to have connection issues
        ];

        foreach ($brokerGroups as $brokerKey => $devices) {
            $firstDevice = $devices->first();
            $brokerType = $this->detectBrokerType($firstDevice);
            
            // Skip known problematic brokers if flag is set
            if ($skipProblematic && in_array($firstDevice->mqtt_host, $knownProblematicBrokers)) {
                $this->warn("⚠️ Skipping problematic broker: {$firstDevice->mqtt_host} (use --skip-problematic=false to attempt)");
                $this->updateDevicesStatus($devices, 'skipped');
                continue;
            }
            
            $port = $this->getDevicePort($firstDevice);
            $this->info("🚀 Processing connection: {$firstDevice->mqtt_host}:{$port}");
            $this->info("📋 Broker Type: {$brokerType} | SSL: " . ($firstDevice->use_ssl ? 'Yes' : 'No'));
            
            // Attempt connection with strict timeout
            $this->connectWithStrictTimeout($brokerKey, $devices, $firstDevice, $brokerType, $connectionTimeout);
        }
        
        $this->info("🎯 Successfully connected to " . count($this->mqttClients) . " broker(s)");
    }

    private function connectWithStrictTimeout($brokerKey, $devices, $firstDevice, $brokerType, $connectionTimeout)
    {
        try {
            $port = $this->getDevicePort($firstDevice);
            $this->info("🔌 Attempting connection to: {$firstDevice->mqtt_host}:{$port} (timeout: {$connectionTimeout}s)");
            $this->logDeviceConfiguration($firstDevice);
            
            $startTime = time();
            
            // Choose library based on SSL requirement
            if ($firstDevice->use_ssl) {
                $this->connectPhpMqttWithStrictTimeout($brokerKey, $devices, $firstDevice, $brokerType, $connectionTimeout);
            } else {
                $this->connectBluerhinos($brokerKey, $devices, $firstDevice, $brokerType);
            }
            
            $connectionTime = time() - $startTime;
            $this->info("✅ Connection completed for {$firstDevice->mqtt_host}:{$port} in {$connectionTime}s");

        } catch (\Exception $e) {
            $this->handleBrokerConnectionError($firstDevice, $devices, $e);
            
            // Always continue with remaining connections
            $this->info("➡️ Continuing with remaining connections...");
        }
    }

    private function detectBrokerType(Device $device): string
    {
        // Check if explicitly set in device configuration
        if ($device->connection_broker) {
            return strtolower($device->connection_broker);
        }

        // Auto-detect based on hostname
        $host = strtolower($device->mqtt_host);
        
        if (str_contains($host, 'thethings') || str_contains($host, 'ttn')) {
            return 'thethings_stack';
        }
        
        if (str_contains($host, 'hivemq')) {
            return 'hivemq';
        }
        
        if (str_contains($host, 'emqx')) {
            return 'emqx';
        }

        // Default fallback
        return 'emqx';
    }

    private function connectBluerhinos(string $brokerKey, $devices, Device $firstDevice, string $brokerType)
    {
        $this->info("🔥 Initializing BluerhiNos MQTT client for {$brokerType}...");
        
        $clientId = 'laravel_' . strtolower($brokerType) . '_' . time() . '_' . substr(md5($brokerKey), 0, 8);
        $port = $this->getDevicePort($firstDevice); // Use actual device port
        $host = $firstDevice->mqtt_host;
        
        $this->info("🏗️ Creating BluerhiNos MQTT client with ID: {$clientId}");
        $this->info("🔗 Host: {$host}, Port: {$port}");
        
        // Create phpMQTT instance
        $mqtt = new phpMQTT($host, $port, $clientId);
        
        // Set keepalive based on broker type
        switch ($brokerType) {
            case 'thethings_stack':
                $mqtt->keepalive = min($firstDevice->keepalive ?: 10, 10);
                break;
            case 'hivemq':
            case 'emqx':
            default:
                $mqtt->keepalive = $firstDevice->keepalive ?: 60;
        }

        $this->info("⏳ Connecting to {$brokerType} at {$host}:{$port} (keepalive: {$mqtt->keepalive}s)");
        $startTime = microtime(true);
        
        // Handle authentication
        $username = $firstDevice->username;
        $password = $firstDevice->password;
        
        if ($username || $password) {
            $this->info("🔐 Authenticating with username: " . ($username ?: 'anonymous'));
        } else {
            $this->info("🔓 Connecting without authentication");
        }
        
        // Connect with appropriate parameters
        $connected = $mqtt->connect(true, NULL, $username, $password);
        
        if (!$connected) {
            throw new \Exception("Failed to connect to {$brokerType}: Connection failed");
        }
        
        $endTime = microtime(true);
        $connectionTime = round(($endTime - $startTime) * 1000, 2);
        $this->info("✅ Connected to {$brokerType} successfully! ({$connectionTime}ms)");
        
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

    private function connectPhpMqttWithStrictTimeout(string $brokerKey, $devices, Device $firstDevice, string $brokerType, int $connectionTimeout)
    {
        // For known problematic brokers, try HiveMQ first and skip TTS
        if ($brokerType === 'thethings_stack') {
            $this->warn("⚠️ The Things Stack detected - this broker often has connection timeouts");
            $this->warn("⚠️ Skipping to prevent blocking other connections");
            throw new \Exception("Skipping The Things Stack to prevent timeout blocking");
        }

        $this->info("🔥 Initializing php-mqtt/client for SSL connection to {$brokerType}...");
        
        $clientId = 'laravel_ssl_' . strtolower($brokerType) . '_' . time() . '_' . substr(md5($brokerKey), 0, 8);
        $port = $this->getDevicePort($firstDevice); // Use actual device port
        $host = $firstDevice->mqtt_host;
        
        $this->info("🏗️ Creating php-mqtt client with ID: {$clientId}");
        $this->info("🔗 Host: {$host}, Port: {$port}, SSL: Yes, Timeout: {$connectionTimeout}s");
        
        // Create connection settings with aggressive timeouts
        $connectionSettings = new ConnectionSettings();
        
        // Set basic authentication
        if ($firstDevice->username) {
            $connectionSettings->setUsername($firstDevice->username);
        }
        if ($firstDevice->password) {
            $connectionSettings->setPassword($firstDevice->password);
        }
        
        // Configure SSL/TLS
        $connectionSettings->setUseTls(true);
        
        // Set keepalive
        $keepalive = $firstDevice->keepalive ?: 60;
        if ($brokerType === 'thethings_stack') {
            $keepalive = min($keepalive, 30);
        }
        $connectionSettings->setKeepAliveInterval($keepalive);
        
        // Check for client certificates (especially for HiveMQ Cloud)
        $certPath = storage_path('certificates');
        $clientCert = $certPath . '/client.crt';
        $clientKey = $certPath . '/client.key';
        $caCert = $certPath . '/ca.crt';
        
        if (file_exists($clientCert) && file_exists($clientKey)) {
            $this->info("🔐 Using client certificate authentication");
            $connectionSettings->setTlsClientCertificateFile($clientCert);
            $connectionSettings->setTlsClientCertificateKeyFile($clientKey);
            
            if (file_exists($caCert)) {
                $connectionSettings->setTlsCaCertificateFile($caCert);
                $connectionSettings->setTlsVerifyPeer(true);
                $connectionSettings->setTlsVerifyPeerName(true);
                $this->info("   - Using CA certificate for verification");
            } else {
                $connectionSettings->setTlsVerifyPeer(false);
                $connectionSettings->setTlsVerifyPeerName(false);
                $this->info("   - No CA certificate, disabling peer verification");
            }
            $connectionSettings->setTlsSelfSignedAllowed(true);
        } else {
            // No certificates available - use permissive settings
            if ($brokerType === 'hivemq') {
                $this->warn("⚠️ HiveMQ Cloud detected but no client certificates found");
                $this->warn("   - This connection will likely fail");
                $this->warn("   - HiveMQ Cloud typically requires client certificates");
                $this->warn("   - Place certificates in storage/certificates/ directory");
            }
            
            $connectionSettings->setTlsVerifyPeer(false);
            $connectionSettings->setTlsVerifyPeerName(false);
            $connectionSettings->setTlsSelfSignedAllowed(true);
        }
        
        // Set aggressive connection timeout
        $connectionSettings->setConnectTimeout($connectionTimeout);
        
        $this->info("⏳ Connecting to SSL {$brokerType} at {$host}:{$port} (keepalive: {$keepalive}s, timeout: {$connectionTimeout}s)");
        $startTime = microtime(true);
        
        // Create MQTT client
        $mqtt = new MqttClient($host, $port, $clientId, MqttClient::MQTT_3_1_1);
        
        try {
            // Connect to broker with timeout
            $mqtt->connect($connectionSettings, true);
            
            $endTime = microtime(true);
            $connectionTime = round(($endTime - $startTime) * 1000, 2);
            $this->info("✅ Connected to SSL {$brokerType} successfully! ({$connectionTime}ms)");
            
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

    private function subscribeBluerhinos($mqtt, $devices, string $brokerType)
    {
        $this->info("📡 Starting BluerhiNos topic subscriptions for {$brokerType}...");
        
        // Collect all topics for this broker
        $topics = [];
        foreach ($devices as $device) {
            foreach ($device->mqtt_topics as $topic) {
                $topics[$topic] = [
                    'qos' => 0,
                    'function' => function($receivedTopic, $message) use ($devices) {
                        $this->handleMqttMessage($devices, $receivedTopic, $message);
                    }
                ];
                $this->info("📋 Added {$brokerType} topic for subscription: {$topic}");
            }
        }
        
        if (!empty($topics)) {
            $this->info("🔔 Subscribing to " . count($topics) . " {$brokerType} topics...");
            $mqtt->subscribe($topics, 0);
            $this->info("✅ Subscribed to " . count($topics) . " {$brokerType} topics successfully");
            
            // Update all devices status to online
            $this->updateDevicesStatus($devices, 'online');
        }
    }

    private function subscribePhpMqtt($mqtt, $devices, string $brokerType)
    {
        $this->info("📡 Starting php-mqtt topic subscriptions for SSL {$brokerType}...");
        
        $topicCount = 0;
        foreach ($devices as $device) {
            foreach ($device->mqtt_topics as $topic) {
                $mqtt->subscribe($topic, function ($receivedTopic, $message, $retained, $matchedWildcards) use ($devices) {
                    $this->handleMqttMessage($devices, $receivedTopic, $message);
                }, 0);
                
                $this->info("📋 Added SSL {$brokerType} topic for subscription: {$topic}");
                $topicCount++;
            }
        }
        
        if ($topicCount > 0) {
            $this->info("✅ Subscribed to {$topicCount} SSL {$brokerType} topics successfully");
            
            // Update all devices status to online
            $this->updateDevicesStatus($devices, 'online');
        }
    }

    private function updateDevicesStatus($devices, string $status)
    {
        foreach ($devices as $device) {
            $device->update([
                'status' => $status,
                'last_seen_at' => now()
            ]);
            $this->info("   📊 Device {$device->name} status updated to {$status}");
        }
    }

    private function runWithTimeout($timeout)
    {
        $startTime = time();
        while ((time() - $startTime) < $timeout && !$this->interruptFlag) {
            $this->processMessages();
            usleep(100000); // Sleep 100ms between loops
        }
    }

    private function runIndefinitely()
    {
        while (!$this->interruptFlag) {
            $this->processMessages();
            usleep(100000); // Sleep 100ms between loops
        }
    }

    private function processMessages()
    {
        foreach ($this->mqttClients as $brokerKey => $clientData) {
            try {
                if ($clientData['type'] === 'bluerhinos') {
                    $clientData['client']->proc();
                } else if ($clientData['type'] === 'php-mqtt') {
                    $clientData['client']->loop(false, true);
                }
            } catch (\Exception $e) {
                $this->warn("Loop error for {$brokerKey}: " . $e->getMessage());
                $this->attemptReconnection($brokerKey, $clientData);
            }
        }
    }

    private function attemptReconnection($brokerKey, $clientData)
    {
        $this->info("🔄 Attempting to reconnect to {$brokerKey}...");
        
        try {
            $devices = $clientData['devices'];
            $firstDevice = $devices->first();
            $brokerType = $clientData['broker_type'];
            
            // Remove failed client
            unset($this->mqttClients[$brokerKey]);
            
            // Attempt reconnection with appropriate library and shorter timeout
            if ($firstDevice->use_ssl) {
                $this->connectPhpMqttWithStrictTimeout($brokerKey, $devices, $firstDevice, $brokerType, 3);
            } else {
                $this->connectBluerhinos($brokerKey, $devices, $firstDevice, $brokerType);
            }
            
            $this->info("✅ Reconnected to {$brokerKey} successfully");
            
        } catch (\Exception $e) {
            $this->error("❌ Reconnection failed for {$brokerKey}: " . $e->getMessage());
            
            // Update devices to error status
            $this->updateDevicesStatus($clientData['devices'], 'error');
        }
    }

    private function disconnectAll()
    {
        foreach ($this->mqttClients as $brokerKey => $clientData) {
            try {
                if ($clientData['type'] === 'bluerhinos') {
                    $clientData['client']->close();
                } else if ($clientData['type'] === 'php-mqtt') {
                    $clientData['client']->disconnect();
                }
                $this->info("Disconnected from broker: {$brokerKey}");
            } catch (\Exception $e) {
                $this->warn("Error disconnecting from {$brokerKey}: " . $e->getMessage());
            }
        }
    }

    private function logDeviceConfiguration(Device $firstDevice)
    {
        $port = $this->getDevicePort($firstDevice);
        $this->info("📋 Device Configuration:");
        $this->info("   - Host: {$firstDevice->mqtt_host}");
        $this->info("   - Port: {$port}" . ($firstDevice->port ? " (custom)" : " (default)"));
        $this->info("   - Use SSL: " . ($firstDevice->use_ssl ? 'Yes' : 'No'));
        $this->info("   - Username: " . ($firstDevice->username ?: 'None'));
        $this->info("   - Password: " . ($firstDevice->password ? 'Set (length: ' . strlen($firstDevice->password) . ')' : 'None'));
        $this->info("   - Keep Alive: " . ($firstDevice->keepalive ?: 60));
    }

    private function handleBrokerConnectionError(Device $firstDevice, $devices, \Exception $e)
    {
        $port = $this->getDevicePort($firstDevice);
        $this->error("💥 Broker connection failed: {$firstDevice->mqtt_host}:{$port}");
        $this->error("💥 Error: " . $e->getMessage());
        
        // Update device status to error for all devices on this broker
        $this->updateDevicesStatus($devices, 'error');
    }

    private function groupDevicesByBroker()
    {
        return $this->devices->groupBy(function ($device) {
            $port = $this->getDevicePort($device);
            return $device->mqtt_host . ':' . $port . ':' . ($device->username ?: 'anonymous');
        });
    }

    // [Rest of the message handling methods remain the same...]

    private function handleMqttMessage($devices, $topic, $message)
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
            $this->processMqttMessage($matchedDevice, $topic, $message);
        } else {
            $this->warn("⚠️ Received message on unmatched topic: {$topic}");
        }
    }

    private function topicMatches($pattern, $topic)
    {
        // Convert MQTT wildcards to regex
        $pattern = str_replace(['+', '#'], ['[^/]+', '.*'], $pattern);
        $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';
        
        return preg_match($pattern, $topic);
    }

    private function processMqttMessage(Device $device, string $topic, string $message)
    {
        $this->info("[{$device->name}] Received message on topic '{$topic}': " . substr($message, 0, 200) . (strlen($message) > 200 ? '...' : ''));

        try {
            // Try to decode JSON message
            $data = json_decode($message, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn("[{$device->name}] Message is not valid JSON, treating as plain text");
                $topicParts = explode('/', $topic);
                $sensorType = end($topicParts);
                $this->createOrUpdateSensor($device, $sensorType, $message, null, $topic);
                return;
            }

            $this->handleDevicePayload($device, $data, $topic);

            $device->update([
                'status' => 'online',
                'last_seen_at' => now()
            ]);

        } catch (\Exception $e) {
            $this->error("[{$device->name}] Error processing message: " . $e->getMessage());
        }
    }

    private function handleDevicePayload(Device $device, array $data, string $topic)
    {
        $brokerType = $device->connection_broker ?? $this->detectBrokerType($device);
        $this->info("[{$device->name}] Processing payload for broker type: {$brokerType}");
        
        switch (strtolower($brokerType)) {
            case 'the_things_stack':
            case 'thethings_stack':
            case 'ttn':
            case 'lorawan':
                $this->handleTheThingsStackPayload($device, $data, $topic);
                break;
                
            case 'hivemq':
            case 'hivemq_cloud':
                if (isset($data['sensors']) && is_array($data['sensors'])) {
                    $this->handleESP32Payload($device, $data, $topic);
                } else {
                    $this->handleSimplePayload($device, $data, $topic);
                }
                break;
                
            case 'emqx':
            case 'esp32':
            default:
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
        
        $decodedPayload = null;
        if (isset($data['uplink_message']['decoded_payload']['data'])) {
            $decodedPayload = $data['uplink_message']['decoded_payload']['data'];
        } elseif (isset($data['uplink_message']['decoded_payload'])) {
            $decodedPayload = $data['uplink_message']['decoded_payload'];
        } elseif (isset($data['decoded_payload'])) {
            $decodedPayload = $data['decoded_payload'];
        }

        if (!$decodedPayload) {
            $this->warn("[{$device->name}] No decoded payload found");
            return;
        }

        foreach ($decodedPayload as $key => $value) {
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
                
                if ($sensorData['type'] === 'geolocation' && isset($sensorData['subtype'])) {
                    $sensorType = $sensorData['subtype'];
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
        
        if (isset($data['sensor_type']) && isset($data['value'])) {
            $this->createOrUpdateSensor($device, $data['sensor_type'], $data['value'], $data['unit'] ?? null, $topic);
            return;
        }

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), ['timestamp', 'device_id', 'message_id'])) {
                continue;
            }
            
            $sensorType = $this->normalizeSensorType($key);
            $unit = $this->getUnitForSensorType($sensorType);
            $this->createOrUpdateSensor($device, $sensorType, $value, $unit, $topic);
        }
    }

    private function createOrUpdateSensor(Device $device, string $sensorType, $value, ?string $unit, string $topic)
    {
        $cleanValue = $this->extractNumericValue($value);
        
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

        $sensor->updateReading($cleanValue, now());
        
        if ($unit && $sensor->unit !== $unit) {
            $sensor->update(['unit' => $unit]);
        }

        $this->info("[{$device->name}] Updated sensor '{$sensorType}' with value: {$cleanValue}" . ($unit ? " {$unit}" : ""));
    }

    private function normalizeSensorType(string $key): string
    {
        $key = strtolower($key);
        
        $mappings = [
            'temp' => 'temperature',
            'temperature' => 'temperature',
            'thermal' => 'temperature',
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
            'gps_fix' => 'gps_quality',
            'gps_quality' => 'gps_quality',
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
            'gps_quality' => 'fix_code',
        ];

        return $units[$sensorType] ?? null;
    }

    private function extractNumericValue($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

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
