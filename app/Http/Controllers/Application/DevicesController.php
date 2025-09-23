<?php

namespace App\Http\Controllers\Application;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\Land;
use App\Models\Sensor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class DevicesController extends Controller
{
    public function index()
    {
        $devices = Device::with(['land', 'sensors'])
            ->forUser(Auth::id())
            ->latest()
            ->get();
        
        return view('application.devices.index', compact('devices'));
    }

    public function create()
    {
        $lands = Land::forUser(Auth::id())->where('enabled', true)->get();
        
        return view('application.devices.create', compact('lands'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:255|unique:devices,device_id',
            'name' => 'required|string|max:255',
            'device_type' => 'required|in:sensor,actuator,gateway,controller',
            'land_id' => 'required|exists:lands,id',
            'status' => 'required|in:online,offline,maintenance,error',
            'connection_type' => 'required|in:mqtt,webhook',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            // Connection fields
            'client_id' => 'nullable|string|max:255',
            'use_ssl' => 'boolean',
            'connection_broker' => 'nullable|in:mosquitto',
            'port' => 'nullable|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'auto_reconnect' => 'boolean',
            'max_reconnect_attempts' => 'nullable|integer|min:1|max:100',
            'keepalive' => 'nullable|integer|min:1|max:3600',
            'timeout' => 'nullable|integer|min:1|max:300',
            'mqtt_host' => 'nullable|string|max:255',
            'mqtt_topics' => 'nullable|array',
            'mqtt_topics.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validate that the selected land belongs to the user
        $land = Land::where('id', $request->land_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$land) {
            return redirect()->back()
                ->withErrors(['error' => 'Invalid land selection.'])
                ->withInput();
        }

        Device::create([
            'device_id' => $request->device_id,
            'name' => $request->name,
            'device_type' => $request->device_type,
            'land_id' => $request->land_id,
            'user_id' => Auth::id(),
            'status' => $request->status,
            'connection_type' => $request->connection_type,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
            'client_id' => $request->client_id,
            'use_ssl' => $request->has('use_ssl'),
            'connection_broker' => $request->connection_broker,
            'port' => $request->port,
            'username' => $request->username,
            'password' => $request->password,
            'auto_reconnect' => $request->has('auto_reconnect'),
            'max_reconnect_attempts' => $request->max_reconnect_attempts ?: 3,
            'keepalive' => $request->keepalive ?: 60,
            'timeout' => $request->timeout ?: 30,
            'mqtt_host' => $request->mqtt_host,
            'mqtt_topics' => $request->mqtt_topics,
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

        $lands = Land::forUser(Auth::id())->get();
        
        return view('application.devices.edit', compact('device', 'lands'));
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
            'land_id' => 'required|exists:lands,id',
            'status' => 'required|in:online,offline,maintenance,error',
            'connection_type' => 'required|in:mqtt,webhook',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            // Connection fields
            'client_id' => 'nullable|string|max:255',
            'use_ssl' => 'boolean',
            'connection_broker' => 'nullable|in:emqx,hivemq,mosquitto,thethings_stack',
            'port' => 'nullable|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'auto_reconnect' => 'boolean',
            'max_reconnect_attempts' => 'nullable|integer|min:1|max:100',
            'keepalive' => 'nullable|integer|min:1|max:3600',
            'timeout' => 'nullable|integer|min:1|max:300',
            'mqtt_host' => 'nullable|string|max:255',
            'mqtt_topics' => 'nullable|array',
            'mqtt_topics.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validate that the selected land belongs to the user
        $land = Land::where('id', $request->land_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$land) {
            return redirect()->back()
                ->withErrors(['error' => 'Invalid land selection.'])
                ->withInput();
        }

        $device->update([
            'device_id' => $request->device_id,
            'name' => $request->name,
            'device_type' => $request->device_type,
            'land_id' => $request->land_id,
            'status' => $request->status,
            'connection_type' => $request->connection_type,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
            'client_id' => $request->client_id,
            'use_ssl' => $request->has('use_ssl'),
            'connection_broker' => $request->connection_broker,
            'port' => $request->port,
            'username' => $request->username,
            'password' => $request->filled('password') ? $request->password : $device->password,
            'auto_reconnect' => $request->has('auto_reconnect'),
            'max_reconnect_attempts' => $request->max_reconnect_attempts ?: $device->max_reconnect_attempts,
            'keepalive' => $request->keepalive ?: $device->keepalive,
            'timeout' => $request->timeout ?: $device->timeout,
            'mqtt_host' => $request->mqtt_host,
            'mqtt_topics' => $request->mqtt_topics,
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

    /**
     * Store sensor data from MQTT
     */
    public function storeSensors(Request $request, Device $device): JsonResponse
    {
        // Ensure user can only store data for their own devices
        if ($device->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'device_id' => 'required|integer',
            'sensor_type' => 'required|string|max:255',
            'value' => 'nullable',
            'unit' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find or create sensor
            $sensor = Sensor::firstOrCreate(
                [
                    'device_id' => $device->id,
                    'sensor_type' => $request->sensor_type,
                    'user_id' => Auth::id(),
                ],
                [
                    'sensor_name' => ucfirst($request->sensor_type) . ' Sensor',
                    'description' => 'Auto-created sensor from MQTT data',
                    'unit' => $request->unit,
                    'enabled' => true,
                    'alert_enabled' => false,
                ]
            );

            // Update sensor reading with current timestamp
            $sensor->updateReading($request->value, now());
            
            // Update unit if provided and different
            if ($request->unit && $sensor->unit !== $request->unit) {
                $sensor->update(['unit' => $request->unit]);
            }

            // Update device last seen and status
            $device->update([
                'last_seen_at' => now(),
                'status' => 'online'
            ]);

            // Refresh sensor to get updated data
            $sensor->refresh();

            // Prepare response data
            $sensorData = [
                'id' => $sensor->id,
                'sensor_type' => $sensor->sensor_type,
                'value' => $sensor->value,
                'unit' => $sensor->unit,
                'formatted_value' => $sensor->getFormattedValue(),
                'alert_status' => $sensor->getAlertStatus(),
                'reading_timestamp' => $sensor->reading_timestamp?->toISOString(),
                'time_since_reading' => $sensor->getTimeSinceLastReading(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Sensor data stored successfully',
                'sensor' => $sensorData,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error storing sensor data: ' . $e->getMessage(), [
                'device_id' => $device->id,
                'sensor_type' => $request->sensor_type,
                'error' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store sensor data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get device alerts HTML for real-time updates
     */
    public function getAlerts(Device $device): JsonResponse
    {
        // Ensure user can only get alerts for their own devices
        if ($device->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $alertSensors = $device->sensors()
                ->where('alert_enabled', true)
                ->get()
                ->filter(function($sensor) {
                    return $sensor->getAlertStatus() !== 'normal';
                });

            $alertsHtml = '';
            
            foreach ($alertSensors as $sensor) {
                $alertStatus = $sensor->getAlertStatus();
                $alertClass = $alertStatus === 'high' ? 'danger' : 'warning';
                $alertIcon = $alertStatus === 'high' ? 'ph-arrow-up' : 'ph-arrow-down';
                $alertText = $alertStatus === 'high' ? 'High Alert' : 'Low Alert';
                
                $alertsHtml .= '<div class="col-md-6 col-lg-4 mb-3">';
                $alertsHtml .= '<div class="alert alert-' . $alertClass . ' mb-0">';
                $alertsHtml .= '<div class="d-flex align-items-center">';
                $alertsHtml .= '<i class="' . $alertIcon . ' me-2"></i>';
                $alertsHtml .= '<div>';
                $alertsHtml .= '<strong>' . e($sensor->sensor_type) . '</strong> - ' . $alertText;
                $alertsHtml .= '<br><small>Current: ' . e($sensor->getFormattedValue()) . '</small>';
                $alertsHtml .= '<br><small>Threshold: ';
                
                if ($alertStatus === 'high') {
                    $alertsHtml .= 'Max ' . $sensor->alert_threshold_max;
                } else {
                    $alertsHtml .= 'Min ' . $sensor->alert_threshold_min;
                }
                
                $alertsHtml .= $sensor->unit ? ' ' . e($sensor->unit) : '';
                $alertsHtml .= '</small>';
                $alertsHtml .= '</div></div></div></div>';
            }

            return response()->json([
                'success' => true,
                'alerts' => $alertsHtml,
                'alert_count' => $alertSensors->count(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting device alerts: ' . $e->getMessage(), [
                'device_id' => $device->id,
                'error' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get alerts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get device sensor data for dashboard updates
     */
    public function getSensorData(Device $device): JsonResponse
    {
        // Ensure user can only get data for their own devices
        if ($device->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $sensors = $device->sensors()
                ->where('enabled', true)
                ->orderBy('sensor_type')
                ->get()
                ->map(function($sensor) {
                    return [
                        'id' => $sensor->id,
                        'sensor_type' => $sensor->sensor_type,
                        'sensor_name' => $sensor->sensor_name,
                        'value' => $sensor->value,
                        'unit' => $sensor->unit,
                        'formatted_value' => $sensor->getFormattedValue(),
                        'alert_status' => $sensor->getAlertStatus(),
                        'reading_timestamp' => $sensor->reading_timestamp?->toISOString(),
                        'time_since_reading' => $sensor->getTimeSinceLastReading(),
                        'has_recent_reading' => $sensor->hasRecentReading(),
                    ];
                });

            return response()->json([
                'success' => true,
                'sensors' => $sensors,
                'device_status' => $device->status,
                'last_seen' => $device->last_seen_at?->toISOString(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting sensor data: ' . $e->getMessage(), [
                'device_id' => $device->id,
                'error' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sensor data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

}
