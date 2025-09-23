<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Application\DashboardController;
use App\Http\Controllers\Application\LandsController;
use App\Http\Controllers\Application\DevicesController;
use App\Http\Controllers\Application\SensorsController;
use App\Http\Controllers\LoRaWANController;
        // Import required classes
        use PhpMqtt\Client\MqttClient;
        use PhpMqtt\Client\ConnectionSettings;

Route::get('/debug/lorawan-check', [LoRaWANController::class, 'debugConnection']);
Route::get('/debug/lorawan-simple', [LoRaWANController::class, 'simpleTest']);


// Authentication Routes (Guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login'); // Changed from 'auth.login'
    Route::post('/login', [AuthController::class, 'handleLogin'])->name('auth.login.post');
    Route::get('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/register', [AuthController::class, 'handleRegister'])->name('auth.register.post');
});

// Logout Route (Authenticated users only)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
});

// Redirect root to dashboard
Route::get('/', function () {
    return redirect('/app/dashboard');
});

// Add to routes/web.php
// Add to routes/api.php
// Add to routes/api.php - CORRECTED VERSION
Route::get('/listen-for-mqttx', function () {
    set_time_limit(45);
    
    $output = "=== Listening for MQTTX Messages ===\n";
    $output .= "Time: " . now() . "\n\n";
    
    try {
        $mqtt = new \Bluerhinos\phpMQTT('test.mosquitto.org', 1883, 'mqttx-listener-' . time());
        
        if ($mqtt->connect(true)) {
            $output .= "✅ Laravel connected to Mosquitto\n";
            
            $testTopic = 'mqttx/test/' . time();
            $output .= "📡 Subscribed to: {$testTopic}\n";
            $output .= "🚀 NOW publish from MQTTX to: {$testTopic}\n";
            $output .= "   Message: 'Hello from MQTTX'\n\n";
            
            $messageReceived = false;
            $startTime = time();
            
            // Fixed the subscribe syntax here
            $topics = [
                $testTopic => [
                    'qos' => 0, 
                    'function' => function($topic, $message) use (&$messageReceived, &$output, $startTime) {
                        $receiveTime = time() - $startTime;
                        $output .= "🎉 SUCCESS! Message received after {$receiveTime} seconds!\n";
                        $output .= "Topic: {$topic}\n";
                        $output .= "Message: {$message}\n";
                        $output .= "Timestamp: " . date('H:i:s') . "\n";
                        $messageReceived = true;
                    }
                ]
            ];
            
            $mqtt->subscribe($topics, 0);
            
            // Listen for 30 seconds
            $start = time();
            while ((time() - $start) < 30 && !$messageReceived) {
                $mqtt->proc();
                usleep(100000);
            }
            
            if ($messageReceived) {
                $output .= "\n✅ MQTTX is publishing correctly to Mosquitto!\n";
            } else {
                $output .= "\n❌ No messages received. MQTTX publish may have failed.\n";
                $output .= "Check your MQTTX connection settings.\n";
            }
            
            $mqtt->close();
            
        } else {
            $output .= "❌ Laravel failed to connect to Mosquitto\n";
        }
        
    } catch (\Exception $e) {
        $output .= "❌ Error: " . $e->getMessage() . "\n";
    }
    
    return response($output, 200, ['Content-Type' => 'text/plain']);
});



// Application Routes (Protected by authentication)
Route::group(['prefix' => 'app', 'middleware' => 'auth'], function () {

    Route::get('/', function () {
        return redirect('/app/dashboard');
    });   

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('app.dashboard');
    
    // Documentation Route
    Route::get('/documentation', function () {
        return view('application.documentation');
    })->name('app.documentation');
    

    Route::group(['prefix' => 'lands'], function () {
        Route::get('/', [LandsController::class, 'index'])->name('app.lands.index');
        Route::get('/create', [LandsController::class, 'create'])->name('app.lands.create');
        Route::post('/', [LandsController::class, 'store'])->name('app.lands.store');
        Route::get('/{land}', [LandsController::class, 'show'])->name('app.lands.show');
        Route::get('/{land}/edit', [LandsController::class, 'edit'])->name('app.lands.edit');
        Route::put('/{land}', [LandsController::class, 'update'])->name('app.lands.update');
        Route::patch('/{land}/toggle-status', [LandsController::class, 'toggleStatus'])->name('app.lands.toggle-status');
        Route::delete('/{land}', [LandsController::class, 'destroy'])->name('app.lands.destroy');
    });

    Route::group(['prefix' => 'devices'], function () {
        Route::get('/', [DevicesController::class, 'index'])->name('app.devices.index');
        Route::get('/create', [DevicesController::class, 'create'])->name('app.devices.create');
        Route::post('/', [DevicesController::class, 'store'])->name('app.devices.store');
        Route::get('/{device}', [DevicesController::class, 'show'])->name('app.devices.show');
        Route::get('/{device}/edit', [DevicesController::class, 'edit'])->name('app.devices.edit');
        Route::put('/{device}', [DevicesController::class, 'update'])->name('app.devices.update');
        Route::patch('/{device}/toggle-status', [DevicesController::class, 'toggleStatus'])->name('app.devices.toggle-status');
        Route::patch('/{device}/update-status', [DevicesController::class, 'updateStatus'])->name('app.devices.update-status');
        Route::delete('/{device}', [DevicesController::class, 'destroy'])->name('app.devices.destroy');
        
        // MQTT Sensor Data Routes
        Route::post('/{device}/store-sensors', [DevicesController::class, 'storeSensors'])->name('app.devices.store-sensors');
        Route::get('/{device}/alerts', [DevicesController::class, 'getAlerts'])->name('app.devices.alerts');
        Route::get('/{device}/sensor-data', [DevicesController::class, 'getSensorData'])->name('app.devices.sensor-data');
        
        // LoRaWAN Data Polling Route
        Route::post('/{device}/poll-lorawan', [DevicesController::class, 'pollLorawanData'])->name('app.devices.poll-lorawan');
        
        // MQTT Control Routes
        Route::post('/{device}/mqtt/start', [\App\Http\Controllers\Application\MQTTController::class, 'startListener'])->name('app.devices.mqtt.start');
        Route::get('/{device}/mqtt/test', [\App\Http\Controllers\Application\MQTTController::class, 'testDevice'])->name('app.devices.mqtt.test');
    });

    Route::group(['prefix' => 'sensors'], function () {
        Route::get('/', [SensorsController::class, 'index'])->name('app.sensors.index');
        Route::get('/{sensor}/edit', [SensorsController::class, 'edit'])->name('app.sensors.edit');
        Route::put('/{sensor}', [SensorsController::class, 'update'])->name('app.sensors.update');
        Route::post('/store', [SensorsController::class, 'store'])->name('app.sensors.store');
    });

});

// Catch all undefined routes and redirect to login for guests, dashboard for authenticated users
Route::fallback(function () {
    if (auth()->check()) {
        return redirect('/app/dashboard');
    }
    return redirect('/login');
});
