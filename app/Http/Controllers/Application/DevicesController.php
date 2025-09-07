<?php

namespace App\Http\Controllers\Application;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\MqttBroker;
use App\Models\Land;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DevicesController extends Controller
{
    public function index()
    {
        $devices = Device::with(['mqttBroker', 'land', 'sensors'])
            ->forUser(Auth::id())
            ->latest()
            ->get();
        
        return view('application.devices.index', compact('devices'));
    }

    public function create()
    {
        $mqttBrokers = MqttBroker::forUser(Auth::id())->where('status', 'active')->get();
        $lands = Land::forUser(Auth::id())->where('enabled', true)->get();
        
        return view('application.devices.create', compact('mqttBrokers', 'lands'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:255|unique:devices,device_id',
            'name' => 'required|string|max:255',
            'device_type' => 'required|in:sensor,actuator,gateway,controller',
            'mqtt_broker_id' => 'required|exists:mqtt_brokers,id',
            'land_id' => 'required|exists:lands,id',
            'status' => 'required|in:online,offline,maintenance,error',
            'installed_at' => 'nullable|date',
            'topics' => 'nullable|array',
            'topics.*' => 'nullable|string|max:255',
            'protocol' => 'required|in:mqtt,mqtts,ws,wss',
            'mqtt_port' => 'nullable|integer|min:1|max:65535',
            'mqtts_port' => 'nullable|integer|min:1|max:65535',
            'ws_port' => 'nullable|integer|min:1|max:65535',
            'wss_port' => 'nullable|integer|min:1|max:65535',
            'configuration' => 'nullable|string',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validate that the selected MQTT broker and land belong to the user
        $mqttBroker = MqttBroker::where('id', $request->mqtt_broker_id)
            ->where('user_id', Auth::id())
            ->first();
        
        $land = Land::where('id', $request->land_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$mqttBroker || !$land) {
            return redirect()->back()
                ->withErrors(['error' => 'Invalid MQTT broker or land selection.'])
                ->withInput();
        }

        // Prepare topics array
        $topics = null;
        if ($request->topics && is_array($request->topics)) {
            $topics = array_filter(array_map('trim', $request->topics));
        }

        // Prepare configuration array
        $configuration = null;
        if ($request->configuration) {
            $configuration = json_decode($request->configuration, true) ?: ['raw' => $request->configuration];
        }

        Device::create([
            'device_id' => $request->device_id,
            'name' => $request->name,
            'device_type' => $request->device_type,
            'mqtt_broker_id' => $request->mqtt_broker_id,
            'land_id' => $request->land_id,
            'user_id' => Auth::id(),
            'status' => $request->status,
            'installed_at' => $request->installed_at ? now()->parse($request->installed_at) : null,
            'topics' => $topics,
            'protocol' => $request->protocol,
            'mqtt_port' => $request->mqtt_port ?: 1883,
            'mqtts_port' => $request->mqtts_port ?: 8883,
            'ws_port' => $request->ws_port ?: 8083,
            'wss_port' => $request->wss_port ?: 8084,
            'configuration' => $configuration,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('app.devices.index')
            ->with('success', 'Device created successfully!');
    }

    public function show(Device $device)
    {
        // Ensure user can only view their own devices
        if ($device->user_id !== Auth::id()) {
            abort(403);
        }

        // Load relationships and get latest sensor readings
        $device->load([
            'mqttBroker', 
            'land', 
            'sensors' => function($query) {
                $query->orderBy('reading_timestamp', 'desc');
            }
        ]);

        // Get the latest location sensor data
        $latestLocation = $device->sensors()
            ->where('sensor_type', 'location')
            ->orderBy('reading_timestamp', 'desc')
            ->first();

        // If we have location data, set it on the device
        if ($latestLocation && $latestLocation->value) {
            $locationData = is_array($latestLocation->value) ? $latestLocation->value : json_decode($latestLocation->value, true);
            if ($locationData && isset($locationData['latitude']) && isset($locationData['longitude'])) {
                $device->current_location = [
                    'type' => 'Point',
                    'coordinates' => [$locationData['longitude'], $locationData['latitude']]
                ];
            }
        }

        return view('application.devices.show', compact('device'));
    }

    public function edit(Device $device)
    {
        // Ensure user can only edit their own devices
        if ($device->user_id !== Auth::id()) {
            abort(403);
        }

        $mqttBrokers = MqttBroker::forUser(Auth::id())->get();
        $lands = Land::forUser(Auth::id())->get();
        
        return view('application.devices.edit', compact('device', 'mqttBrokers', 'lands'));
    }

    public function update(Request $request, Device $device)
    {
        // Ensure user can only update their own devices
        if ($device->user_id !== Auth::id()) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:255|unique:devices,device_id,' . $device->id,
            'name' => 'required|string|max:255',
            'device_type' => 'required|in:sensor,actuator,gateway,controller',
            'mqtt_broker_id' => 'required|exists:mqtt_brokers,id',
            'land_id' => 'required|exists:lands,id',
            'status' => 'required|in:online,offline,maintenance,error',
            'installed_at' => 'nullable|date',
            'topics' => 'nullable|array',
            'topics.*' => 'nullable|string|max:255',
            'protocol' => 'required|in:mqtt,mqtts,ws,wss',
            'mqtt_port' => 'nullable|integer|min:1|max:65535',
            'mqtts_port' => 'nullable|integer|min:1|max:65535',
            'ws_port' => 'nullable|integer|min:1|max:65535',
            'wss_port' => 'nullable|integer|min:1|max:65535',
            'configuration' => 'nullable|string',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validate that the selected MQTT broker and land belong to the user
        $mqttBroker = MqttBroker::where('id', $request->mqtt_broker_id)
            ->where('user_id', Auth::id())
            ->first();
        
        $land = Land::where('id', $request->land_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$mqttBroker || !$land) {
            return redirect()->back()
                ->withErrors(['error' => 'Invalid MQTT broker or land selection.'])
                ->withInput();
        }

        // Prepare topics array
        $topics = null;
        if ($request->topics && is_array($request->topics)) {
            $topics = array_filter(array_map('trim', $request->topics));
        }

        // Prepare configuration array
        $configuration = null;
        if ($request->configuration) {
            $configuration = json_decode($request->configuration, true) ?: ['raw' => $request->configuration];
        }

        $device->update([
            'device_id' => $request->device_id,
            'name' => $request->name,
            'device_type' => $request->device_type,
            'mqtt_broker_id' => $request->mqtt_broker_id,
            'land_id' => $request->land_id,
            'status' => $request->status,
            'installed_at' => $request->installed_at ? now()->parse($request->installed_at) : null,
            'topics' => $topics,
            'protocol' => $request->protocol,
            'mqtt_port' => $request->mqtt_port ?: 1883,
            'mqtts_port' => $request->mqtts_port ?: 8883,
            'ws_port' => $request->ws_port ?: 8083,
            'wss_port' => $request->wss_port ?: 8084,
            'configuration' => $configuration,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('app.devices.index')
            ->with('success', 'Device updated successfully!');
    }

    public function destroy(Device $device)
    {
        // Ensure user can only delete their own devices
        if ($device->user_id !== Auth::id()) {
            abort(403);
        }

        $device->delete();

        return redirect()->route('app.devices.index')
            ->with('success', 'Device deleted successfully!');
    }

    public function toggleStatus(Request $request, Device $device)
    {
        // Ensure user can only toggle their own devices
        if ($device->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid data'], 400);
        }

        try {
            $device->update([
                'is_active' => $request->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device status updated successfully',
                'is_active' => $device->is_active
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update device status'
            ], 500);
        }
    }

    public function updateStatus(Request $request, Device $device)
    {
        // Ensure user can only update their own devices
        if ($device->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:online,offline,maintenance,error',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
        }

        try {
            $device->update([
                'status' => $request->status,
                'last_seen_at' => $request->status === 'online' ? now() : $device->last_seen_at
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device status updated successfully',
                'status' => $device->status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update device status'
            ], 500);
        }
    }
}
