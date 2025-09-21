<?php

namespace App\Http\Controllers\Application;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Land;
use App\Models\Device;
use App\Models\Sensor;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Get all user's data
        $lands = Land::forUser($user->id)->with(['devices.sensors'])->get();
        $devices = Device::whereHas('land', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['sensors', 'land'])->get();
        
        $sensors = Sensor::whereHas('device.land', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['device'])->get();
        
        // Calculate statistics
        $stats = [
            'total_lands' => $lands->count(),
            'total_devices' => $devices->count(),
            'total_sensors' => $sensors->count(),
            'online_devices' => $devices->where('status', 'online')->count(),
            'offline_devices' => $devices->where('status', 'offline')->count(),
            'active_lands' => $lands->where('enabled', true)->count(),
            'inactive_lands' => $lands->where('enabled', false)->count(),
        ];
        
        // Calculate alerts
        $alertSensors = $sensors->filter(function($sensor) {
            return $sensor->alert_enabled && $sensor->getAlertStatus() !== 'normal';
        });
        
        $stats['total_alerts'] = $alertSensors->count();
        $stats['high_alerts'] = $alertSensors->filter(function($sensor) {
            return $sensor->getAlertStatus() === 'high';
        })->count();
        $stats['low_alerts'] = $alertSensors->filter(function($sensor) {
            return $sensor->getAlertStatus() === 'low';
        })->count();
        
        // Recent activity (devices seen in last 24 hours)
        $recentDevices = $devices->filter(function($device) {
            return $device->last_seen_at && $device->last_seen_at->isAfter(Carbon::now()->subDay());
        });
        $stats['recent_activity'] = $recentDevices->count();
        
        // Device type breakdown
        $deviceTypes = $devices->groupBy('device_type')->map(function($group) {
            return $group->count();
        });
        
        // Sensor type breakdown
        $sensorTypes = $sensors->groupBy('sensor_type')->map(function($group) {
            return $group->count();
        });
        
        // Recent sensor readings (last 24 hours)
        $recentSensors = $sensors->filter(function($sensor) {
            return $sensor->reading_timestamp && Carbon::parse($sensor->reading_timestamp)->isAfter(Carbon::now()->subDay());
        });
        
        // Get devices with GPS coordinates for map
        $devicesWithGPS = $devices->filter(function($device) {
            $latSensor = $device->sensors->where('sensor_type', 'latitude')->first();
            $lngSensor = $device->sensors->where('sensor_type', 'longitude')->first();
            return $latSensor && $lngSensor && $latSensor->value && $lngSensor->value;
        });
        
        // Get recent alerts (last 7 days)
        $recentAlerts = $alertSensors->filter(function($sensor) {
            return $sensor->reading_timestamp && Carbon::parse($sensor->reading_timestamp)->isAfter(Carbon::now()->subWeek());
        })->sortByDesc('reading_timestamp')->take(10);
        
        return view('application.dashboard', compact(
            'stats', 
            'lands', 
            'devices', 
            'sensors',
            'deviceTypes',
            'sensorTypes',
            'devicesWithGPS',
            'recentAlerts',
            'alertSensors'
        ));
    }
    
    /**
     * Get dashboard data for AJAX updates
     */
    public function getDashboardData()
    {
        $user = Auth::user();
        
        // Get fresh data
        $devices = Device::whereHas('land', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['sensors', 'land'])->get();
        
        $sensors = Sensor::whereHas('device.land', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['device'])->get();
        
        // Calculate real-time stats
        $stats = [
            'online_devices' => $devices->where('status', 'online')->count(),
            'offline_devices' => $devices->where('status', 'offline')->count(),
            'total_alerts' => $sensors->filter(function($sensor) {
                return $sensor->alert_enabled && $sensor->getAlertStatus() !== 'normal';
            })->count(),
            'recent_activity' => $devices->filter(function($device) {
                return $device->last_seen_at && $device->last_seen_at->isAfter(Carbon::now()->subHour());
            })->count(),
        ];
        
        // Get latest alerts
        $latestAlerts = $sensors->filter(function($sensor) {
            return $sensor->alert_enabled && $sensor->getAlertStatus() !== 'normal';
        })->sortByDesc('reading_timestamp')->take(5)->map(function($sensor) {
            return [
                'id' => $sensor->id,
                'device_name' => $sensor->device->name,
                'sensor_type' => $sensor->sensor_type,
                'value' => $sensor->getFormattedValue(),
                'alert_status' => $sensor->getAlertStatus(),
                'timestamp' => $sensor->reading_timestamp ? Carbon::parse($sensor->reading_timestamp)->diffForHumans() : 'Unknown',
            ];
        })->values();
        
        return response()->json([
            'success' => true,
            'stats' => $stats,
            'alerts' => $latestAlerts,
        ]);
    }
}
