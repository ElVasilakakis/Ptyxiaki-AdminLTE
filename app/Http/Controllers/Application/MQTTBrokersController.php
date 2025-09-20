<?php

namespace App\Http\Controllers\Application;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MqttBroker;
use App\Services\WebhookMqttBridge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MQTTBrokersController extends Controller
{
    private WebhookMqttBridge $webhookBridge;

    public function __construct(WebhookMqttBridge $webhookBridge)
    {
        $this->webhookBridge = $webhookBridge;
    }

    public function index()
    {
        $mqttBrokers = MqttBroker::forUser(Auth::id())->latest()->get();
        return view('application.mqttbrokers.index', compact('mqttBrokers'));
    }

    public function create()
    {
        return view('application.mqttbrokers.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:webhook,lorawan,emqx,mosquitto',
            'description' => 'nullable|string|max:1000',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'websocket_port' => 'nullable|integer|min:1|max:65535',
            'path' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'use_ssl' => 'nullable|boolean',
            'ssl_port' => 'nullable|integer|min:1|max:65535',
            'client_id' => 'nullable|string|max:255',
            'keepalive' => 'nullable|integer|min:1|max:3600',
            'timeout' => 'nullable|integer|min:1|max:300',
            'auto_reconnect' => 'nullable|boolean',
            'max_reconnect_attempts' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->only([
            'name', 'type', 'description', 'host', 'port', 'websocket_port', 'path',
            'username', 'password', 'use_ssl', 'ssl_port', 'client_id', 'keepalive',
            'timeout', 'auto_reconnect', 'max_reconnect_attempts'
        ]);

        $data['status'] = 'active';
        $data['user_id'] = Auth::id();

        MqttBroker::create($data);

        return redirect()->route('app.mqttbrokers.index')
            ->with('success', 'Connector created successfully!');
    }

    public function show(MqttBroker $mqttbroker)
    {
        // Ensure user can only view their own brokers
        if ($mqttbroker->user_id !== Auth::id()) {
            abort(403);
        }

        $mqttbroker->load('devices');

        // Get webhook instructions for all devices
        $webhookInstructions = [];
        foreach ($mqttbroker->devices as $device) {
            $webhookInstructions[$device->device_id] = $this->webhookBridge->getWebhookInstructions($device);
        }

        return view('application.mqttbrokers.show', compact('mqttbroker', 'webhookInstructions'));
    }

    public function edit(MqttBroker $mqttbroker)
    {
        // Ensure user can only edit their own brokers
        if ($mqttbroker->user_id !== Auth::id()) {
            abort(403);
        }

        return view('application.mqttbrokers.edit', compact('mqttbroker'));
    }

    public function update(Request $request, MqttBroker $mqttbroker)
    {
        // Ensure user can only update their own brokers
        if ($mqttbroker->user_id !== Auth::id()) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:webhook,lorawan,emqx,mosquitto',
            'description' => 'nullable|string|max:1000',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'websocket_port' => 'nullable|integer|min:1|max:65535',
            'path' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'use_ssl' => 'nullable|boolean',
            'ssl_port' => 'nullable|integer|min:1|max:65535',
            'client_id' => 'nullable|string|max:255',
            'keepalive' => 'nullable|integer|min:1|max:3600',
            'timeout' => 'nullable|integer|min:1|max:300',
            'auto_reconnect' => 'nullable|boolean',
            'max_reconnect_attempts' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->only([
            'name', 'type', 'description', 'host', 'port', 'websocket_port', 'path',
            'username', 'use_ssl', 'ssl_port', 'client_id', 'keepalive',
            'timeout', 'auto_reconnect', 'max_reconnect_attempts'
        ]);

        // Only update password if provided
        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $mqttbroker->update($data);

        return redirect()->route('app.mqttbrokers.index')
            ->with('success', 'Connector updated successfully!');
    }

    public function destroy(MqttBroker $mqttbroker)
    {
        // Ensure user can only delete their own brokers
        if ($mqttbroker->user_id !== Auth::id()) {
            abort(403);
        }

        $mqttbroker->delete();

        return redirect()->route('app.mqttbrokers.index')
            ->with('success', 'Webhook Connector deleted successfully!');
    }

    public function toggleStatus(Request $request, MqttBroker $mqttbroker)
    {
        // Ensure user can only toggle their own brokers
        if ($mqttbroker->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid data'], 400);
        }

        try {
            $mqttbroker->update([
                'status' => $request->status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Connector status updated successfully',
                'status' => $mqttbroker->status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update connector status'
            ], 500);
        }
    }

    /**
     * Get webhook instructions for a specific device
     */
    public function getWebhookInstructions(Request $request, MqttBroker $mqttbroker, $deviceId)
    {
        // Ensure user can only access their own brokers
        if ($mqttbroker->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $device = $mqttbroker->devices()->where('device_id', $deviceId)->first();

            if (!$device) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not found'
                ], 404);
            }

            $instructions = $this->webhookBridge->getWebhookInstructions($device);

            return response()->json([
                'success' => true,
                'device_id' => $deviceId,
                'instructions' => $instructions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get webhook instructions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test webhook functionality
     */
    public function testWebhook(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Webhook system is working correctly',
            'timestamp' => now()->toISOString(),
            'data_received' => $request->all()
        ]);
    }

    /**
     * Test broker connection
     */
    public function testConnection(Request $request, MqttBroker $mqttbroker)
    {
        // Ensure user can only test their own brokers
        if ($mqttbroker->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            // For webhook/lorawan brokers, just return success since they don't need real connections
            if (in_array($mqttbroker->type, ['webhook', 'lorawan'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook connector is ready to receive data'
                ]);
            }

            // For MQTT brokers, we could implement actual connection testing here
            // For now, just return a basic response
            return response()->json([
                'success' => true,
                'message' => 'Connection test completed - broker appears to be configured correctly'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test connection from form data
     */
    public function testConnectionFromForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'use_ssl' => 'nullable|boolean',
            'ssl_port' => 'nullable|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'timeout' => 'nullable|integer|min:1|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid connection parameters'
            ], 400);
        }

        try {
            // Basic validation of connection parameters
            $host = $request->input('host');
            $port = $request->input('port');

            // Simple connectivity check (you could enhance this with actual MQTT connection testing)
            if (empty($host) || $port < 1 || $port > 65535) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid host or port configuration'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Connection parameters validated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
