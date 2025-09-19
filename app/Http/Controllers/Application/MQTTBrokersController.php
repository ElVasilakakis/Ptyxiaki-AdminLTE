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
            'type' => 'required|in:webhook,lorawan',
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
            'description' => $request->description,
            'status' => 'active',
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('app.mqttbrokers.index')
            ->with('success', 'Webhook Connector created successfully!');
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
            'type' => 'required|in:webhook,lorawan',
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
            'description' => $request->description,
        ]);

        return redirect()->route('app.mqttbrokers.index')
            ->with('success', 'Webhook Connector updated successfully!');
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
}
