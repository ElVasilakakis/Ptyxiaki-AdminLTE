<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'name',
        'device_type',
        'mqtt_broker_id',
        'land_id',
        'user_id',
        'location',
        'status',
        'last_seen_at',
        'installed_at',
        'topics',
        'protocol',
        'mqtt_port',
        'mqtts_port',
        'ws_port',
        'wss_port',
        'configuration',
        'description',
        'is_active',
    ];

    protected $casts = [
        'location' => 'array',
        'last_seen_at' => 'datetime',
        'installed_at' => 'datetime',
        'topics' => 'array',
        'configuration' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'device_type' => 'sensor',
        'status' => 'offline',
        'is_active' => true,
    ];

    /**
     * Get the user that owns the device.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the MQTT broker for the device.
     */
    public function mqttBroker(): BelongsTo
    {
        return $this->belongsTo(MqttBroker::class);
    }

    /**
     * Get the land that the device belongs to.
     */
    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }

    /**
     * Get the sensors for the device.
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }

    /**
     * Scope a query to only include active devices.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include online devices.
     */
    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    /**
     * Scope a query to only include devices for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by device type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('device_type', $type);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if the device is currently online.
     */
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    /**
     * Check if the device is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get the device's coordinates from location GeoJSON.
     */
    public function getCoordinates(): ?array
    {
        if (!$this->location || !isset($this->location['coordinates'])) {
            return null;
        }

        return $this->location['coordinates'];
    }

    /**
     * Get the device's latitude.
     */
    public function getLatitude(): ?float
    {
        $coordinates = $this->getCoordinates();
        return $coordinates ? $coordinates[1] : null;
    }

    /**
     * Get the device's longitude.
     */
    public function getLongitude(): ?float
    {
        $coordinates = $this->getCoordinates();
        return $coordinates ? $coordinates[0] : null;
    }

    /**
     * Update the device's last seen timestamp.
     */
    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * Set the device status to online.
     */
    public function setOnline(): void
    {
        $this->update([
            'status' => 'online',
            'last_seen_at' => now()
        ]);
    }

    /**
     * Set the device status to offline.
     */
    public function setOffline(): void
    {
        $this->update(['status' => 'offline']);
    }
}
