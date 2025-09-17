@extends('layouts.application.app')

@section('title', 'How to LoRaWAN')

@section('content')
<div class="content">
    <div class="page-header page-header-light shadow">
        <div class="page-header-content d-lg-flex">
            <div class="d-flex">
                <h4 class="page-title mb-0">
                    How to LoRaWAN
                </h4>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Simple Steps to Get Started with LoRaWAN</h5>
                        <p class="text-muted mb-0">Easy guide for beginners - no programming experience needed!</p>
                    </div>
                    <div class="card-body">
                        
                        <!-- What is LoRaWAN -->
                        <div class="alert alert-primary mb-4">
                            <div class="d-flex align-items-center">
                                <i class="ph-broadcast fs-3 me-3"></i>
                                <div>
                                    <h6 class="fw-semibold mb-1">What is LoRaWAN?</h6>
                                    <p class="mb-0">LoRaWAN lets your sensors send data wirelessly over long distances (up to 10km) using very little power. Perfect for outdoor sensors!</p>
                                </div>
                            </div>
                        </div>

                        <!-- Step-by-step guide -->
                        <div class="row">
                            <div class="col-12">
                                
                                <!-- Step 1 -->
                                <div class="card border-primary mb-4">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <span class="badge bg-white text-primary me-2">1</span>
                                            Add MQTT Broker
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">First, you need a way to receive data from your LoRaWAN device.</p>
                                        <div class="d-flex align-items-start">
                                            <i class="ph-arrow-right text-primary me-2 mt-1"></i>
                                            <div>
                                                <p class="mb-2">Go to <a href="{{ route('app.mqttbrokers.create') }}" class="fw-semibold">MQTT Brokers → Add New</a></p>
                                                <p class="mb-2">Fill in these details:</p>
                                                <ul class="list-unstyled ms-3">
                                                    <li class="mb-1">• <strong>Name:</strong> "My LoRaWAN Broker"</li>
                                                    <li class="mb-1">• <strong>Host:</strong> Your broker address (like: eu1.cloud.thethings.network)</li>
                                                    <li class="mb-1">• <strong>Port:</strong> 1883</li>
                                                    <li class="mb-1">• <strong>Username:</strong> Your TTN username</li>
                                                    <li class="mb-1">• <strong>Password:</strong> Your TTN password</li>
                                                </ul>
                                                <p class="mb-0 text-success">✓ Click "Create Broker"</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 2 -->
                                <div class="card border-success mb-4">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0">
                                            <span class="badge bg-white text-success me-2">2</span>
                                            Add Your Device
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">Now register your LoRaWAN sensor in the system.</p>
                                        <div class="d-flex align-items-start">
                                            <i class="ph-arrow-right text-success me-2 mt-1"></i>
                                            <div>
                                                <p class="mb-2">Go to <a href="{{ route('app.devices.create') }}" class="fw-semibold">Devices → Add New</a></p>
                                                <p class="mb-2">Fill in these details:</p>
                                                <ul class="list-unstyled ms-3">
                                                    <li class="mb-1">• <strong>Name:</strong> "My Sensor" (any name you like)</li>
                                                    <li class="mb-1">• <strong>Land:</strong> Choose where your sensor is located</li>
                                                    <li class="mb-1">• <strong>Protocol:</strong> Select "LoRaWAN"</li>
                                                    <li class="mb-1">• <strong>Device ID:</strong> Your device's DevEUI (from device label)</li>
                                                    <li class="mb-1">• <strong>Application ID:</strong> Your AppEUI (from TTN console)</li>
                                                </ul>
                                                <p class="mb-0 text-success">✓ Click "Create Device"</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 3 -->
                                <div class="card border-warning mb-4">
                                    <div class="card-header bg-warning text-white">
                                        <h6 class="mb-0">
                                            <span class="badge bg-white text-warning me-2">3</span>
                                            Configure Webhook
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">Connect The Things Network to send data to your system.</p>
                                        <div class="d-flex align-items-start">
                                            <i class="ph-arrow-right text-warning me-2 mt-1"></i>
                                            <div>
                                                <p class="mb-2">In The Things Network Console:</p>
                                                <ul class="list-unstyled ms-3">
                                                    <li class="mb-1">• Go to your Application</li>
                                                    <li class="mb-1">• Click "Integrations" → "Webhooks"</li>
                                                    <li class="mb-1">• Add new webhook</li>
                                                    <li class="mb-1">• <strong>Base URL:</strong> {{ url('/') }}</li>
                                                    <li class="mb-1">• <strong>Uplink message:</strong> /api/lorawan/webhook</li>
                                                    <li class="mb-1">• <strong>Format:</strong> JSON</li>
                                                </ul>
                                                <p class="mb-0 text-success">✓ Save the webhook</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 4 -->
                                <div class="card border-info mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0">
                                            <span class="badge bg-white text-info me-2">4</span>
                                            You're Ready!
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <i class="ph-check-circle text-success fs-2 me-3"></i>
                                            <div>
                                                <h6 class="fw-semibold text-success mb-2">Congratulations!</h6>
                                                <p class="mb-2">Your LoRaWAN device is now connected and ready to send data.</p>
                                                <p class="mb-0">Check your <a href="{{ route('app.devices.index') }}" class="fw-semibold">Devices page</a> to see incoming sensor data!</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Payload Documentation -->
                        <div class="mt-5">
                            <h6 class="fw-semibold text-secondary mb-3">
                                <i class="ph-code me-2"></i>Payload Format Information
                            </h6>
                            
                            <div class="alert alert-secondary">
                                <h6 class="fw-semibold mb-2">Expected LoRaWAN Payload Format</h6>
                                <p class="mb-2">Your LoRaWAN device should send data in this format through The Things Network:</p>
                                
                                <div class="bg-dark text-light p-3 rounded mb-3">
                                    <pre class="mb-0"><code style="color: black">{
  "end_device_ids": {
    "device_id": "your-device-id",
    "application_ids": {
      "application_id": "your-app-id"
    }
  },
  "uplink_message": {
    "decoded_payload": {
      "temperature": 25.6,
      "humidity": 65.2,
      "battery": 85,
      "altitude": 150,
      "latitude": 38.170284,
      "longitude": -119.076024,
      "gps_fix": 2,
      "gps_fix_type": "3D Fix"
    }
  },
  "received_at": "2025-09-17T09:19:00.730681063Z"
}</code></pre>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Supported Sensor Types:</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-1">• <strong>temperature</strong> - Temperature in °C</li>
                                            <li class="mb-1">• <strong>humidity</strong> - Humidity in %</li>
                                            <li class="mb-1">• <strong>battery</strong> - Battery level in %</li>
                                            <li class="mb-1">• <strong>altitude</strong> - Altitude in meters</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">GPS Data:</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-1">• <strong>latitude</strong> - GPS latitude in degrees</li>
                                            <li class="mb-1">• <strong>longitude</strong> - GPS longitude in degrees</li>
                                            <li class="mb-1">• <strong>gps_fix</strong> - GPS fix quality (0-3)</li>
                                            <li class="mb-1">• <strong>gps_fix_type</strong> - Fix type description</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="ph-info me-2"></i>
                                    <strong>Note:</strong> The system automatically detects and creates sensors for any data in the decoded_payload. Unknown sensor types will be created with generic names and units.
                                </div>
                            </div>
                        </div>

                        <!-- Quick Tips -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="alert alert-light">
                                    <h6 class="fw-semibold mb-2">
                                        <i class="ph-lightbulb text-warning me-2"></i>Quick Tips
                                    </h6>
                                    <ul class="mb-0">
                                        <li>Keep your device close to a window for better signal</li>
                                        <li>Check battery level regularly</li>
                                        <li>Data may take 1-2 minutes to appear</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-light">
                                    <h6 class="fw-semibold mb-2">
                                        <i class="ph-question text-info me-2"></i>Need Help?
                                    </h6>
                                    <ul class="mb-0">
                                        <li>Check if your device is powered on</li>
                                        <li>Verify all IDs are entered correctly</li>
                                        <li>Make sure webhook URL is correct</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- What's Next -->
                        <div class="text-center mt-4">
                            <div class="alert alert-success">
                                <h6 class="fw-semibold mb-2">What's Next?</h6>
                                <p class="mb-2">Once your device is sending data, you can:</p>
                                <div class="d-flex justify-content-center gap-3 flex-wrap">
                                    <a href="{{ route('app.devices.index') }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ph-list me-1"></i>View All Devices
                                    </a>
                                    <a href="{{ route('app.sensors.index') }}" class="btn btn-sm btn-outline-success">
                                        <i class="ph-thermometer-hot me-1"></i>Monitor Sensors
                                    </a>
                                    <a href="{{ route('app.dashboard') }}" class="btn btn-sm btn-outline-info">
                                        <i class="ph-house me-1"></i>Go to Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
