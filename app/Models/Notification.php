<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'sensor_id',
        'device_id',
        'type',
        'title',
        'message',
        'data',
        'severity',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    // Methods
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function getSeverityColorAttribute()
    {
        return match($this->severity) {
            'critical' => 'danger',
            'error' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            default => 'secondary',
        };
    }

    public function getSeverityIconAttribute()
    {
        return match($this->severity) {
            'critical' => 'ph-warning-octagon',
            'error' => 'ph-x-circle',
            'warning' => 'ph-warning-circle',
            'info' => 'ph-info',
            default => 'ph-bell',
        };
    }

    // Static methods for creating notifications
    public static function createThresholdAlert($sensor, $currentValue, $thresholdType)
    {
        $device = $sensor->device;
        $user = $device->land->user;
        
        $severity = $thresholdType === 'critical' ? 'critical' : 'warning';
        $title = "Sensor Alert: {$sensor->sensor_type}";
        $message = "Device '{$device->name}' sensor '{$sensor->sensor_type}' has exceeded threshold. Current value: {$currentValue}";
        
        return self::create([
            'user_id' => $user->id,
            'sensor_id' => $sensor->id,
            'device_id' => $device->id,
            'type' => 'threshold_exceeded',
            'title' => $title,
            'message' => $message,
            'data' => [
                'current_value' => $currentValue,
                'threshold_min' => $sensor->alert_threshold_min,
                'threshold_max' => $sensor->alert_threshold_max,
                'sensor_type' => $sensor->sensor_type,
                'device_name' => $device->name,
                'land_name' => $device->land->land_name,
            ],
            'severity' => $severity,
        ]);
    }

    public static function createDeviceOfflineAlert($device)
    {
        $user = $device->land->user;
        
        return self::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'type' => 'device_offline',
            'title' => "Device Offline",
            'message' => "Device '{$device->name}' has gone offline.",
            'data' => [
                'device_name' => $device->name,
                'land_name' => $device->land->land_name,
                'last_seen' => $device->last_seen_at?->toISOString(),
            ],
            'severity' => 'warning',
        ]);
    }

    public static function createGeofenceViolationAlert($device, $coordinates)
    {
        $user = $device->land->user;
        
        return self::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'type' => 'geofence_violation',
            'title' => "Geofence Violation",
            'message' => "Device '{$device->name}' is outside the designated land boundary.",
            'data' => [
                'device_name' => $device->name,
                'land_name' => $device->land->land_name,
                'coordinates' => $coordinates,
            ],
            'severity' => 'error',
        ]);
    }
}
