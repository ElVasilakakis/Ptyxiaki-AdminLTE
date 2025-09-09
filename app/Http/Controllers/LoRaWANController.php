<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class LoRaWANController extends Controller
{
    // TTN Connection Parameters - Updated with your working credentials
    private const TTN_HOST = 'eu1.cloud.thethings.industries';
    private const TTN_PORT = 8883;
    private const TTN_USERNAME = 'laravel-backend@ptyxiakinetwork';
    private const DEVICE_ID = 'test-lorawan-1';
    private const API_KEY = 'NNSXS.S44Q7UFP4YFNSADL3MINDUYCQZAO7QSW4BGWSWA.TMJ6IK457FJWIVMJY26D4ZNH5QTKZMQYJMUT4E63HJL4VHVW2WRQ';

    public function debugConnection(Request $request)
    {
        $html = '<html><head><title>LoRaWAN Connection Test</title></head><body>';
        $html .= '<h1>LoRaWAN Connection Debug</h1>';
        $html .= '<div style="font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;">';
        
        try {
            $startTime = microtime(true);
            $html .= '<p><strong>🔧 Starting Connection Test...</strong></p>';
            
            // Use your working API key from Postman
            $apiKey = $request->input('api_key') ?? self::API_KEY;
            
            if (!$apiKey) {
                $html .= '<p style="color: red;">❌ No API key provided</p>';
                return $html . '</div></body></html>';
            }
            
            $html .= '<p>✅ API Key: ' . substr($apiKey, 0, 20) . '...</p>';
            
            // Connection parameters matching Postman
            $html .= '<p><strong>Connection Parameters (Matching Postman):</strong></p>';
            $html .= '<ul>';
            $html .= '<li>Host: ' . self::TTN_HOST . '</li>';
            $html .= '<li>Port: ' . self::TTN_PORT . '</li>';
            $html .= '<li>Protocol: MQTTS (TLS)</li>';
            $html .= '<li>Username: ' . self::TTN_USERNAME . '</li>';
            $html .= '<li>Device ID: ' . self::DEVICE_ID . '</li>';
            $html .= '<li>Client ID: laravel_debug_' . uniqid() . '</li>';
            $html .= '<li>MQTT Version: Default (v3.1.1)</li>';
            $html .= '<li>Clean Session: true</li>';
            $html .= '</ul>';
            
            // Create connection matching Postman settings
            $clientId = 'laravel_debug_' . uniqid();
            $mqttClient = new MqttClient(self::TTN_HOST, self::TTN_PORT, $clientId);
            
            // Configure exactly like Postman - REMOVED setMqttVersion()
            $connectionSettings = (new ConnectionSettings())
                ->setUseTls(true)
                ->setTlsVerifyPeer(true)
                ->setTlsSelfSignedAllowed(false)
                ->setUsername(self::TTN_USERNAME)
                ->setPassword($apiKey)
                ->setKeepAliveInterval(60)
                ->setConnectTimeout(30)
                ->setSocketTimeout(30);

            $html .= '<p><strong>🔗 Attempting Connection (Using Postman Settings)...</strong></p>';
            
            // Connect with clean session
            $cleanSession = true;
            $mqttClient->connect($connectionSettings, $cleanSession);
            
            $connectionTime = microtime(true) - $startTime;
            $html .= '<p style="color: green;">✅ Connection Successful! (' . round($connectionTime, 2) . 's)</p>';
            $html .= '<p style="color: green;">🎉 <strong>BREAKTHROUGH!</strong> Authentication working with new API key!</p>';
            
            // Test the exact same topic as Postman
            $topics = $this->getTopics();
            $html .= '<p><strong>📡 Testing Same Topic as Postman:</strong></p>';
            $html .= '<ul>';
            foreach ($topics as $topic) {
                $html .= '<li>' . $topic . '</li>';
            }
            $html .= '</ul>';
            
            // Subscribe to the working topic
            $html .= '<p><strong>🎯 Testing Subscription (Same as Postman)...</strong></p>';
            try {
                $testTopic = $topics[0]; // Up messages - same as Postman
                $receivedMessages = [];
                
                $mqttClient->subscribe($testTopic, function($topic, $message) use (&$receivedMessages) {
                    $receivedMessages[] = [
                        'topic' => $topic,
                        'message' => $message,
                        'time' => date('H:i:s')
                    ];
                    Log::info('LoRaWAN Message Received', ['topic' => $topic, 'message' => $message]);
                }, 0);
                
                $html .= '<p style="color: green;">✅ Successfully subscribed to: ' . $testTopic . '</p>';
            } catch (\Exception $e) {
                $html .= '<p style="color: orange;">⚠️ Subscription test failed: ' . $e->getMessage() . '</p>';
            }
            
            // Listen for messages like Postman
            $html .= '<p><strong>🔄 Listening for Messages (10 seconds)...</strong></p>';
            $loopStart = time();
            $messageCount = 0;
            
            while ((time() - $loopStart) < 10) {
                try {
                    $mqttClient->loop(true, true);
                    $messageCount++;
                    
                    usleep(250000); // 0.25 seconds
                } catch (\Exception $loopException) {
                    $html .= '<p style="color: orange;">⚠️ Loop interrupted: ' . $loopException->getMessage() . '</p>';
                    break;
                }
            }
            
            $html .= '<p>✅ Listening completed - ' . $messageCount . ' iterations</p>';
            
            if (!empty($receivedMessages)) {
                $html .= '<p style="color: green;"><strong>📨 Messages Received:</strong></p>';
                foreach ($receivedMessages as $msg) {
                    $html .= '<div style="background: #d4edda; padding: 10px; margin: 5px; border-radius: 5px;">';
                    $html .= '<p><strong>Time:</strong> ' . $msg['time'] . '</p>';
                    $html .= '<p><strong>Topic:</strong> ' . $msg['topic'] . '</p>';
                    $html .= '<p><strong>Message:</strong> ' . htmlspecialchars(substr($msg['message'], 0, 200)) . '...</p>';
                    $html .= '</div>';
                }
            } else {
                $html .= '<p style="color: orange;">📭 No messages received (device may not be sending data)</p>';
            }
            
            // Connection status check
            try {
                $isConnected = $mqttClient->isConnected();
                $html .= '<p>📊 Connection Status: ' . ($isConnected ? '🟢 Connected' : '🔴 Disconnected') . '</p>';
            } catch (\Exception $statusException) {
                $html .= '<p style="color: orange;">⚠️ Could not check connection status: ' . $statusException->getMessage() . '</p>';
            }
            
            // Clean disconnect
            try {
                $mqttClient->disconnect();
                $html .= '<p style="color: green;">✅ Disconnected cleanly</p>';
            } catch (\Exception $disconnectException) {
                $html .= '<p style="color: orange;">⚠️ Disconnect warning: ' . $disconnectException->getMessage() . '</p>';
            }
            
            $totalTime = microtime(true) - $startTime;
            $html .= '<p><strong>⏱️ Total test time: ' . round($totalTime, 2) . ' seconds</strong></p>';
            
        } catch (\Exception $e) {
            $html .= '<p style="color: red;">❌ Connection Failed!</p>';
            $html .= '<p style="color: red;"><strong>Error:</strong> ' . $e->getMessage() . '</p>';
            $html .= '<p style="color: red;"><strong>Error Code:</strong> ' . $e->getCode() . '</p>';
            
            // Show stack trace for debugging
            $html .= '<p><strong>🔍 Debug Information:</strong></p>';
            $html .= '<pre style="background: #ffe6e6; padding: 10px; border-radius: 5px; font-size: 12px;">';
            $html .= 'File: ' . $e->getFile() . "\n";
            $html .= 'Line: ' . $e->getLine() . "\n";
            $html .= 'Trace: ' . substr($e->getTraceAsString(), 0, 1000) . '...';
            $html .= '</pre>';
            
            $html .= '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;">';
            $html .= '<h4>🚨 PHP vs Postman Analysis</h4>';
            $html .= '<p><strong>Postman Status:</strong> ✅ Connected & Working</p>';
            $html .= '<p><strong>PHP Status:</strong> ❌ ' . $e->getMessage() . '</p>';
            $html .= '<p><strong>Progress Made:</strong></p>';
            $html .= '<ul>';
            $html .= '<li>✅ Fixed hostname parsing (removed mqtts://)</li>';
            $html .= '<li>✅ Fixed missing methods (removed setMqttVersion)</li>';
            $html .= '<li>✅ Using correct API key from Postman</li>';
            $html .= '<li>✅ TLS configuration matches Postman</li>';
            $html .= '<li>⚠️ Current issue: ' . $e->getMessage() . '</li>';
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '<hr>';
        
        // Show library information
        $html .= '<h3>📚 Library Information:</h3>';
        $html .= '<div style="background: #e2e3e5; padding: 10px; border-radius: 5px;">';
        
        try {
            $reflection = new \ReflectionClass('PhpMqtt\Client\ConnectionSettings');
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            $html .= '<p><strong>Available ConnectionSettings Methods:</strong></p>';
            $html .= '<ul>';
            foreach ($methods as $method) {
                if (strpos($method->getName(), 'set') === 0) {
                    $html .= '<li>' . $method->getName() . '</li>';
                }
            }
            $html .= '</ul>';
        } catch (\Exception $reflectionError) {
            $html .= '<p>Could not inspect ConnectionSettings class</p>';
        }
        
        $html .= '</div>';
        
        $html .= '<h3>✅ Postman Comparison:</h3>';
        $html .= '<div style="background: #d4edda; padding: 15px; border-radius: 5px;">';
        $html .= '<p><strong>Postman Results:</strong></p>';
        $html .= '<ul>';
        $html .= '<li>✅ Connected to broker successfully</li>';
        $html .= '<li>✅ Subscribed to topic: .../test-lorawan-1/up</li>';
        $html .= '<li>✅ Using API key: NNSXS.S44Q7UFP4YFNSADL3MINDUYCQZAO7QSW4BGWSWA...</li>';
        $html .= '<li>✅ TLS connection established</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        $html .= '<h3>🧪 Test Different API Keys:</h3>';
        $html .= '<form method="GET" action="/debug/lorawan-check">';
        $html .= '<input type="text" name="api_key" placeholder="Enter TTN API Key" style="width: 500px; padding: 5px;" value="' . htmlspecialchars($request->input('api_key') ?? self::API_KEY) . '">';
        $html .= '<button type="submit" style="padding: 5px 15px; margin-left: 10px;">Test Connection</button>';
        $html .= '</form>';
        
        $html .= '<hr>';
        $html .= '<p><small>Generated at: ' . date('Y-m-d H:i:s') . ' EEST</small></p>';
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Alternative test with minimal settings
     */
    public function simpleTest(Request $request)
    {
        try {
            $apiKey = self::API_KEY;
            $clientId = 'simple_test_' . uniqid();
            
            // Create the most basic connection possible
            $mqttClient = new MqttClient(self::TTN_HOST, self::TTN_PORT, $clientId);
            
            // Minimal settings
            $connectionSettings = (new ConnectionSettings())
                ->setUseTls(true)
                ->setUsername(self::TTN_USERNAME)
                ->setPassword($apiKey);
            
            $mqttClient->connect($connectionSettings, true);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Simple connection successful!',
                'client_id' => $clientId,
                'host' => self::TTN_HOST,
                'port' => self::TTN_PORT
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ]);
        }
    }

    /**
     * Get all topics for a device
     */
    private function getTopics($deviceId = self::DEVICE_ID)
    {
        $baseTopic = "v3/" . self::TTN_USERNAME . "/devices/{$deviceId}";
        
        return [
            "{$baseTopic}/up",           // Uplink messages - same as Postman
            "{$baseTopic}/join",         // Join messages
            "{$baseTopic}/down/push"     // Downlink confirmations
        ];
    }
}
