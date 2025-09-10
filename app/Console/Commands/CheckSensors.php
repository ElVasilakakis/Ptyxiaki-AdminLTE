<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\Sensor;

class CheckSensors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sensors:check {--device=test-lorawan-1 : Device ID to check sensors for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check current sensor readings for a LoRaWAN device';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deviceId = $this->option('device');
        
        $this->info("🔍 Checking sensors for device: {$deviceId}");
        $this->line("");
        
        // Find the device
        $device = Device::where('device_id', $deviceId)->first();
        
        if (!$device) {
            $this->error("❌ Device '{$deviceId}' not found in database");
            return 1;
        }
        
        // Get all sensors for this device
        $sensors = Sensor::where('device_id', $device->id)
            ->orderBy('sensor_type')
            ->get();
        
        if ($sensors->isEmpty()) {
            $this->warn("⚠️ No sensors found for device '{$deviceId}'");
            return 0;
        }
        
        // Display device info
        $this->info("📱 Device Information:");
        $this->line("   ID: {$device->device_id}");
        $this->line("   Name: {$device->name}");
        $this->line("   Status: " . ($device->isOnline() ? '🟢 Online' : '🔴 Offline'));
        $this->line("   Last Seen: " . ($device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never'));
        $this->line("");
        
        // Display sensors in a table
        $this->info("📊 Current Sensor Readings:");
        
        $tableData = [];
        foreach ($sensors as $sensor) {
            $tableData[] = [
                'Type' => $sensor->sensor_type,
                'Name' => $sensor->sensor_name,
                'Value' => $sensor->getFormattedValue(),
                'Last Updated' => $sensor->reading_timestamp ? $sensor->reading_timestamp->diffForHumans() : 'Never',
                'Status' => $sensor->isEnabled() ? '✅ Enabled' : '❌ Disabled'
            ];
        }
        
        $this->table(['Type', 'Name', 'Value', 'Last Updated', 'Status'], $tableData);
        
        // Show summary
        $this->line("");
        $this->info("📈 Summary:");
        $this->line("   Total Sensors: " . $sensors->count());
        $this->line("   Enabled: " . $sensors->where('enabled', true)->count());
        $this->line("   With Recent Data: " . $sensors->filter(function($sensor) {
            return $sensor->hasRecentReading();
        })->count());
        
        return 0;
    }
}
