<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the lands for the user.
     */
    public function lands(): HasMany
    {
        return $this->hasMany(Land::class);
    }

    /**
     * Get the devices for the user.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Get the sensors for the user.
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }

    /**
     * Get the active lands for the user.
     */
    public function activeLands(): HasMany
    {
        return $this->lands()->enabled();
    }


    /**
     * Get the active devices for the user.
     */
    public function activeDevices(): HasMany
    {
        return $this->devices()->active();
    }

    /**
     * Get the enabled sensors for the user.
     */
    public function enabledSensors(): HasMany
    {
        return $this->sensors()->enabled();
    }

    /**
     * Get the online devices for the user.
     */
    public function onlineDevices(): HasMany
    {
        return $this->devices()->online();
    }
}
