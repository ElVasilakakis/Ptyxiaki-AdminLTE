<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\Sensor;

class TestSensorUpdates extends Command
{
    protected $signature = 'test:sensor-updates {--device=}';
    protected $description = 'Test and display current sensor data for MQTT devices';

    public function handle(): int
    {
        $deviceFilter = $this->option('device');
        
        // Get MQTT devices
        $query = Device::where('connection_type', 'mqtt')
            ->where('is_active', true)
            ->with(['sensors' => function($query) {
                $query->orderBy('sensor_type');
            }]);

        if ($deviceFilter) {
            $query->where('device_id', 'like', "%{$deviceFilter}%");
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->error("No MQTT devices found.");
            return 1;
        }

        $this->info("📊 Current Sensor Data for MQTT Devices");
        $this->info("=" . str_repeat("=", 50));

        foreach ($devices as $device) {
            $this->info("\n🔧 Device: {$device->name} ({$device->device_id})");
            $this->info("   Status: {$device->status} | Last seen: " . ($device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never'));
            $this->info("   Host: {$device->mqtt_host} | Topics: " . implode(', ', $device->mqtt_topics ?? []));
            
            $sensors = $device->sensors;
            
            if ($sensors->isEmpty()) {
                $this->warn("   └─ No sensors found for this device");
            } else {
                $this->info("   └─ Sensors ({$sensors->count()}):");
                
                foreach ($sensors as $sensor) {
                    $status = $sensor->enabled ? '✅' : '❌';
                    $alertStatus = $sensor->getAlertStatus();
                    $alertIcon = $alertStatus === 'normal' ? '🟢' : ($alertStatus === 'high' ? '🔴' : '🟡');
                    
                    $this->info("      {$status} {$sensor->sensor_type}: {$sensor->getFormattedValue()} {$alertIcon}");
                    
                    if ($sensor->reading_timestamp) {
                        $this->info("         Last reading: {$sensor->reading_timestamp->format('Y-m-d H:i:s')} ({$sensor->getTimeSinceLastReading()})");
                    } else {
                        $this->warn("         No readings yet");
                    }
                    
                    if ($sensor->min_threshold !== null || $sensor->max_threshold !== null) {
                        $min = $sensor->min_threshold ?? 'none';
                        $max = $sensor->max_threshold ?? 'none';
                        $this->info("         Thresholds: {$min} - {$max}");
                    }
                }
            }
        }

        // Summary statistics
        $totalSensors = $devices->sum(function($device) {
            return $device->sensors->count();
        });
        
        $recentSensors = $devices->sum(function($device) {
            return $device->sensors->filter(function($sensor) {
                return $sensor->hasRecentReading();
            })->count();
        });

        $this->info("\n📈 Summary:");
        $this->info("   Total devices: {$devices->count()}");
        $this->info("   Total sensors: {$totalSensors}");
        $this->info("   Recent readings (last hour): {$recentSensors}");
        
        if ($totalSensors > 0) {
            $percentage = round(($recentSensors / $totalSensors) * 100, 1);
            $this->info("   Activity rate: {$percentage}%");
        }

        return 0;
    }
}
