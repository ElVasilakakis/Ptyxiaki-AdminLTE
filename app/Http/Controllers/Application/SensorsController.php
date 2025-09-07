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
        return view('application.sensors.index');
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
}
