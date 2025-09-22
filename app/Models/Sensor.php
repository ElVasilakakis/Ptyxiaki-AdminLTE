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
