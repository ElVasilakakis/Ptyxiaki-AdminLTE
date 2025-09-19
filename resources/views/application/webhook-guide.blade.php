@extends('layouts.application.app')

@section('title', 'How to Use Webhooks')

@section('content')
<div class="content">
    <div class="page-header page-header-light shadow">
        <div class="page-header-content d-lg-flex">
            <div class="d-flex">
                <h4 class="page-title mb-0">
                    How to Use Webhooks
                </h4>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Simple Steps to Connect Devices via Webhooks</h5>
                        <p class="text-muted mb-0">Easy guide for beginners - no complex setup needed!</p>
                    </div>
                    <div class="card-body">

                        <!-- What are Webhooks -->
                        <div class="alert alert-success mb-4">
                            <div class="d-flex align-items-center">
                                <i class="ph-webhook fs-3 me-3"></i>
                                <div>
                                    <h6 class="fw-semibold mb-1">What are Webhooks?</h6>
                                    <p class="mb-0">Webhooks are HTTP endpoints that receive data from your devices. Simply send sensor data via HTTP POST requests - perfect for IoT devices and server deployments!</p>
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
                                            Create Webhook Connector
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">First, create a webhook connector to organize your devices.</p>
                                        <div class="d-flex align-items-start">
                                            <i class="ph-arrow-right text-primary me-2 mt-1"></i>
                                            <div>
                                                <p class="mb-2">Go to <a href="{{ route('app.mqttbrokers.create') }}" class="fw-semibold">Connectors → Add New</a></p>
                                                <p class="mb-2">Fill in these details:</p>
                                                <ul class="list-unstyled ms-3">
                                                    <li class="mb-1">• <strong>Name:</strong> "My Webhook Connector"</li>
                                                    <li class="mb-1">• <strong>Type:</strong> Select "webhook"</li>
                                                    <li class="mb-1">• <strong>Description:</strong> Optional description</li>
                                                </ul>
                                                <div class="alert alert-info mt-2 mb-2">
                                                    <small><strong>Benefits of Webhooks:</strong><br>
                                                    • Works on any server (Ploi, shared hosting, etc.)<br>
                                                    • No background processes needed<br>
                                                    • Easy to debug and monitor<br>
                                                    • Secure token-based authentication</small>
                                                </div>
                                                <p class="mb-0 text-success">✓ Click "Create Connector"</p>
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
                                        <p class="mb-3">Register your device in the system.</p>
                                        <div class="d-flex align-items-start">
                                            <i class="ph-arrow-right text-success me-2 mt-1"></i>
                                            <div>
                                                <p class="mb-2">Go to <a href="{{ route('app.devices.create') }}" class="fw-semibold">Devices → Add New</a></p>
                                                <p class="mb-2">Fill in these details:</p>
                                                <ul class="list-unstyled ms-3">
                                                    <li class="mb-1">• <strong>Device ID:</strong> "SENSOR001" (unique identifier)</li>
                                                    <li class="mb-1">• <strong>Name:</strong> "Living Room Sensor" (any name you like)</li>
                                                    <li class="mb-1">• <strong>Land:</strong> Choose where your device is located</li>
                                                    <li class="mb-1">• <strong>Protocol:</strong> Will default to "webhook"</li>
                                                    <li class="mb-1">• <strong>Connector:</strong> Select the webhook connector you created</li>
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
                                            Get Webhook URL
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">Get the unique webhook URL for your device.</p>
                                        <div class="d-flex align-items-start">
                                            <i class="ph-arrow-right text-warning me-2 mt-1"></i>
                                            <div>
                                                <p class="mb-2">Two ways to get your webhook URL:</p>
                                                <ul class="list-unstyled ms-3">
                                                    <li class="mb-2">• <strong>Method 1:</strong> Go to your connector details page and view webhook instructions</li>
                                                    <li class="mb-2">• <strong>Method 2:</strong> Use API endpoint: <code>GET /api/webhook/mqtt/SENSOR001/instructions</code></li>
                                                </ul>
                                                <div class="alert alert-secondary mt-2 mb-2">
                                                    <strong>Your webhook URL will look like:</strong><br>
                                                    <code>{{ url('/api/webhook/mqtt/SENSOR001?token=abc123...') }}</code>
                                                </div>
                                                <p class="mb-0 text-success">✓ Copy the webhook URL and token</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 4 -->
                                <div class="card border-info mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0">
                                            <span class="badge bg-white text-info me-2">4</span>
                                            Configure Your Device
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">Set up your device to send data to the webhook URL.</p>
                                        <div class="d-flex align-items-start">
                                            <i class="ph-arrow-right text-info me-2 mt-1"></i>
                                            <div>
                                                <p class="mb-2">Configure your device to:</p>
                                                <ul class="list-unstyled ms-3">
                                                    <li class="mb-1">• <strong>Method:</strong> HTTP POST</li>
                                                    <li class="mb-1">• <strong>URL:</strong> Your webhook URL (from step 3)</li>
                                                    <li class="mb-1">• <strong>Content-Type:</strong> application/json</li>
                                                    <li class="mb-1">• <strong>Body:</strong> JSON sensor data (see examples below)</li>
                                                </ul>
                                                <p class="mb-0 text-success">✓ Start sending data from your device</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 5 -->
                                <div class="card border-success mb-4">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0">
                                            <span class="badge bg-white text-success me-2">5</span>
                                            You're Ready!
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <i class="ph-check-circle text-success fs-2 me-3"></i>
                                            <div>
                                                <h6 class="fw-semibold text-success mb-2">Congratulations!</h6>
                                                <p class="mb-2">Your device is now connected via webhooks and ready to send data.</p>
                                                <p class="mb-0">Check your <a href="{{ route('app.devices.index') }}" class="fw-semibold">Devices page</a> to see incoming sensor data!</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Data Format Documentation -->
                        <div class="mt-5">
                            <h6 class="fw-semibold text-secondary mb-3">
                                <i class="ph-code me-2"></i>Webhook Data Format Information
                            </h6>

                            <div class="alert alert-secondary">
                                <h6 class="fw-semibold mb-2">Supported Data Formats</h6>
                                <p class="mb-2">Your device can send data in two formats:</p>

                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Structured Format (Recommended)</h6>
                                        <div class="bg-dark text-light p-3 rounded mb-3">
                                            <pre class="mb-0"><code>{
  "sensors": [
    {
      "type": "temperature",
      "value": 25.5
    },
    {
      "type": "humidity",
      "value": 60
    },
    {
      "type": "battery",
      "value": 85
    }
  ]
}</code></pre>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Flat Format</h6>
                                        <div class="bg-dark text-light p-3 rounded mb-3">
                                            <pre class="mb-0"><code>{
  "temperature": 25.5,
  "humidity": 60,
  "battery": 85,
  "light": 450,
  "motion": 1
}</code></pre>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Supported Sensor Types:</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-1">• <strong>temperature</strong> - Temperature readings (°C)</li>
                                            <li class="mb-1">• <strong>humidity</strong> - Humidity percentage (%)</li>
                                            <li class="mb-1">• <strong>pressure</strong> - Atmospheric pressure (hPa)</li>
                                            <li class="mb-1">• <strong>light</strong> - Light intensity (lux)</li>
                                            <li class="mb-1">• <strong>motion</strong> - Motion detection (0/1)</li>
                                            <li class="mb-1">• <strong>battery</strong> - Battery level (%)</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Location Data:</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-1">• <strong>latitude</strong> - GPS latitude (degrees)</li>
                                            <li class="mb-1">• <strong>longitude</strong> - GPS longitude (degrees)</li>
                                            <li class="mb-1">• Values should be decimal degrees</li>
                                            <li class="mb-1">• Custom sensor types are auto-created</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="ph-info me-2"></i>
                                    <strong>Note:</strong> The system automatically creates sensors from incoming data. Unknown sensor types will be created automatically with appropriate units.
                                </div>
                            </div>
                        </div>

                        <!-- Arduino Example -->
                        <div class="mt-4">
                            <h6 class="fw-semibold text-secondary mb-3">
                                <i class="ph-microchip me-2"></i>Arduino Example Code
                            </h6>

                            <div class="alert alert-light">
                                <h6 class="fw-semibold mb-2">Simple Arduino Webhook Client</h6>
                                <p class="mb-2">Here's a basic example for ESP32/ESP8266:</p>

                                <div class="bg-dark text-light p-3 rounded mb-3">
                                    <pre class="mb-0"><code>#include &lt;WiFi.h&gt;
