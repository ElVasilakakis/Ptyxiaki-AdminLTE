<?php

namespace App\Http\Controllers\Application;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sensor;
use App\Models\Device;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SensorsController extends Controller
{
    public function index()
    {
        $sensors = Sensor::forUser(Auth::id())
            ->with(['device', 'device.land'])
            ->latest('reading_timestamp')
            ->get();
        
        return view('application.sensors.index', compact('sensors'));
    }

    public function edit(Sensor $sensor)
    {
        // Ensure user can only edit their own sensors
        if ($sensor->user_id !== Auth::id()) {
            abort(403);
        }

        return view('application.sensors.edit', compact('sensor'));
    }

    public function update(Request $request, Sensor $sensor)
    {
        // Ensure user can only update their own sensors
        if ($sensor->user_id !== Auth::id()) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'sensor_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'unit' => 'nullable|string|max:50',
            'enabled' => 'boolean',
            'alert_enabled' => 'boolean',
            'alert_threshold_min' => 'nullable|numeric',
            'alert_threshold_max' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validate that min threshold is less than max threshold
        if ($request->alert_threshold_min !== null && $request->alert_threshold_max !== null) {
            if ((float)$request->alert_threshold_min >= (float)$request->alert_threshold_max) {
                return redirect()->back()
                    ->withErrors(['alert_threshold_min' => 'Minimum threshold must be less than maximum threshold'])
                    ->withInput();
            }
        }

        try {
            $sensor->update([
                'sensor_name' => $request->sensor_name,
                'description' => $request->description,
                'unit' => $request->unit,
                'enabled' => $request->has('enabled'),
                'alert_enabled' => $request->has('alert_enabled'),
                'alert_threshold_min' => $request->alert_threshold_min,
                'alert_threshold_max' => $request->alert_threshold_max,
            ]);

            return redirect()->route('app.sensors.index')
                ->with('success', 'Sensor updated successfully!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to update sensor: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:devices,id',
            'sensor_type' => 'required|string|max:255',
            'value' => 'required',
            'unit' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        // Verify that the device belongs to the authenticated user
        $device = Device::where('id', $request->device_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found or access denied'
            ], 403);
        }

        try {
            // Create or update sensor record
            $sensor = Sensor::updateOrCreate(
                [
                    'device_id' => $request->device_id,
                    'sensor_type' => $request->sensor_type,
                ],
                [
                    'user_id' => Auth::id(),
                    'value' => $request->value,
                    'unit' => $request->unit,
                    'reading_timestamp' => now(),
                ]
            );

            // Update device's last seen timestamp
            $device->update(['last_seen_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Sensor data stored successfully',
                'sensor' => $sensor
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store sensor data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live sensor data for real-time updates
     */
    public function getLiveSensorData(Request $request)
    {
        try {
            $sensors = Sensor::forUser(Auth::id())
                ->with(['device', 'device.land'])
                ->latest('reading_timestamp')
                ->get();

            $sensorData = $sensors->map(function ($sensor) {
                return [
                    'id' => $sensor->id,
                    'sensor_type' => $sensor->sensor_type,
                    'sensor_name' => $sensor->sensor_name ?: 'Unnamed',
                    'device_name' => $sensor->device->name,
                    'land_name' => $sensor->device->land->land_name,
                    'value' => $sensor->value,
                    'formatted_value' => $sensor->getFormattedValue(),
                    'unit' => $sensor->unit ?: '-',
                    'enabled' => $sensor->enabled,
                    'alert_enabled' => $sensor->alert_enabled,
                    'alert_status' => $sensor->getAlertStatus(),
                    'reading_timestamp' => $sensor->reading_timestamp ? $sensor->reading_timestamp->toISOString() : null,
                    'reading_human' => $sensor->reading_timestamp ? $sensor->reading_timestamp->diffForHumans() : 'Never',
                    'reading_formatted' => $sensor->reading_timestamp ? $sensor->reading_timestamp->format('M d, Y H:i:s') : null,
                ];
            });

            return response()->json([
                'success' => true,
                'sensors' => $sensorData,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sensor data: ' . $e->getMessage()
            ], 500);
        }
    }
}
