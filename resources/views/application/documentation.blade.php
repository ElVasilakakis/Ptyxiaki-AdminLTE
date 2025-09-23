@extends('layouts.application.app')

@section('title', 'How to Use the System')

@section('content')
<div class="content">
    <div class="page-header page-header-light shadow">
        <div class="page-header-content d-lg-flex">
            <div class="d-flex">
                <h4 class="page-title mb-0">
                    <i class="ph-book-open me-2"></i>How to Use the System
                </h4>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">IoT Device Management - User Guide</h5>
                        <p class="text-muted mb-0">Simple step-by-step instructions for monitoring your IoT devices</p>
                    </div>
                    <div class="card-body">
                        
                        <!-- What This System Does -->
                        <div class="alert alert-primary mb-4">
                            <div class="d-flex align-items-center">
                                <i class="ph-gear fs-3 me-3"></i>
                                <div>
                                    <h6 class="fw-semibold mb-1">🏠 What This System Does</h6>
                                    <ul class="mb-0">
                                        <li><strong>Monitor your devices:</strong> Track temperature, humidity, location, and other sensors</li>
                                        <li><strong>Map visualization:</strong> See where your devices are located on a map</li>
                                        <li><strong>Alerts:</strong> Get notified when sensors go outside normal ranges</li>
                                        <li><strong>Land boundaries:</strong> Check if devices are in the right locations</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Step 1: Setting Up a Land -->
                        <div class="card border-primary mb-4">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <span class="badge bg-white text-primary me-2">1</span>
                                    📍 Step 1: Setting Up a Land
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">A "Land" is an area where you want to monitor devices (like a farm field, building, or garden).</p>
                                
                                <h6 class="fw-semibold mb-2">How to Create a Land:</h6>
                                <ol>
                                    <li><strong>Go to Lands page:</strong> Click "Lands" in the menu → "Create New Land"</li>
                                    <li><strong>Fill in the details:</strong>
                                        <ul class="mt-2">
                                            <li><strong>Land Name:</strong> Give it a simple name (e.g., "North Field", "Greenhouse 1")</li>
                                            <li><strong>Description:</strong> What this area is used for</li>
                                            <li><strong>Location:</strong> You can add GPS coordinates if you know them</li>
                                            <li><strong>Draw boundaries</strong> (optional): If you want to check if devices stay within an area</li>
                                        </ul>
                                    </li>
                                    <li><strong>Save your land:</strong> Click "Create Land" - Your land is now ready for devices</li>
                                </ol>
                                
                                <div class="text-center mt-3">
                                    <a href="{{ route('app.lands.create') }}" class="btn btn-primary">
                                        <i class="ph-plus me-1"></i>Create Your First Land
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Setting Up Devices -->
                        <div class="card border-success mb-4">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <span class="badge bg-white text-success me-2">2</span>
                                    📱 Step 2: Setting Up Devices
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">Devices are your sensors that send data (temperature, humidity, etc.).</p>
                                
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                            <div class="card border-info">
                                            <div class="card-header bg-info text-white">
                                                <h6 class="mb-0">🔧 MQTT Devices</h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="mb-2"><strong>Best for:</strong> ESP32, Arduino with WiFi, Raspberry Pi</p>
                                                <p class="mb-2"><strong>Setup:</strong></p>
                                                <ul class="mb-0 small">
                                                    <li>Device ID: unique name</li>
                                                    <li>Connection Type: MQTT</li>
                                                    <li>MQTT Host: test.mosquitto.org or broker.emqx.io</li>
                                                    <li>Port: 1883</li>
                                                    <li>Topic: where device sends data</li>
                                                </ul>
                                                <div class="alert alert-warning mt-2 mb-0">
                                                    <small><strong>Note:</strong> EMQX only supports public servers without authentication. Use Mosquitto for production with credentials.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card border-warning">
                                            <div class="card-header bg-warning text-white">
                                                <h6 class="mb-0">🌐 LoRaWAN Devices</h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="mb-2"><strong>Best for:</strong> Long-range, low-power devices</p>
                                                <p class="mb-2"><strong>Setup:</strong></p>
                                                <ul class="mb-0 small">
                                                    <li>Set up in The Things Stack first</li>
                                                    <li>Connection Type: Webhook</li>
                                                    <li>Configure webhook in TTN</li>
                                                    <li>URL: /api/lorawan/webhook</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card border-secondary">
                                            <div class="card-header bg-secondary text-white">
                                                <h6 class="mb-0">🔗 Webhook Devices</h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="mb-2"><strong>Best for:</strong> Custom devices with HTTP</p>
                                                <p class="mb-2"><strong>Setup:</strong></p>
                                                <ul class="mb-0 small">
                                                    <li>Connection Type: Webhook</li>
                                                    <li>Get webhook URL after creating</li>
                                                    <li>Send HTTP POST requests</li>
                                                    <li>JSON format required</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <a href="{{ route('app.devices.create') }}" class="btn btn-success">
                                        <i class="ph-plus me-1"></i>Add Your First Device
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Device Payload Formats -->
                        <div class="card border-warning mb-4">
                            <div class="card-header bg-warning text-white">
                                <h6 class="mb-0">
                                    <i class="ph-code me-2"></i>What Your Device Should Send (Payload Formats)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h6 class="fw-semibold text-info">MQTT Format 1 (Recommended)</h6>
                                        <div class="bg-light p-3 rounded mb-3">
                                            <pre class="mb-0 small"><code>{
  "sensors": [
    {"type": "temperature", "value": "25.6 celsius"},
    {"type": "humidity", "value": "60.2 percent"},
    {"type": "light", "value": "75 percent"}
  ]
}</code></pre>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="fw-semibold text-info">MQTT Format 2 (Simple)</h6>
                                        <div class="bg-light p-3 rounded mb-3">
                                            <pre class="mb-0 small"><code>{
  "temperature": 26.1,
  "humidity": 58.5,
  "light": 80.0
}</code></pre>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="fw-semibold text-info">LoRaWAN Format</h6>
                                        <div class="bg-light p-3 rounded mb-3">
                                            <pre class="mb-0 small"><code>{
  "uplink_message": {
    "decoded_payload": {
      "data": {
        "temperature": 23.5,
        "humidity": 65.2,
        "battery": 85
      }
    }
  }
}</code></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- How Sensors Work -->
                        <div class="card border-info mb-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <span class="badge bg-white text-info me-2">3</span>
                                    📊 How Sensors Work
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-success">
                                    <h6 class="fw-semibold mb-2">✨ Automatic Sensor Creation</h6>
                                    <p class="mb-0">When your device sends data, the system automatically creates sensors for each type:</p>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Supported Sensor Types:</h6>
                                        <ul>
                                            <li><code>temperature</code> - Temperature readings (°C)</li>
                                            <li><code>humidity</code> - Humidity percentage (%)</li>
                                            <li><code>light</code> - Light intensity (%)</li>
                                            <li><code>pressure</code> - Air pressure (hPa)</li>
                                            <li><code>soil_moisture</code> - Soil moisture (%)</li>
                                            <li><code>ph</code> - pH levels</li>
                                            <li><code>battery</code> - Battery percentage (%)</li>
                                            <li><code>latitude</code> - GPS latitude (°)</li>
                                            <li><code>longitude</code> - GPS longitude (°)</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Setting Thresholds:</h6>
                                        <p class="mb-2">You can set minimum and maximum values for each sensor. The system will:</p>
                                        <ul>
                                            <li><span class="badge bg-success">Green</span> when values are normal</li>
                                            <li><span class="badge bg-warning">Yellow</span> for low warnings</li>
                                            <li><span class="badge bg-danger">Red</span> for high alerts</li>
                                        </ul>
                                        
                                        <div class="text-center mt-3">
                                            <a href="{{ route('app.sensors.index') }}" class="btn btn-info">
                                                <i class="ph-thermometer me-1"></i>View All Sensors
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Map Features -->
                        <div class="card border-secondary mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">
                                    <i class="ph-map-pin me-2"></i>🗺️ Map Features
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Device Location:</h6>
                                        <ul>
                                            <li>If your device sends GPS coordinates (<code>latitude</code> and <code>longitude</code>), it appears on the map</li>
                                            <li>The map shows your land boundaries (if you drew them)</li>
                                            <li><span class="badge bg-success">Green alert</span>: Device is inside the land boundary</li>
                                            <li><span class="badge bg-danger">Red alert</span>: Device is outside the land boundary</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Map Tools:</h6>
                                        <ul>
                                            <li><strong>Locate Device:</strong> Centers map on your device</li>
                                            <li><strong>Measure Distance:</strong> Click to measure distance from device to any point</li>
                                            <li><strong>Land Boundaries:</strong> Visual polygon showing your land area</li>
                                            <li><strong>Real-time Updates:</strong> Device location updates automatically</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Monitoring and Alerts -->
                        <div class="card border-danger mb-4">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0">
                                    <i class="ph-bell me-2"></i>🚨 Monitoring and Alerts
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h6 class="fw-semibold mb-2">Device Status:</h6>
                                        <ul>
                                            <li><span class="badge bg-success">Green (Online)</span>: Device is sending data</li>
                                            <li><span class="badge bg-danger">Red (Offline)</span>: No data received recently</li>
                                            <li><span class="badge bg-warning">Yellow (Maintenance)</span>: Device in maintenance mode</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="fw-semibold mb-2">Sensor Alerts:</h6>
                                        <ul>
                                            <li><span class="badge bg-success">Normal</span>: Values within expected range</li>
                                            <li><span class="badge bg-warning">Low Alert</span>: Value below minimum threshold</li>
                                            <li><span class="badge bg-danger">High Alert</span>: Value above maximum threshold</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="fw-semibold mb-2">Live Updates:</h6>
                                        <ul>
                                            <li><strong>MQTT devices:</strong> Update every 10 seconds</li>
                                            <li><strong>Webhook devices:</strong> Update when data is received</li>
                                            <li><strong>Web page:</strong> Refreshes automatically</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Troubleshooting -->
                        <div class="card border-warning mb-4">
                            <div class="card-header bg-warning text-white">
                                <h6 class="mb-0">
                                    <i class="ph-wrench me-2"></i>🔧 Troubleshooting
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold text-danger mb-2">Device Not Connecting:</h6>
                                        <ol>
                                            <li>Check your MQTT broker settings</li>
                                            <li>Verify username and password</li>
                                            <li>Make sure your device can reach the internet</li>
                                            <li>Check the topic name matches exactly</li>
                                        </ol>
                                        
                                        <h6 class="fw-semibold text-danger mb-2 mt-3">No Data Appearing:</h6>
                                        <ol>
                                            <li>Verify your device is sending the correct JSON format</li>
                                            <li>Check device status in the web interface</li>
                                            <li>Make sure the device ID matches exactly</li>
                                        </ol>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold text-danger mb-2">Location Not Showing:</h6>
                                        <ol>
                                            <li>Your device must send both <code>latitude</code> and <code>longitude</code></li>
                                            <li>Values should be decimal degrees (e.g., 39.528865)</li>
                                            <li>Check that GPS coordinates are valid</li>
                                        </ol>
                                        
                                        <div class="alert alert-info mt-3">
                                            <h6 class="fw-semibold mb-1">📞 Getting Help</h6>
                                            <p class="mb-0 small">If you need help, check that your device sends data in the correct format shown above, verify all settings match exactly, and ensure your device has internet connection.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Setup Example -->
                        <div class="card border-success mb-4">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="ph-rocket-launch me-2"></i>📝 Quick Setup Example
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">Here's a complete example of setting up a temperature monitoring system:</p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Setup Steps:</h6>
                                        <ol>
                                            <li><strong>Create Land:</strong> "My Garden"</li>
                                            <li><strong>Create Device:</strong>
                                                <ul class="mt-1">
                                                    <li>ID: <code>garden_temp_01</code></li>
                                                    <li>Type: MQTT</li>
                                                    <li>Host: <code>test.mosquitto.org</code></li>
                                                    <li>Topic: <code>garden/sensors</code></li>
                                                </ul>
                                            </li>
                                            <li><strong>Device sends data</strong></li>
                                            <li><strong>Monitor:</strong> Check web interface for live data</li>
                                        </ol>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Device Payload:</h6>
                                        <div class="bg-light p-3 rounded">
                                            <pre class="mb-0 small"><code>{
  "sensors": [
    {"type": "temperature", "value": "22.5 celsius"},
    {"type": "humidity", "value": "65 percent"}
  ]
}</code></pre>
                                        </div>
                                        <p class="mb-0 mt-2 small"><strong>Result:</strong> Temperature and humidity sensors appear automatically in the system!</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Next Steps -->
                        <div class="text-center">
                            <div class="alert alert-primary">
                                <h6 class="fw-semibold mb-2">🎯 Ready to Get Started?</h6>
                                <p class="mb-3">Your IoT monitoring system is ready to use! Follow the steps above to start monitoring your devices.</p>
                                <div class="d-flex justify-content-center gap-3 flex-wrap">
                                    <a href="{{ route('app.lands.create') }}" class="btn btn-primary">
                                        <i class="ph-map-pin-line me-1"></i>Create First Land
                                    </a>
                                    <a href="{{ route('app.devices.create') }}" class="btn btn-success">
                                        <i class="ph-cpu me-1"></i>Add First Device
                                    </a>
                                    <a href="{{ route('app.dashboard') }}" class="btn btn-outline-info">
                                        <i class="ph-house me-1"></i>View Dashboard
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
