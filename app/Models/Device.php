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
        'land_id',
        'user_id',
        'status',
        'description',
        'is_active',
        'connection_type',
        'client_id',
        'use_ssl',
        'connection_broker',
        'port',
        'username',
        'password',
        'auto_reconnect',
        'max_reconnect_attempts',
        'keepalive',
        'timeout',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'use_ssl' => 'boolean',
        'auto_reconnect' => 'boolean',
    ];

    protected $attributes = [
        'device_type' => 'sensor',
        'connection_type' => 'webhook',
        'status' => 'offline',
        'is_active' => true,
        'use_ssl' => false,
        'auto_reconnect' => true,
        'max_reconnect_attempts' => 3,
        'keepalive' => 60,
        'timeout' => 30,
    ];

    /**
     * Get the user that owns the device.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
