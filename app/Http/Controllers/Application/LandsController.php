<?php

namespace App\Http\Controllers\Application;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Land;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LandsController extends Controller
{
    public function index()
    {
        $lands = Land::forUser(Auth::id())->with('devices')->latest()->get();
        return view('application.lands.index', compact('lands'));
    }

    public function create()
    {
        return view('application.lands.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'land_name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'geojson' => 'required|json',
            'location' => 'required|json',
            'enabled' => 'boolean',
            'data' => 'nullable|array',
            'data.*.key' => 'nullable|string|max:255',
            'data.*.value' => 'nullable|string|max:255',
        ], [
            'geojson.required' => 'Please draw a polygon on the map to define the land boundaries.',
            'geojson.json' => 'Invalid polygon data. Please redraw the polygon on the map.',
            'location.required' => 'Location data is required. Please draw a polygon on the map.',
            'location.json' => 'Invalid location data. Please redraw the polygon on the map.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Process the data array to remove empty entries
        $data = [];
        if ($request->has('data') && is_array($request->data)) {
            foreach ($request->data as $item) {
                if (!empty($item['key']) && !empty($item['value'])) {
                    $data[$item['key']] = $item['value'];
                }
            }
        }

        Land::create([
            'land_name' => $request->land_name,
            'user_id' => Auth::id(),
            'location' => json_decode($request->location, true),
            'geojson' => json_decode($request->geojson, true),
            'color' => $request->color,
            'data' => $data,
            'enabled' => $request->has('enabled'),
        ]);

        return redirect()->route('app.lands.index')
            ->with('success', 'Land created successfully!');
    }

    public function show(Land $land)
    {
        // Ensure user can only view their own lands
        if ($land->user_id !== Auth::id()) {
            abort(403);
        }

        $land->load('devices.sensors');
        return view('application.lands.show', compact('land'));
    }

    public function edit(Land $land)
    {
        // Ensure user can only edit their own lands
        if ($land->user_id !== Auth::id()) {
            abort(403);
        }

        return view('application.lands.edit', compact('land'));
    }

    public function update(Request $request, Land $land)
    {
        // Ensure user can only update their own lands
        if ($land->user_id !== Auth::id()) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'land_name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'geojson' => 'required|json',
            'location' => 'required|json',
            'enabled' => 'boolean',
            'data' => 'nullable|array',
            'data.*.key' => 'nullable|string|max:255',
            'data.*.value' => 'nullable|string|max:255',
        ], [
            'geojson.required' => 'Please draw a polygon on the map to define the land boundaries.',
            'geojson.json' => 'Invalid polygon data. Please redraw the polygon on the map.',
            'location.required' => 'Location data is required. Please draw a polygon on the map.',
            'location.json' => 'Invalid location data. Please redraw the polygon on the map.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Process the data array to remove empty entries
        $data = [];
        if ($request->has('data') && is_array($request->data)) {
            foreach ($request->data as $item) {
                if (!empty($item['key']) && !empty($item['value'])) {
                    $data[$item['key']] = $item['value'];
                }
            }
        }

        $land->update([
            'land_name' => $request->land_name,
            'location' => json_decode($request->location, true),
            'geojson' => json_decode($request->geojson, true),
            'color' => $request->color,
            'data' => $data,
            'enabled' => $request->has('enabled'),
        ]);

        return redirect()->route('app.lands.index')
            ->with('success', 'Land updated successfully!');
    }

    public function destroy(Land $land)
    {
        // Ensure user can only delete their own lands
        if ($land->user_id !== Auth::id()) {
            abort(403);
        }

        $land->delete();

        return redirect()->route('app.lands.index')
            ->with('success', 'Land deleted successfully!');
    }

    public function toggleStatus(Request $request, Land $land)
    {
        // Ensure user can only toggle their own lands
        if ($land->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid data'], 400);
        }

        try {
            $land->update([
                'enabled' => $request->enabled
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Land status updated successfully',
                'enabled' => $land->enabled
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update land status'
            ], 500);
        }
    }

    /**
     * Get live device data for a land (for map updates)
     */
    public function getLiveDeviceData(Land $land)
    {
        // Ensure user can only get data for their own lands
        if ($land->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            // Load devices with their sensors and latest readings
            $devices = $land->devices()
                ->with(['sensors' => function($query) {
                    $query->orderBy('reading_timestamp', 'desc');
                }])
                ->get()
                ->map(function($device) {
                    return [
                        'id' => $device->id,
                        'name' => $device->name,
                        'device_id' => $device->device_id,
                        'device_type' => $device->device_type,
                        'status' => $device->status,
                        'last_seen_at' => $device->last_seen_at?->toISOString(),
                        'sensors' => $device->sensors->map(function($sensor) {
                            return [
                                'id' => $sensor->id,
                                'sensor_type' => $sensor->sensor_type,
                                'sensor_name' => $sensor->sensor_name,
                                'value' => $sensor->value,
                                'unit' => $sensor->unit,
                                'formatted_value' => $sensor->getFormattedValue(),
                                'alert_enabled' => $sensor->alert_enabled,
                                'alert_status' => $sensor->getAlertStatus(),
                                'reading_timestamp' => $sensor->reading_timestamp?->toISOString(),
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'devices' => $devices,
                'land' => [
                    'id' => $land->id,
                    'name' => $land->land_name,
                    'enabled' => $land->enabled,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting live device data for land: ' . $e->getMessage(), [
                'land_id' => $land->id,
                'error' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get device data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
