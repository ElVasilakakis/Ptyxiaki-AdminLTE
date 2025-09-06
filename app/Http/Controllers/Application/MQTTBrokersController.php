<?php

namespace App\Http\Controllers\Application;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MqttBroker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MQTTBrokersController extends Controller
{
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
            'type' => 'required|in:emqx,mosquitto,lorawan',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'websocket_port' => 'nullable|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'use_ssl' => 'boolean',
            'ssl_port' => 'nullable|integer|min:1|max:65535',
            'client_id' => 'nullable|string|max:255',
            'keepalive' => 'required|integer|min:1|max:3600',
            'timeout' => 'required|integer|min:1|max:300',
            'auto_reconnect' => 'boolean',
            'max_reconnect_attempts' => 'required|integer|min:1|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        MqttBroker::create([
            'name' => $request->name,
            'type' => $request->type,
            'host' => $request->host,
            'port' => $request->port,
            'websocket_port' => $request->websocket_port,
            'username' => $request->username,
            'password' => $request->password,
            'use_ssl' => $request->has('use_ssl'),
            'ssl_port' => $request->ssl_port,
            'client_id' => $request->client_id,
            'keepalive' => $request->keepalive,
            'timeout' => $request->timeout,
            'auto_reconnect' => $request->has('auto_reconnect'),
            'max_reconnect_attempts' => $request->max_reconnect_attempts,
            'description' => $request->description,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('app.mqttbrokers.index')
            ->with('success', 'MQTT Broker created successfully!');
    }

    public function show(MqttBroker $mqttbroker)
    {
        // Ensure user can only view their own brokers
        if ($mqttbroker->user_id !== Auth::id()) {
            abort(403);
        }

        $mqttbroker->load('devices');
        return view('application.mqttbrokers.show', compact('mqttbroker'));
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
            'type' => 'required|in:emqx,mosquitto,lorawan',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'websocket_port' => 'nullable|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'use_ssl' => 'boolean',
            'ssl_port' => 'nullable|integer|min:1|max:65535',
            'client_id' => 'nullable|string|max:255',
            'keepalive' => 'required|integer|min:1|max:3600',
            'timeout' => 'required|integer|min:1|max:300',
            'auto_reconnect' => 'boolean',
            'max_reconnect_attempts' => 'required|integer|min:1|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $mqttbroker->update([
            'name' => $request->name,
            'type' => $request->type,
            'host' => $request->host,
            'port' => $request->port,
            'websocket_port' => $request->websocket_port,
            'username' => $request->username,
            'password' => $request->password,
            'use_ssl' => $request->has('use_ssl'),
            'ssl_port' => $request->ssl_port,
            'client_id' => $request->client_id,
            'keepalive' => $request->keepalive,
            'timeout' => $request->timeout,
            'auto_reconnect' => $request->has('auto_reconnect'),
            'max_reconnect_attempts' => $request->max_reconnect_attempts,
            'description' => $request->description,
        ]);

        return redirect()->route('app.mqttbrokers.index')
            ->with('success', 'MQTT Broker updated successfully!');
    }

    public function destroy(MqttBroker $mqttbroker)
    {
        // Ensure user can only delete their own brokers
        if ($mqttbroker->user_id !== Auth::id()) {
            abort(403);
        }

        $mqttbroker->delete();

        return redirect()->route('app.mqttbrokers.index')
            ->with('success', 'MQTT Broker deleted successfully!');
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
                'message' => 'Broker status updated successfully',
                'status' => $mqttbroker->status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update broker status'
            ], 500);
        }
    }

    public function testConnection(Request $request, MqttBroker $mqttbroker)
    {
        // Ensure user can only test their own brokers
        if ($mqttbroker->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            // Simulate MQTT connection test
            $connectionResult = $this->performMqttConnectionTest($mqttbroker);
            
            if ($connectionResult['success']) {
                // Update last connected timestamp
                $mqttbroker->update([
                    'last_connected_at' => now(),
                    'status' => 'active'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $connectionResult['message']
                ]);
            } else {
                // Update status to error if connection failed
                $mqttbroker->update([
                    'status' => 'error'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $connectionResult['message']
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function performMqttConnectionTest(MqttBroker $broker)
    {
        try {
            // Basic connection validation
            $host = $broker->host;
            $port = $broker->use_ssl ? ($broker->ssl_port ?? 8883) : $broker->port;
            $timeout = $broker->timeout ?? 10;

            // Test if host is reachable
            $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
            
            if (!$connection) {
                return [
                    'success' => false,
                    'message' => "Cannot connect to {$host}:{$port} - {$errstr} ({$errno})"
                ];
            }

            fclose($connection);

            // If we reach here, basic connection is successful
            return [
                'success' => true,
                'message' => "Successfully connected to {$host}:{$port}"
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Connection test failed: " . $e->getMessage()
            ];
        }
    }

    public function testConnectionFromForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'use_ssl' => 'boolean',
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
            // Create a temporary broker object for testing
            $tempBroker = new MqttBroker([
                'host' => $request->host,
                'port' => $request->port,
                'use_ssl' => $request->use_ssl ?? false,
                'ssl_port' => $request->ssl_port,
                'username' => $request->username,
                'password' => $request->password,
                'timeout' => $request->timeout ?? 30,
            ]);

            $connectionResult = $this->performMqttConnectionTest($tempBroker);
            
            return response()->json([
                'success' => $connectionResult['success'],
                'message' => $connectionResult['message']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
