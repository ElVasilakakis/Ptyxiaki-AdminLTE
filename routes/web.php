<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Application\DashboardController;
use App\Http\Controllers\Application\LandsController;
use App\Http\Controllers\Application\DevicesController;
use App\Http\Controllers\Application\SensorsController;
use App\Http\Controllers\Application\MQTTBrokersController;

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

// Application Routes (Protected by authentication)
Route::group(['prefix' => 'app', 'middleware' => 'auth'], function () {

    Route::get('/', function () {
        return redirect('/app/dashboard');
    });   

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('app.dashboard');

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
    });

    Route::group(['prefix' => 'sensors'], function () {
        Route::get('/', [SensorsController::class, 'index'])->name('app.sensors.index');
    });

    Route::group(['prefix' => 'mqttbrokers'], function () {
        Route::get('/', [MQTTBrokersController::class, 'index'])->name('app.mqttbrokers.index');
        Route::get('/create', [MQTTBrokersController::class, 'create'])->name('app.mqttbrokers.create');
        Route::post('/', [MQTTBrokersController::class, 'store'])->name('app.mqttbrokers.store');
        Route::post('/test-connection-form', [MQTTBrokersController::class, 'testConnectionFromForm'])->name('app.mqttbrokers.test-connection-form');
        Route::get('/{mqttbroker}', [MQTTBrokersController::class, 'show'])->name('app.mqttbrokers.show');
        Route::get('/{mqttbroker}/edit', [MQTTBrokersController::class, 'edit'])->name('app.mqttbrokers.edit');
        Route::put('/{mqttbroker}', [MQTTBrokersController::class, 'update'])->name('app.mqttbrokers.update');
        Route::patch('/{mqttbroker}/toggle-status', [MQTTBrokersController::class, 'toggleStatus'])->name('app.mqttbrokers.toggle-status');
        Route::post('/{mqttbroker}/test-connection', [MQTTBrokersController::class, 'testConnection'])->name('app.mqttbrokers.test-connection');
        Route::delete('/{mqttbroker}', [MQTTBrokersController::class, 'destroy'])->name('app.mqttbrokers.destroy');
    });
});

// Catch all undefined routes and redirect to login for guests, dashboard for authenticated users
Route::fallback(function () {
    if (auth()->check()) {
        return redirect('/app/dashboard');
    }
    return redirect('/login');
});
