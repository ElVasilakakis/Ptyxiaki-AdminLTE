<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sensor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'device_id',
        'user_id',
        'sensor_type',
        'sensor_name',
        'description',
        'unit',
        'min_threshold',
        'max_threshold',
        'value',
        'reading_timestamp',
        'enabled',
    ];

    protected $casts = [
        'value' => 'json',
        'reading_timestamp' => 'datetime',
        'enabled' => 'boolean',
        'min_threshold' => 'float',
        'max_threshold' => 'float',
    ];

    protected $dates = [
        'deleted_at',
    ];

    protected $attributes = [
        'enabled' => true,
    ];

    /**
     * Get the device that owns the sensor.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the user that owns the sensor.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include enabled sensors.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope a query to only include sensors for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by sensor type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('sensor_type', $type);
    }

    /**
     * Scope a query to only include sensors for a specific device.
     */
    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Check if the sensor is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if the current value is within thresholds.
     */
    public function isValueInRange(): bool
    {
        if ($this->value === null) {
            return true;
        }

        // Only check thresholds for numeric values
        if (!is_numeric($this->value)) {
            return true;
        }

        $numericValue = (float) $this->value;
        $belowMin = $this->min_threshold !== null && $numericValue < $this->min_threshold;
        $aboveMax = $this->max_threshold !== null && $numericValue > $this->max_threshold;

        return !($belowMin || $aboveMax);
    }

    /**
     * Update the sensor reading.
     */
    public function updateReading($value, $timestamp = null): void
    {
        $this->update([
            'value' => $value,
            'reading_timestamp' => $timestamp ?: now(),
        ]);
    }

    /**
     * Get the formatted value with unit.
     */
    public function getFormattedValue(): string
    {
        if ($this->value === null) {
            return 'No reading';
        }

        // Handle JSON/array values
        if (is_array($this->value) || is_object($this->value)) {
            return json_encode($this->value);
        }

        // Handle numeric values
        if (is_numeric($this->value)) {
            $unit = $this->unit ? " {$this->unit}" : '';
            return number_format((float) $this->value, 2) . $unit;
        }

        // Handle string values
        $unit = $this->unit ? " {$this->unit}" : '';
        return $this->value . $unit;
    }

    /**
     * Get the time since last reading.
     */
    public function getTimeSinceLastReading(): ?string
    {
        if (!$this->reading_timestamp) {
            return null;
        }

        return $this->reading_timestamp->diffForHumans();
    }

    /**
     * Check if the sensor has recent readings (within last hour).
     */
    public function hasRecentReading(): bool
    {
        if (!$this->reading_timestamp) {
            return false;
        }

        return $this->reading_timestamp->isAfter(now()->subHour());
    }

    /**
     * Get threshold status based on current value.
     */
    public function getAlertStatus(): string
    {
        if ($this->value === null) {
            return 'normal';
        }

        // Check for geofence violations for GPS sensors
        if (in_array($this->sensor_type, ['latitude', 'longitude'])) {
            return $this->checkGeofenceStatus();
        }

        // Only check thresholds for numeric values
        if (!is_numeric($this->value)) {
            return 'normal';
        }

        $numericValue = (float) $this->value;

        if ($this->min_threshold !== null && $numericValue < $this->min_threshold) {
            return 'low';
        }

        if ($this->max_threshold !== null && $numericValue > $this->max_threshold) {
            return 'high';
        }

        return 'normal';
    }

    /**
     * Check if device is outside geofence (for GPS sensors)
     */
    private function checkGeofenceStatus(): string
    {
        // Get the device and its land
        $device = $this->device;
        if (!$device || !$device->land || !$device->land->geojson) {
            return 'normal'; // No geofence defined
        }

        // Get both latitude and longitude sensors for this device
        $latSensor = $device->sensors->where('sensor_type', 'latitude')->first();
        $lngSensor = $device->sensors->where('sensor_type', 'longitude')->first();

        if (!$latSensor || !$lngSensor || !$latSensor->value || !$lngSensor->value) {
            return 'normal'; // No complete GPS coordinates
        }

        $lat = (float) $latSensor->value;
        $lng = (float) $lngSensor->value;

        if (!is_numeric($lat) || !is_numeric($lng)) {
            return 'normal';
        }

        // Check if point is inside the land polygon
        if ($this->isPointInsidePolygon($lat, $lng, $device->land->geojson)) {
            return 'normal'; // Inside geofence
        } else {
            return 'high'; // Outside geofence - treat as high alert
        }
    }

    /**
     * Check if a point is inside a polygon using ray casting algorithm
     */
    private function isPointInsidePolygon(float $lat, float $lng, array $geojson): bool
    {
        try {
            // Handle different GeoJSON geometry types
            if (isset($geojson['type'])) {
                if ($geojson['type'] === 'Polygon') {
                    return $this->pointInPolygon([$lng, $lat], $geojson['coordinates'][0]);
                } elseif ($geojson['type'] === 'MultiPolygon') {
                    foreach ($geojson['coordinates'] as $polygon) {
                        if ($this->pointInPolygon([$lng, $lat], $polygon[0])) {
                            return true;
                        }
                    }
                    return false;
                }
            }

            // If it's a FeatureCollection or Feature, extract the geometry
            if (isset($geojson['features']) && is_array($geojson['features'])) {
                foreach ($geojson['features'] as $feature) {
                    if (isset($feature['geometry'])) {
                        return $this->isPointInsidePolygon($lat, $lng, $feature['geometry']);
                    }
                }
            } elseif (isset($geojson['geometry'])) {
                return $this->isPointInsidePolygon($lat, $lng, $geojson['geometry']);
            }

            return true; // Default to inside if we can't determine
        } catch (\Exception $e) {
            \Log::warning('Error checking geofence for sensor ' . $this->id . ': ' . $e->getMessage());
            return true; // Default to inside on error
        }
    }

    /**
     * Ray casting algorithm to determine if point is inside polygon
     */
    private function pointInPolygon(array $point, array $polygon): bool
    {
        $x = $point[0];
        $y = $point[1];
        $inside = false;

        $j = count($polygon) - 1;
        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];

            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
            $j = $i;
        }

        return $inside;
    }

    public function getFormattedValueAttribute(): string
    {
        return $this->getFormattedValue();
    }

    /**
     * Get the alert status attribute
     */
    public function getAlertStatusAttribute(): string
    {
        return $this->getAlertStatus();
    }
}
