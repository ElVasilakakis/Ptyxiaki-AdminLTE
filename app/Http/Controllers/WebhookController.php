<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\WebhookMqttBridge;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private WebhookMqttBridge $webhookBridge;

    public function __construct(WebhookMqttBridge $webhookBridge)
    {
        $this->webhookBridge = $webhookBridge;
    }

    /**
     * Handle incoming webhook data for MQTT devices
     */
    public function handleMqttWebhook(Request $request, string $deviceId): JsonResponse
    {
        try {
            // Find the device
            $device = Device::where('device_id', $deviceId)->first();

            if (!$device) {
                Log::warning('Webhook received for unknown device', [
                    'device_id' => $deviceId,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Device not found'
                ], 404);
            }

            // Validate token
            $token = $request->query('token');
            if (!$token || !$this->webhookBridge->validateWebhookToken($device, $token)) {
                Log::warning('Invalid webhook token', [
                    'device_id' => $deviceId,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or missing token'
                ], 401);
            }

            // Get the request data
            $data = $request->all();

            // Remove token from data to avoid processing it as sensor data
            unset($data['token']);

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data provided'
                ], 400);
            }

            // Process the webhook data
            $result = $this->webhookBridge->processWebhookData($device, $data);

            Log::info('Webhook processed successfully', [
                'device_id' => $deviceId,
                'sensors_updated' => $result['sensors_updated'] ?? 0,
                'ip' => $request->ip()
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get webhook instructions for a device
     */
    public function getWebhookInstructions(Request $request, string $deviceId): JsonResponse
    {
        try {
            $device = Device::where('device_id', $deviceId)->first();

            if (!$device) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not found'
                ], 404);
            }

            // Check if user owns this device (if authenticated)
            if ($request->user() && $device->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $instructions = $this->webhookBridge->getWebhookInstructions($device);

            return response()->json([
                'success' => true,
                'device_id' => $deviceId,
                'instructions' => $instructions
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting webhook instructions', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Test webhook endpoint (for debugging)
     */
    public function testWebhook(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Webhook endpoint is working',
            'timestamp' => now()->toISOString(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'data_received' => $request->all()
        ]);
    }
}
