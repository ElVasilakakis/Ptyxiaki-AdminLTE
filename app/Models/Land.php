<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Land extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'land_name',
        'user_id',
        'location',
        'data',
        'geojson',
        'color',
        'enabled',
    ];

    protected $casts = [
        'location' => 'array',
        'data' => 'array',
        'geojson' => 'array',
        'enabled' => 'boolean',
    ];

    protected $dates = [
        'deleted_at',
    ];

    /**
     * Get the user that owns the land.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the devices for the land.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Scope a query to only include enabled lands.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope a query to only include lands for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