#include &lt;HTTPClient.h&gt;
#include &lt;ArduinoJson.h&gt;

const char* ssid = "your-wifi-ssid";
const char* password = "your-wifi-password";
const char* webhookUrl = "{{ url('/api/webhook/mqtt/SENSOR001?token=your_token') }}";

void setup() {
  Serial.begin(115200);
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting to WiFi...");
  }
  Serial.println("Connected to WiFi");
}

void loop() {
  // Send sensor data every 30 seconds
  static unsigned long lastSend = 0;
  unsigned long now = millis();
  if (now - lastSend > 30000) {
    lastSend = now;
    sendSensorData();
  }
  delay(1000);
}

void sendSensorData() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(webhookUrl);
    http.addHeader("Content-Type", "application/json");

    // Create JSON payload
    DynamicJsonDocument doc(1024);
    doc["temperature"] = 25.5;
    doc["humidity"] = 60;
    doc["battery"] = 85;

    String payload;
    serializeJson(doc, payload);

    // Send POST request
    int httpResponseCode = http.POST(payload);

    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.println("Response: " + response);
    } else {
      Serial.println("Error sending data");
    }

    http.end();
  }
}</code></pre>
                                </div>

                                <div class="alert alert-warning mt-2 mb-0">
                                    <i class="ph-warning me-2"></i>
                                    <strong>Note:</strong> Replace the webhook URL with your actual device URL and token from step 3.
                                </div>
                            </div>
                        </div>

                        <!-- Python Example -->
                        <div class="mt-4">
                            <h6 class="fw-semibold text-secondary mb-3">
                                <i class="ph-file-py me-2"></i>Python Example Code
                            </h6>

                            <div class="alert alert-light">
                                <h6 class="fw-semibold mb-2">Simple Python Webhook Client</h6>

                                <div class="bg-dark text-light p-3 rounded mb-3">
                                    <pre class="mb-0"><code>import requests
