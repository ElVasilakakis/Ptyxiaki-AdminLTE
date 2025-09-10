<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoRaWANController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// LoRaWAN webhook endpoint (no authentication required for TTN webhooks)
Route::post('/lorawan/webhook', [LoRaWANController::class, 'webhook']);

// Test endpoint to simulate webhook with sample data
Route::post('/lorawan/test-webhook', [LoRaWANController::class, 'testWebhook']);
