# MQTTX and Arduino Webhook Configuration Guide

## 🎯 Understanding Your Setup

You have **EMQX broker ID 1** and want to use **MQTTX** as a sender along with **Arduino**. Here are your configuration options:

## 📊 Two Approaches Available

### Option 1: Direct Webhook (Recommended)
Your system is designed to work with **webhooks** instead of traditional MQTT brokers. This is simpler and more reliable.

### Option 2: MQTT Bridge (Advanced)
Use EMQX broker with webhook forwarding rules.

---

## 🚀 Option 1: Direct Webhook Configuration (Recommended)

### Step 1: Get Your Device Webhook URL

1. **Login to your dashboard**
2. **Go to Connectors** (MQTT Brokers section)
3. **Find your EMQX broker (ID: 1)**
4. **View devices** associated with this broker
5. **Copy the webhook URL** for each device

**Example webhook URL:**
```
https://yourapp.com/api/webhook/mqtt/YOUR_DEVICE_ID?token=your_secure_token
```

### Step 2: Configure MQTTX for Webhook Testing

Since MQTTX is primarily an MQTT client, you'll need to use **HTTP requests** instead. Here are alternatives:

#### A. Use Postman or cURL for Testing
```bash
curl -X POST 'https://yourapp.com/api/webhook/mqtt/YOUR_DEVICE_ID?token=your_token' \
  -H 'Content-Type: application/json' \
  -d '{
    "temperature": 25.5,
    "humidity": 60.2,
    "battery": 85
  }'
```

#### B. Use MQTTX Scripts Feature
MQTTX has a scripts feature where you can create custom JavaScript to send HTTP requests:

```javascript
// MQTTX Script for Webhook
function handleMessage(topic, message, packet) {
  const data = JSON.parse(message.toString());
  
  // Send to webhook
  const webhookUrl = 'https://yourapp.com/api/webhook/mqtt/YOUR_DEVICE_ID?token=your_token';
  
  fetch(webhookUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(result => console.log('Webhook response:', result))
  .catch(error => console.error('Webhook error:', error));
}
```

### Step 3: Configure Arduino for Direct Webhook

```cpp
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// Configuration
const char* ssid = "your_wifi_ssid";
const char* password = "your_wifi_password";
const char* webhookUrl = "https://yourapp.com/api/webhook/mqtt/YOUR_DEVICE_ID?token=your_token";

void setup() {
  Serial.begin(115200);
  
  // Connect to WiFi
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting to WiFi...");
  }
  Serial.println("Connected to WiFi");
}

void sendSensorData(float temperature, float humidity, int battery) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(webhookUrl);
    http.addHeader("Content-Type", "application/json");
    
    // Create JSON payload
    StaticJsonDocument<200> doc;
    doc["temperature"] = temperature;
    doc["humidity"] = humidity;
    doc["battery"] = battery;
    
    String jsonString;
    serializeJson(doc, jsonString);
    
    // Send POST request
    int httpResponseCode = http.POST(jsonString);
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.println("HTTP Response: " + String(httpResponseCode));
      Serial.println("Response: " + response);
    } else {
      Serial.println("Error in HTTP request");
    }
    
    http.end();
  }
}

void loop() {
  // Read sensors (example values)
  float temp = 25.5;
  float hum = 60.2;
  int bat = 85;
  
  // Send data every 30 seconds
  sendSensorData(temp, hum, bat);
  delay(30000);
}
```

---

## 🔧 Option 2: EMQX Broker with Webhook Rules (Advanced)

If you want to keep using EMQX broker, you can configure webhook rules in EMQX:

### Step 1: Configure EMQX Webhook Rule

1. **Access EMQX Dashboard** (usually http://localhost:18083)
2. **Go to Rules** section
3. **Create a new rule**:

```sql
SELECT 
  payload.temperature as temperature,
  payload.humidity as humidity,
  payload.battery as battery,
  clientid as device_id
FROM 
  "sensor/+/data"
```

4. **Add Action**: HTTP Request
   - **URL**: `https://yourapp.com/api/webhook/mqtt/${device_id}?token=your_token`
   - **Method**: POST
   - **Headers**: `Content-Type: application/json`
   - **Body**: `${payload}`

### Step 2: Configure MQTTX for EMQX

1. **Open MQTTX**
2. **Create new connection**:
   - **Host**: localhost (or your EMQX server IP)
   - **Port**: 1883
   - **Client ID**: mqttx_client
3. **Publish messages** to topic: `sensor/YOUR_DEVICE_ID/data`
4. **Message format**:
```json
{
  "temperature": 25.5,
  "humidity": 60.2,
  "battery": 85
}
```

### Step 3: Configure Arduino for EMQX

```cpp
#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

// Configuration
const char* ssid = "your_wifi_ssid";
const char* password = "your_wifi_password";
const char* mqtt_server = "your_emqx_server_ip";
const int mqtt_port = 1883;
const char* device_id = "YOUR_DEVICE_ID";

WiFiClient espClient;
PubSubClient client(espClient);

void setup() {
  Serial.begin(115200);
  
  // Connect to WiFi
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting to WiFi...");
  }
  
  // Connect to MQTT
  client.setServer(mqtt_server, mqtt_port);
  
  while (!client.connected()) {
    if (client.connect(device_id)) {
      Serial.println("Connected to MQTT");
    } else {
      delay(5000);
    }
  }
}

void sendSensorData(float temperature, float humidity, int battery) {
  StaticJsonDocument<200> doc;
  doc["temperature"] = temperature;
  doc["humidity"] = humidity;
  doc["battery"] = battery;
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  String topic = "sensor/" + String(device_id) + "/data";
  client.publish(topic.c_str(), jsonString.c_str());
  
  Serial.println("Data sent: " + jsonString);
}

void loop() {
  if (!client.connected()) {
    // Reconnect logic here
  }
  client.loop();
  
  // Read sensors and send data
  float temp = 25.5;
  float hum = 60.2;
  int bat = 85;
  
  sendSensorData(temp, hum, bat);
  delay(30000);
}
```

---

## 🎯 Recommendation

**Use Option 1 (Direct Webhook)** because:
- ✅ Simpler setup
- ✅ No additional EMQX configuration needed
- ✅ Better for hosting environments
- ✅ Easier to debug and monitor
- ✅ Your system is already optimized for webhooks

## 🧪 Testing Your Setup

1. **Test webhook endpoint**:
```bash
curl -X POST 'https://yourapp.com/api/webhook/test' \
  -H 'Content-Type: application/json' \
  -d '{"test": "data"}'
```

2. **Check your dashboard** for incoming data
3. **Monitor logs** in your Laravel application

## 📞 Need Help?

If you need the specific webhook URLs for your devices, run this command in your project:

```bash
php get_webhook_url.php
```

This will show you the exact URLs and tokens for all your devices.
