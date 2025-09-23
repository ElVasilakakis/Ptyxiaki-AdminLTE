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
Route::get('/test-mqtt-protocol', function () {
    set_time_limit(45);
    
    $output = "=== MQTT Protocol Test ===\n";
    $output .= "Time: " . now() . "\n\n";
    
    try {
        $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings())
            ->setUsername('mqttuser')
            ->setPassword('12345678')
            ->setConnectTimeout(15);
        
        $mqtt = new \PhpMqtt\Client\MqttClient('broker.emqx.io', 1883, 'ploi-protocol-test-' . time());
        
        $output .= "Attempting MQTT connection to broker.emqx.io...\n";
        $start = microtime(true);
        
        $mqtt->connect($connectionSettings, true);
        
        $connectTime = round((microtime(true) - $start) * 1000, 2);
        $output .= "✅ MQTT Connected successfully in {$connectTime}ms!\n";
        
        // Test simple subscription
        $testTopic = 'ploi/protocol/test/' . time();
        $output .= "📡 Subscribed to: {$testTopic}\n";
        $output .= "Now publish to this topic from MQTTX to test!\n";
        
        $messageReceived = false;
        $mqtt->subscribe($testTopic, function($topic, $message) use (&$messageReceived, &$output) {
            $output .= "📨 RECEIVED MESSAGE!\n";
            $output .= "Topic: {$topic}\n";
            $output .= "Message: {$message}\n";
            $messageReceived = true;
        }, 0);
        
        // Listen for 15 seconds
        $startListen = time();
        while ((time() - $startListen) < 15 && !$messageReceived) {
            $mqtt->loop(true);
            usleep(100000);
        }
        
        if ($messageReceived) {
            $output .= "🎉 SUCCESS! Ploi server can receive MQTT messages from EMQX!\n";
        } else {
            $output .= "⏰ Timeout: No messages received in 15 seconds\n";
            $output .= "Try publishing to: {$testTopic}\n";
        }
        
        $mqtt->disconnect();
        $output .= "✅ Disconnected successfully\n";
        
    } catch (\Exception $e) {
        $output .= "❌ MQTT Protocol Error: " . $e->getMessage() . "\n";
        $output .= "Error Code: " . $e->getCode() . "\n";
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
