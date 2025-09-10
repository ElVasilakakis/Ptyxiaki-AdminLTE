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
        'location',
        'unit',
        'thresholds',
        'value',
        'reading_timestamp',
        'enabled',
        'alert_enabled',
        'alert_threshold_min',
        'alert_threshold_max',
    ];

    protected $casts = [
        'thresholds' => 'array',
        'value' => 'json',
        'reading_timestamp' => 'datetime',
        'enabled' => 'boolean',
        'alert_enabled' => 'boolean',
        'alert_threshold_min' => 'float',
        'alert_threshold_max' => 'float',
    ];

    protected $dates = [
        'deleted_at',
    ];

    protected $attributes = [
        'enabled' => true,
        'alert_enabled' => false,
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
     * Scope a query to only include sensors with alerts enabled.
     */
    public function scopeWithAlertsEnabled($query)
    {
        return $query->where('alert_enabled', true);
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
     * Check if alerts are enabled for this sensor.
     */
    public function hasAlertsEnabled(): bool
    {
        return $this->alert_enabled;
    }

    /**
     * Check if the current value is within alert thresholds.
     */
    public function isValueInAlertRange(): bool
    {
        if (!$this->hasAlertsEnabled() || $this->value === null) {
            return false;
        }

        // Only check thresholds for numeric values
        if (!is_numeric($this->value)) {
            return false;
        }

        $numericValue = (float) $this->value;
        $belowMin = $this->alert_threshold_min !== null && $numericValue < $this->alert_threshold_min;
        $aboveMax = $this->alert_threshold_max !== null && $numericValue > $this->alert_threshold_max;

        return $belowMin || $aboveMax;
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
     * Get alert status based on current value.
     */
    public function getAlertStatus(): string
    {
        if (!$this->hasAlertsEnabled() || $this->value === null) {
            return 'normal';
        }

        // Only check thresholds for numeric values
        if (!is_numeric($this->value)) {
            return 'normal';
        }

        $numericValue = (float) $this->value;

        if ($this->alert_threshold_min !== null && $numericValue < $this->alert_threshold_min) {
            return 'low';
        }

        if ($this->alert_threshold_max !== null && $numericValue > $this->alert_threshold_max) {
            return 'high';
        }

        return 'normal';
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
