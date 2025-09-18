@extends('layouts.application.app')

@section('title', 'How to MQTT')

@section('content')
<div class="content">
    <div class="page-header page-header-light shadow">
        <div class="page-header-content d-lg-flex">
            <div class="d-flex">
                <h4 class="page-title mb-0">
                    How to MQTT
                </h4>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Simple Steps to Connect MQTT Devices</h5>
                        <p class="text-muted mb-0">Easy guide for beginners - no programming experience needed!</p>
                    </div>
                    <div class="card-body">
                        
                        <!-- What is MQTT -->
                        <div class="alert alert-success mb-4">
                            <div class="d-flex align-items-center">
                                <i class="ph-wifi-high fs-3 me-3"></i>
                                <div>
                                    <h6 class="fw-semibold mb-1">What is MQTT?</h6>
                                    <p class="mb-0">MQTT is a messaging protocol that allows devices to send data over WiFi or internet connection to a central server (broker). Perfect for IoT devices!</p>
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
                                            Set Up Connector
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">First, you need an Connector to handle messages between your devices and the system.</p>
                                        <div class="d-flex align-items-start">
                                            <i class="ph-arrow-right text-primary me-2 mt-1"></i>
                                            <div>
                                                <p class="mb-2">Go to <a href="{{ route('app.mqttbrokers.create') }}" class="fw-semibold">Connectors → Add New</a></p>
                                                <p class="mb-2">Fill in these details:</p>
                                                <ul class="list-unstyled ms-3">
                                                    <li class="mb-1">• <strong>Name:</strong> "My Connector"</li>
                                                    <li class="mb-1">• <strong>Host:</strong> Your broker address (e.g., broker.hivemq.com)</li>
                                                    <li class="mb-1">• <strong>Port:</strong> 1883 (standard) or 8883 (secure)</li>
                                                    <li class="mb-1">• <strong>Username:</strong> Your broker username (if required)</li>
                                                    <li class="mb-1">• <strong>Password:</strong> Your broker password (if required)</li>
                                                </ul>
                                                <div class="alert alert-info mt-2 mb-2">
                                                    <small><strong>Popular Free Brokers:</strong><br>
                                                    • test.mosquitto.org (port 1883) - For testing only<br>
                                                    • broker.hivemq.com (port 1883) - Public broker<br>
                                                    • mqtt.eclipseprojects.io (port 1883) - Eclipse broker</small>
                                                </div>
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
                                            Add Your MQTT Device
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">Register your MQTT device in the system.</p>
                                        <div class="d-flex align-items-start">
                                            <i class="ph-arrow-right text-success me-2 mt-1"></i>
                                            <div>
                                                <p class="mb-2">Go to <a href="{{ route('app.devices.create') }}" class="fw-semibold">Devices → Add New</a></p>
                                                <p class="mb-2">Fill in these details:</p>
                                                <ul class="list-unstyled ms-3">
                                                    <li class="mb-1">• <strong>Name:</strong> "My MQTT Device" (any name you like)</li>
                                                    <li class="mb-1">• <strong>Land:</strong> Choose where your device is located</li>
                                                    <li class="mb-1">• <strong>Protocol:</strong> Select "MQTT"</li>
                                                    <li class="mb-1">• <strong>Connector:</strong> Select the broker you created</li>
                                                    <li class="mb-1">• <strong>Topic:</strong> The MQTT topic your device publishes to (e.g., "sensors/device1")</li>
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
                                            Configure Your Device
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">Set up your physical device to send data to the broker.</p>
                                        <div class="d-flex align-items-start">
                                            <i class="ph-arrow-right text-warning me-2 mt-1"></i>
                                            <div>
                                                <p class="mb-2">Configure your device with:</p>
                                                <ul class="list-unstyled ms-3">
                                                    <li class="mb-1">• <strong>Broker Host:</strong> Same as step 1</li>
                                                    <li class="mb-1">• <strong>Port:</strong> Same as step 1</li>
                                                    <li class="mb-1">• <strong>Topic:</strong> Same as step 2</li>
                                                    <li class="mb-1">• <strong>Username/Password:</strong> If your broker requires authentication</li>
                                                </ul>
                                                <p class="mb-2">Your device should publish sensor data to: <code>{{ url('/api/lorawan/webhook') }}</code></p>
                                                <p class="mb-0 text-success">✓ Start your device</p>
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
                                                <p class="mb-2">Your MQTT device is now connected and ready to send data.</p>
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
                                <i class="ph-code me-2"></i>MQTT Payload Format Information
                            </h6>
                            
                            <div class="alert alert-secondary">
                                <h6 class="fw-semibold mb-2">Expected MQTT Payload Format</h6>
                                <p class="mb-2">Your MQTT device should send data in this JSON format to the webhook endpoint:</p>
                                
                                <div class="bg-dark text-light p-3 rounded mb-3">
                                    <pre class="mb-0"><code class="text-black">{
  "sensors": [
    {
      "type": "thermal",
      "value": "25.6 celsius"
    },
    {
      "type": "humidity", 
      "value": "65.2 percent"
    },
    {
      "type": "light",
      "value": "450 lux"
    },
    {
      "type": "geolocation",
      "subtype": "latitude",
      "value": "38.170284"
    },
    {
      "type": "geolocation", 
      "subtype": "longitude",
      "value": "-119.076024"
    }
  ],
  "timestamp": 1694952000
}</code></pre>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Supported Sensor Types:</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-1">• <strong>thermal</strong> - Temperature (celsius/fahrenheit)</li>
                                            <li class="mb-1">• <strong>humidity</strong> - Humidity (percent)</li>
                                            <li class="mb-1">• <strong>light</strong> - Light level (lux/percent)</li>
                                            <li class="mb-1">• <strong>potentiometer</strong> - Analog values (percent)</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">Location Data:</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-1">• <strong>geolocation</strong> with subtype "latitude"</li>
                                            <li class="mb-1">• <strong>geolocation</strong> with subtype "longitude"</li>
                                            <li class="mb-1">• Values should be decimal degrees</li>
                                            <li class="mb-1">• <strong>timestamp</strong> - Unix timestamp (optional)</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="ph-info me-2"></i>
                                    <strong>Note:</strong> The system automatically extracts numeric values and units from strings like "25.6 celsius" or "65 percent". Unknown sensor types will be created automatically.
                                </div>
                            </div>
                        </div>

                        <!-- Arduino Example -->
                        <div class="mt-4">
                            <h6 class="fw-semibold text-secondary mb-3">
                                <i class="ph-microchip me-2"></i>Arduino Example Code
                            </h6>
                            
                            <div class="alert alert-light">
                                <h6 class="fw-semibold mb-2">Simple Arduino MQTT Client</h6>
                                <p class="mb-2">Here's a basic example for ESP32/ESP8266:</p>
                                
                                <div class="bg-dark text-light p-3 rounded mb-3">
                                    <pre class="mb-0"><code>#include &lt;WiFi.h&gt;
