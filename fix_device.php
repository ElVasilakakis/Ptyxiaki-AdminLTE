<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Device;

echo "Fixing device test-lorawan-1 protocol:\n";

$device = Device::where('device_id', 'test-lorawan-1')->first();

if ($device) {
    echo "Current protocol: " . ($device->protocol ?? 'NULL') . "\n";
    $device->protocol = 'lorawan';
    $device->save();
    echo "✅ Device protocol updated to: " . $device->protocol . "\n";
} else {
    echo "❌ Device NOT found!\n";
}
