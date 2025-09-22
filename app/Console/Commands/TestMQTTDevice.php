<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;

class TestMQTTDevice extends Command
{
    protected $signature = 'mqtt:test {device_id}';
    protected $description = 'Test MQTT device configuration';

    public function handle()
    {
        $deviceId = $this->argument('device_id');

        // Find the device
        $device = Device::where('device_id', $deviceId)->first();

        if (!$device) {
            $this->error("Device with ID '{$deviceId}' not found.");
            $this->info("Available devices:");
            $devices = Device::all();
            foreach ($devices as $dev) {
                $this->info("- {$dev->device_id} ({$dev->name}) - {$dev->connection_type}");
            }
            return 1;
        }

        $this->info("Device found: {$device->name}");
        $this->info("Connection Type: {$device->connection_type}");
        $this->info("MQTT Host: " . ($device->mqtt_host ?: 'NOT SET'));
        $this->info("MQTT Topics: " . (is_array($device->mqtt_topics) ? implode(', ', $device->mqtt_topics) : 'NOT SET'));
        $this->info("Port: " . ($device->port ?: 'DEFAULT'));
        $this->info("SSL: " . ($device->use_ssl ? 'YES' : 'NO'));
        $this->info("Username: " . ($device->username ?: 'NOT SET'));
        $this->info("Status: {$device->status}");

        if ($device->connection_type !== 'mqtt') {
            $this->warn("Device is not configured as MQTT device!");
            return 1;
        }

        if (!$device->mqtt_host) {
            $this->error("MQTT Host is not configured!");
            return 1;
        }

        if (!$device->mqtt_topics || empty($device->mqtt_topics)) {
            $this->error("MQTT Topics are not configured!");
            return 1;
        }

        $this->info("Device configuration looks good!");
        $this->info("You can now run: php artisan mqtt:listen {$deviceId}");

        return 0;
    }
}
