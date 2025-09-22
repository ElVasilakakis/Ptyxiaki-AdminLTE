<?php

namespace App\Http\Controllers\Application;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\Http\JsonResponse;

class MQTTController extends Controller
{
    public function startListener(Request $request, Device $device): JsonResponse
    {
        // Ensure user can only start listener for their own devices
        if ($device->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($device->connection_type !== 'mqtt') {
            return response()->json(['success' => false, 'message' => 'Device is not configured for MQTT'], 400);
        }

        if (!$device->mqtt_host) {
            return response()->json(['success' => false, 'message' => 'MQTT host is not configured'], 400);
        }

        if (!$device->mqtt_topics || empty($device->mqtt_topics)) {
            return response()->json(['success' => false, 'message' => 'MQTT topics are not configured'], 400);
        }

        try {
            // Start the MQTT listener in the background
            $command = "php artisan mqtt:listen {$device->device_id} --timeout=300";
            
            // For Windows, use start command to run in background
            if (PHP_OS_FAMILY === 'Windows') {
                $backgroundCommand = "start /B {$command}";
            } else {
                $backgroundCommand = "{$command} > /dev/null 2>&1 &";
            }

            // Execute the command
            $result = Process::run($backgroundCommand);

            return response()->json([
                'success' => true,
                'message' => 'MQTT listener started successfully',
                'command' => $command,
                'device_id' => $device->device_id
            ]);

        } catch (\Exception $e) {
            \Log::error('Error starting MQTT listener', [
                'device_id' => $device->device_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start MQTT listener: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testDevice(Request $request, Device $device): JsonResponse
    {
        // Ensure user can only test their own devices
        if ($device->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $issues = [];
        $config = [];

        // Check device configuration
        $config['device_id'] = $device->device_id;
        $config['connection_type'] = $device->connection_type;
        $config['mqtt_host'] = $device->mqtt_host;
        $config['mqtt_topics'] = $device->mqtt_topics;
        $config['port'] = $device->port ?: ($device->use_ssl ? 8883 : 1883);
        $config['ssl'] = $device->use_ssl;
        $config['username'] = $device->username ? 'SET' : 'NOT SET';
        $config['status'] = $device->status;

        if ($device->connection_type !== 'mqtt') {
            $issues[] = 'Device is not configured as MQTT device';
        }

        if (!$device->mqtt_host) {
            $issues[] = 'MQTT Host is not configured';
        }

        if (!$device->mqtt_topics || empty($device->mqtt_topics)) {
            $issues[] = 'MQTT Topics are not configured';
        }

        $isValid = empty($issues);

        return response()->json([
            'success' => true,
            'valid' => $isValid,
            'issues' => $issues,
            'config' => $config,
            'command' => "php artisan mqtt:listen {$device->device_id}",
            'message' => $isValid ? 'Device configuration is valid' : 'Device configuration has issues'
        ]);
    }
}
