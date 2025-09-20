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
        'description',
        'status',
        'user_id',
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
        'last_connected_at',
        'connection_error',
        'auto_reconnect',
        'max_reconnect_attempts',
    ];

    protected $casts = [
        'use_ssl' => 'boolean',
        'auto_reconnect' => 'boolean',
        'port' => 'integer',
        'websocket_port' => 'integer',
        'ssl_port' => 'integer',
        'keepalive' => 'integer',
        'timeout' => 'integer',
        'max_reconnect_attempts' => 'integer',
        'certificates' => 'array',
        'additional_config' => 'array',
        'last_connected_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => 'webhook',
        'status' => 'active',
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
     * Get the broker endpoint URL.
     */
    public function getEndpoint(): string
    {
        $protocol = $this->use_ssl ? 'mqtts' : 'mqtt';
        $port = $this->use_ssl && $this->ssl_port ? $this->ssl_port : $this->port;

        return "{$protocol}://{$this->host}:{$port}";
    }

    /**
     * Get the connection port based on SSL settings.
     */
    public function getConnectionPort(): int
    {
        return $this->use_ssl && $this->ssl_port ? $this->ssl_port : $this->port;
    }

    /**
     * Check if the broker uses SSL.
     */
    public function usesSsl(): bool
    {
        return (bool) $this->use_ssl;
    }

}