import json
import time

# Configuration
webhook_url = "{{ url('/api/webhook/mqtt/SENSOR001?token=your_token') }}"
headers = {"Content-Type": "application/json"}

def send_sensor_data(temperature, humidity, battery):
    data = {
        "temperature": temperature,
        "humidity": humidity,
        "battery": battery
    }

    try:
        response = requests.post(webhook_url, json=data, headers=headers)
        if response.status_code == 200:
            print("Data sent successfully:", response.json())
        else:
            print("Error:", response.status_code, response.text)
    except Exception as e:
        print("Exception:", str(e))

# Main loop
while True:
    # Read sensor values (replace with actual sensor reading code)
    temp = 25.5
    humidity = 60
    battery = 85

    send_sensor_data(temp, humidity, battery)
    time.sleep(30)  # Send data every 30 seconds</code></pre>
                                </div>
                            </div>
                        </div>

                        <!-- cURL Example -->
                        <div class="mt-4">
                            <h6 class="fw-semibold text-secondary mb-3">
                                <i class="ph-terminal me-2"></i>cURL Example
                            </h6>

                            <div class="alert alert-light">
                                <h6 class="fw-semibold mb-2">Test with cURL Command</h6>

                                <div class="bg-dark text-light p-3 rounded mb-3">
                                    <pre class="mb-0"><code>curl -X POST '{{ url('/api/webhook/mqtt/SENSOR001?token=your_token') }}' \
  -H 'Content-Type: application/json' \
  -d '{
    "temperature": 25.5,
    "humidity": 60,
    "battery": 85
  }'</code></pre>
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
                                        <li>Use unique device IDs for each device</li>
                                        <li>Keep webhook tokens secure</li>
                                        <li>Use HTTPS in production</li>
                                        <li>Keep payload size reasonable</li>
                                        <li>Handle HTTP errors gracefully</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-light">
                                    <h6 class="fw-semibold mb-2">
                                        <i class="ph-question text-info me-2"></i>Troubleshooting
                                    </h6>
                                    <ul class="mb-0">
                                        <li>Check WiFi connection on your device</li>
                                        <li>Verify webhook URL and token are correct</li>
                                        <li>Ensure Content-Type header is set</li>
                                        <li>Check server logs for errors</li>
                                        <li>Test with cURL first</li>
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
