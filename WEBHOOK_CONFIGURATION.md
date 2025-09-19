# Webhook Configuration Guide

This guide provides step-by-step instructions for configuring and using webhooks in the IoT Sensor Monitoring System.

## 🎯 Overview

The system uses **webhooks** instead of MQTT for data collection. This approach is:
- ✅ **Server-friendly** - Works on any hosting platform (Ploi, shared hosting, etc.)
- ✅ **Reliable** - HTTP-based, easier to debug and monitor
- ✅ **Secure** - Token-based authentication
- ✅ **Simple** - No background processes or commands needed

## 📋 Quick Setup Checklist

- [ ] Create a webhook connector in the dashboard
- [ ] Add your devices to the connector
- [ ] Get webhook URLs for each device
- [ ] Configure your devices to send data to webhook URLs
- [ ] Test data transmission
- [ ] Monitor dashboard for incoming data

## 🔧 Step-by-Step Configuration

### Step 1: Create a Webhook Connector

1. **Login** to your dashboard
2. **Navigate** to "Connectors" (or "MQTT Brokers" section)
3. **Click** "Add New Connector"
4. **Fill in the form**:
   ```
   Name: My IoT Devices
   Type: webhook
   Description: Webhook connector for my sensor devices
   ```
5. **Save** the connector

### Step 2: Add Devices

1. **Go to** "Devices" section
2. **Click** "Add Device"
3. **Configure** each device:
   ```
   Device ID: SENSOR001 (unique identifier)
   Name: Living Room Sensor
   Connector: My IoT Devices (select your webhook connector)
   Protocol: webhook (auto-selected)
   Device Type: sensor
   ```
4. **Save** the device

### Step 3: Get Webhook URLs

**Method A: Via Dashboard**
1. Go to your connector details page
2. View the webhook instructions for each device
3. Copy the webhook URL and token

**Method B: Via API**
```bash
GET https://yourapp.com/api/webhook/mqtt/SENSOR001/instructions
```

**Response:**
```json
{
  "success": true,
  "device_id": "SENSOR001",
  "instructions": {
    "webhook_url": "https://yourapp.com/api/webhook/mqtt/SENSOR001?token=abc123...",
    "method": "POST",
    "content_type": "application/json",
    "example_curl": "curl -X POST '...' -H 'Content-Type: application/json' -d '{\"temperature\": 25.5}'"
  }
}
```

## 📡 Device Configuration Examples

### Arduino/ESP32 Configuration

```cpp
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// Configuration
const char* ssid = "your_wifi_ssid";
const char* password = "your_wifi_password";
const char* webhookUrl = "https://yourapp.com/api/webhook/mqtt/SENSOR001?token=your_token";

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