#include &lt;PubSubClient.h&gt;
#include &lt;ArduinoJson.h&gt;

const char* ssid = "your-wifi-ssid";
const char* password = "your-wifi-password";
const char* mqtt_server = "broker.hivemq.com";

WiFiClient espClient;
PubSubClient client(espClient);

void setup() {
  Serial.begin(115200);
  WiFi.begin(ssid, password);
  client.setServer(mqtt_server, 1883);
}

void loop() {
  if (!client.connected()) {
    reconnect();
  }
  client.loop();
  
  // Send sensor data every 30 seconds
  static unsigned long lastMsg = 0;
  unsigned long now = millis();
  if (now - lastMsg > 30000) {
    lastMsg = now;
    sendSensorData();
  }
}

void sendSensorData() {
  DynamicJsonDocument doc(1024);
  doc["device_id"] = "my-arduino-device";
  doc["timestamp"] = millis() / 1000;
  
  JsonArray sensors = doc.createNestedArray("sensors");
  
  // Temperature sensor
  JsonObject temp = sensors.createNestedObject();
  temp["type"] = "thermal";
  temp["value"] = String(25.6) + " celsius";
  
  // Humidity sensor  
  JsonObject hum = sensors.createNestedObject();
  hum["type"] = "humidity";
  hum["value"] = String(65.2) + " percent";
  
  String payload;
  serializeJson(doc, payload);
  
  // Publish to your webhook endpoint
  // You'll need to use HTTP POST instead of MQTT publish
  // or configure your broker to forward to the webhook
}</code></pre>
                                </div>
                                
                                <div class="alert alert-warning mt-2 mb-0">
                                    <i class="ph-warning me-2"></i>
                                    <strong>Note:</strong> This is a basic example. For production use, add error handling, secure connections (TLS), and proper sensor reading code.
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
                                        <li>Test with public brokers first</li>
                                        <li>Use secure connections (port 8883) in production</li>
                                        <li>Keep payload size small for better performance</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-light">
                                    <h6 class="fw-semibold mb-2">
                                        <i class="ph-question text-info me-2"></i>Need Help?
                                    </h6>
                                    <ul class="mb-0">
                                        <li>Check WiFi connection on your device</li>
                                        <li>Verify broker credentials are correct</li>
                                        <li>Test with MQTT client tools first</li>
                                        <li>Check firewall allows MQTT ports</li>
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
