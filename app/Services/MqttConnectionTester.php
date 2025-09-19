<?php

namespace App\Services;

use App\Models\MqttBroker;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;
use Exception;

class MqttConnectionTester
{
    /**
     * Test MQTT broker connection with proper MQTT protocol
     */
    public function testConnection(MqttBroker $broker): array
    {
        try {
            // Create unique client ID for testing
            $clientId = 'test_' . uniqid();

            // Determine connection settings
            $host = $broker->host;
            $port = $this->getConnectionPort($broker);
            $useTLS = $broker->use_ssl;
            $timeout = $broker->timeout ?? 10;

            Log::info('Testing MQTT connection', [
                'broker_id' => $broker->id,
                'host' => $host,
                'port' => $port,
                'use_tls' => $useTLS,
                'client_id' => $clientId
            ]);

            // Create MQTT client
            $mqttClient = new MqttClient($host, $port, $clientId);

            // Configure connection settings
            $connectionSettings = (new ConnectionSettings())
                ->setUseTls($useTLS)
                ->setKeepAliveInterval(60)
                ->setConnectTimeout($timeout)
                ->setSocketTimeout($timeout);

            // Add TLS settings for better compatibility
            if ($useTLS) {
                $connectionSettings = $connectionSettings
                    ->setTlsVerifyPeer(false)
                    ->setTlsVerifyPeerName(false)
                    ->setTlsSelfSignedAllowed(true);
            }

            // Add authentication if provided
            if ($broker->username && $broker->password) {
                $connectionSettings->setUsername($broker->username);
                $connectionSettings->setPassword($broker->password);
            }

            // Attempt to connect
            $mqttClient->connect($connectionSettings, true);

            // Test publish/subscribe to verify full functionality
            $testTopic = 'test/' . $clientId;
            $testMessage = 'connection_test_' . time();
            $messageReceived = false;

            // Subscribe to test topic
            $mqttClient->subscribe($testTopic, function($topic, $message) use ($testMessage, &$messageReceived) {
                if ($message === $testMessage) {
                    $messageReceived = true;
                }
            });

            // Publish test message
            $mqttClient->publish($testTopic, $testMessage, 0);

            // Wait for message (with timeout)
            $startTime = time();
            while (!$messageReceived && (time() - $startTime) < 5) {
                $mqttClient->loop(true, true);
                usleep(100000); // 0.1 seconds
            }

            // Disconnect
            $mqttClient->disconnect();

            // Update broker status
            $broker->update([
                'status' => 'active',
                'last_connected_at' => now(),
                'connection_error' => null
            ]);

            $message = $messageReceived
                ? "Successfully connected and tested pub/sub functionality"
                : "Connected successfully but pub/sub test failed";

            Log::info('MQTT connection test successful', [
                'broker_id' => $broker->id,
                'pubsub_test' => $messageReceived
            ]);

            return [
                'success' => true,
                'message' => $message,
                'details' => [
                    'host' => $host,
                    'port' => $port,
                    'tls' => $useTLS,
                    'auth' => !empty($broker->username),
                    'pubsub_test' => $messageReceived
                ]
            ];

        } catch (Exception $e) {
            // Update broker status on failure
            $broker->update([
                'status' => 'error',
                'connection_error' => $e->getMessage()
            ]);

            Log::error('MQTT connection test failed', [
                'broker_id' => $broker->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'details' => [
                    'host' => $host ?? $broker->host,
                    'port' => $port ?? $broker->port,
                    'error_type' => get_class($e)
                ]
            ];
        }
    }

    /**
     * Test connection from form data (before saving)
     */
    public function testConnectionFromData(array $data): array
    {
        try {
            $tempBroker = new MqttBroker($data);
            return $this->testConnection($tempBroker);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test connection with multiple fallback strategies
     */
    public function testConnectionWithFallbacks(MqttBroker $broker): array
    {
        // First try the standard connection
        $result = $this->testConnection($broker);

        if ($result['success']) {
            return $result;
        }

        // If TLS failed, try with different TLS settings
        if ($broker->use_ssl && strpos($result['message'], 'TLS') !== false) {
            Log::info('TLS connection failed, trying with relaxed TLS settings', [
                'broker_id' => $broker->id
            ]);

            $result = $this->testConnectionWithRelaxedTLS($broker);
            if ($result['success']) {
                return $result;
            }
        }

        // If still failing, try socket test as final fallback
        $socketResult = $this->testSocketConnection($broker);

        return [
            'success' => $socketResult['success'],
            'message' => $socketResult['success']
                ? $socketResult['message'] . ' (MQTT protocol test failed, but socket connectivity confirmed)'
                : $result['message'],
            'details' => array_merge($result['details'] ?? [], [
                'fallback_used' => 'socket_test',
                'socket_result' => $socketResult['success']
            ])
        ];
    }

    /**
     * Test connection with relaxed TLS settings
     */
    private function testConnectionWithRelaxedTLS(MqttBroker $broker): array
    {
        try {
            $clientId = 'test_relaxed_' . uniqid();
            $host = $broker->host;
            $port = $this->getConnectionPort($broker);
            $timeout = $broker->timeout ?? 10;

            $mqttClient = new MqttClient($host, $port, $clientId);

            $connectionSettings = (new ConnectionSettings())
                ->setUseTls(true)
                ->setKeepAliveInterval(60)
                ->setConnectTimeout($timeout)
                ->setSocketTimeout($timeout);

            // Very relaxed TLS settings
            $connectionSettings = $connectionSettings
                ->setTlsVerifyPeer(false)
                ->setTlsVerifyPeerName(false)
                ->setTlsSelfSignedAllowed(true);

            if ($broker->username && $broker->password) {
                $connectionSettings->setUsername($broker->username);
                $connectionSettings->setPassword($broker->password);
            }

            $mqttClient->connect($connectionSettings, true);
            $mqttClient->disconnect();

            $broker->update([
                'status' => 'active',
                'last_connected_at' => now(),
                'connection_error' => null
            ]);

            return [
                'success' => true,
                'message' => 'Connected successfully with relaxed TLS settings',
                'details' => [
                    'host' => $host,
                    'port' => $port,
                    'tls' => true,
                    'tls_mode' => 'relaxed',
                    'auth' => !empty($broker->username)
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Relaxed TLS connection also failed: ' . $e->getMessage(),
                'details' => [
                    'error_type' => get_class($e)
                ]
            ];
        }
    }

    /**
     * Get the appropriate connection port based on broker settings
     */
    private function getConnectionPort(MqttBroker $broker): int
    {
        if ($broker->use_ssl) {
            return $broker->ssl_port ?? 8883;
        }

        return $broker->port ?? 1883;
    }

    /**
     * Test all active brokers
     */
    public function testAllBrokers(): array
    {
        $results = [];
        $brokers = MqttBroker::where('status', 'active')->get();

        foreach ($brokers as $broker) {
            $results[$broker->id] = [
                'broker' => $broker->name,
                'result' => $this->testConnection($broker)
            ];
        }

        return $results;
    }

    /**
     * Basic socket connectivity test (fallback)
     */
    public function testSocketConnection(MqttBroker $broker): array
    {
        $host = $broker->host;
        $port = $this->getConnectionPort($broker);
        $timeout = $broker->timeout ?? 10;

        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($connection) {
            fclose($connection);
            return [
                'success' => true,
                'message' => "Socket connection successful to {$host}:{$port}"
            ];
        }

        return [
            'success' => false,
            'message' => "Socket connection failed to {$host}:{$port} - {$errstr} ({$errno})"
        ];
    }
}
