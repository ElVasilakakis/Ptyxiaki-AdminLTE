<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoRaWANController;
use App\Http\Controllers\WebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// LoRaWAN webhook endpoint (no authentication required for TTN webhooks)
Route::post('/lorawan/webhook', [LoRaWANController::class, 'webhook']);

// Test endpoint to simulate webhook with sample data
Route::post('/lorawan/test-webhook', [LoRaWANController::class, 'testWebhook']);

// MQTT Webhook endpoints (no authentication required for device webhooks)
Route::post('/webhook/mqtt/{deviceId}', [WebhookController::class, 'handleMqttWebhook']);
Route::get('/webhook/mqtt/{deviceId}/instructions', [WebhookController::class, 'getWebhookInstructions']);
Route::post('/webhook/test', [WebhookController::class, 'testWebhook']);

// Live sensor data endpoint (requires web authentication)
Route::middleware('web')->group(function () {
    Route::get('/sensors/live', [App\Http\Controllers\Application\SensorsController::class, 'getLiveSensorData']);
    Route::get('/devices/{device}/sensors', [App\Http\Controllers\Application\DevicesController::class, 'getSensorData']);
    Route::get('/lands/{land}/devices', [App\Http\Controllers\Application\LandsController::class, 'getLiveDeviceData']);
    Route::get('/dashboard/data', [App\Http\Controllers\Application\DashboardController::class, 'getDashboardData']);

    // Notification endpoints
    Route::prefix('notifications')->group(function () {
        Route::get('/', [App\Http\Controllers\Application\NotificationController::class, 'index']);
        Route::get('/unread-count', [App\Http\Controllers\Application\NotificationController::class, 'getUnreadCount']);
        Route::post('/{id}/mark-read', [App\Http\Controllers\Application\NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [App\Http\Controllers\Application\NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [App\Http\Controllers\Application\NotificationController::class, 'destroy']);
        Route::delete('/clear-read', [App\Http\Controllers\Application\NotificationController::class, 'clearRead']);
    });
});
