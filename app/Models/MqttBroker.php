<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MqttBroker extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'host',
        'port',
        'websocket_port',
        'path',
        'username',
        'password',
        'use_ssl',
        'ssl_port',
        'client_id',
        'keepalive',
        'timeout',
        'certificates',
        'additional_config',
        'status',
        'last_connected_at',
        'connection_error',
        'auto_reconnect',
        'max_reconnect_attempts',
        'description',
        'user_id',
    ];

    protected $casts = [
        'port' => 'integer',
        'websocket_port' => 'integer',
        'use_ssl' => 'boolean',
        'ssl_port' => 'integer',
        'keepalive' => 'integer',
        'timeout' => 'integer',
        'certificates' => 'array',
        'additional_config' => 'array',
        'last_connected_at' => 'datetime',
        'auto_reconnect' => 'boolean',
        'max_reconnect_attempts' => 'integer',
    ];

    protected $attributes = [
        'type' => 'mosquitto',
        'port' => 1883,
        'use_ssl' => false,
        'keepalive' => 60,
        'timeout' => 30,
        'status' => 'inactive',
        'auto_reconnect' => true,
        'max_reconnect_attempts' => 5,
    ];

    /**
     * Get the user that owns the MQTT broker.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the devices for the MQTT broker.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Scope a query to only include active brokers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include brokers for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by broker type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if the broker is currently connected.
     */
    public function isConnected(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the broker uses SSL.
     */
    public function usesSsl(): bool
    {
        return $this->use_ssl;
    }

    /**
     * Get the connection port (SSL or regular).
     */
    public function getConnectionPort(): int
    {
        return $this->use_ssl && $this->ssl_port ? $this->ssl_port : $this->port;
    }

    /**
     * Get the full broker endpoint.
     */
    public function getEndpoint(): string
    {
        $protocol = $this->use_ssl ? 'mqtts' : 'mqtt';
        return "{$protocol}://{$this->host}:{$this->getConnectionPort()}";
    }
}
