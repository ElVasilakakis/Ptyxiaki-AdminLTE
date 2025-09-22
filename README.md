# IoT Device Management System - User Guide

A simple system to monitor your IoT devices and sensors. This guide will help you set up your lands and devices step by step.

## 🏠 What This System Does

- **Monitor your devices**: Track temperature, humidity, location, and other sensors
- **Map visualization**: See where your devices are located on a map
- **Alerts**: Get notified when sensors go outside normal ranges
- **Land boundaries**: Check if devices are in the right locations

## 📋 Step 1: Setting Up a Land

A "Land" is an area where you want to monitor devices (like a farm field, building, or garden).

### How to Create a Land:

1. **Go to Lands page**
   - Click "Lands" in the menu
   - Click "Create New Land"

2. **Fill in the details**:
   - **Land Name**: Give it a simple name (e.g., "North Field", "Greenhouse 1")
   - **Description**: What this area is used for
   - **Location**: You can add GPS coordinates if you know them
   - **Draw boundaries** (optional): If you want to check if devices stay within an area

3. **Save your land**
   - Click "Create Land"
   - Your land is now ready for devices

## 📱 Step 2: Setting Up Devices

Devices are your sensors that send data (temperature, humidity, etc.).

### Device Types Available:

1. **MQTT Devices** (like ESP32, Arduino with WiFi)
2. **LoRaWAN Devices** (The Things Stack/TTN)
3. **Webhook Devices** (custom HTTP devices)

---

## 🔧 MQTT Devices (Most Common)

**Best for**: ESP32, Arduino with WiFi, Raspberry Pi

### How to Add an MQTT Device:

1. **Go to Devices page**
   - Click "Devices" → "Create New Device"

2. **Basic Information**:
   - **Device ID**: A unique name (e.g., "temp_sensor_01")
   - **Device Name**: Friendly name (e.g., "Greenhouse Temperature Sensor")
   - **Land**: Choose the land you created
   - **Connection Type**: Select "MQTT"

3. **MQTT Settings**:
   - **MQTT Host**: `broker.emqx.io` (free public broker)
   - **Port**: `1883`
   - **Username**: Your MQTT username
   - **Password**: Your MQTT password
   - **Topic**: Where your device sends data (e.g., `greenhouse/sensors`)

4. **Save and Test**
   - Click "Create Device"
   - The system will try to connect

### What Your Device Should Send (MQTT Payload):

Your device needs to send data in JSON format. Here are the formats that work:

#### Format 1: Multiple Sensors (Recommended)
```json
{
  "sensors": [
    {"type": "temperature", "value": "25.6 celsius"},
    {"type": "humidity", "value": "60.2 percent"},
    {"type": "light", "value": "75 percent"}
  ]
}
```

#### Format 2: Simple Values
```json
{
  "temperature": 26.1,
  "humidity": 58.5,
  "light": 80.0
}
```

#### Format 3: Single Sensor
```json
{
  "sensor_type": "temperature",
  "value": 24.8,
  "unit": "°C"
}
```

---

## 🌐 LoRaWAN Devices (The Things Stack)

**Best for**: Long-range, low-power devices

### How to Add a LoRaWAN Device:

1. **Set up in The Things Stack first**:
   - Create account at The Things Stack
   - Add your device there
   - Get device ID and keys

2. **Add to our system**:
   - **Device ID**: Same as in The Things Stack
   - **Connection Type**: Select "Webhook"
   - **Land**: Choose your land

3. **Configure webhook in The Things Stack**:
   - Add webhook URL: `https://yourdomain.com/api/lorawan/webhook`
   - Set format to JSON

### What Your LoRaWAN Device Should Send:

The Things Stack automatically formats the data like this:
```json
{
  "uplink_message": {
    "decoded_payload": {
      "data": {
        "temperature": 23.5,
        "humidity": 65.2,
        "battery": 85
      }
    }
  }
}
```

---

## 🔗 Webhook Devices (Custom)

**Best for**: Custom devices that can send HTTP requests

### How to Add a Webhook Device:

1. **Create device**:
   - **Connection Type**: Select "Webhook"
   - Get the webhook URL after creating

2. **Configure your device** to send HTTP POST to:
   `https://yourdomain.com/api/webhook/mqtt/your_device_id`

### What Your Webhook Device Should Send:
```json
{
  "device_id": "your_device_id",
  "sensors": [
    {
      "sensor_type": "temperature",
      "value": 25.6,
      "unit": "°C"
    }
  ]
}
```

---

## 📊 How Sensors Work

### Automatic Sensor Creation:
When your device sends data, the system automatically creates sensors for each type:

- **Temperature** → Creates "Temperature Sensor" (°C)
- **Humidity** → Creates "Humidity Sensor" (%)
- **Light** → Creates "Light Sensor" (%)
- **Pressure** → Creates "Pressure Sensor" (hPa)
- **Latitude/Longitude** → Creates GPS sensors for location

### Supported Sensor Types:
- `temperature` - Temperature readings
- `humidity` - Humidity percentage
- `light` - Light intensity
- `pressure` - Air pressure
- `soil_moisture` - Soil moisture percentage
- `ph` - pH levels
- `battery` - Battery percentage
- `latitude` - GPS latitude
- `longitude` - GPS longitude
- `altitude` - Height above sea level

### Setting Thresholds:
You can set minimum and maximum values for each sensor. The system will:
- Show **green** when values are normal
- Show **yellow** for low warnings
- Show **red** for high alerts

---

## 🗺️ Map Features

### Device Location:
- If your device sends GPS coordinates (`latitude` and `longitude`), it appears on the map
- The map shows your land boundaries (if you drew them)
- **Green alert**: Device is inside the land boundary
- **Red alert**: Device is outside the land boundary

### Map Tools:
- **Locate Device**: Centers map on your device
- **Measure Distance**: Click to measure distance from device to any point

---

## 🚨 Monitoring and Alerts

### Device Status:
- **Green (Online)**: Device is sending data
- **Red (Offline)**: No data received recently
- **Yellow (Maintenance)**: Device in maintenance mode

### Sensor Alerts:
- **Normal**: Values within expected range
- **Low Alert**: Value below minimum threshold
- **High Alert**: Value above maximum threshold

### Live Updates:
- **MQTT devices**: Update every 10 seconds
- **Webhook devices**: Update when data is received
- **Web page**: Refreshes automatically

---

## 🔧 Troubleshooting

### Device Not Connecting:
1. Check your MQTT broker settings
2. Verify username and password
3. Make sure your device can reach the internet
4. Check the topic name matches exactly

### No Data Appearing:
1. Verify your device is sending the correct JSON format
2. Check device status in the web interface
3. Make sure the device ID matches exactly

### Location Not Showing:
1. Your device must send both `latitude` and `longitude`
2. Values should be decimal degrees (e.g., 39.528865)
3. Check that GPS coordinates are valid

---

## 📞 Getting Help

If you need help:
1. Check that your device sends data in the correct format shown above
2. Verify all settings match exactly (device ID, topics, etc.)
3. Make sure your device has internet connection
4. Check the device status page for error messages

---

## 📝 Quick Setup Example

Here's a complete example:

1. **Create Land**: "My Garden"
2. **Create Device**: 
   - ID: `garden_temp_01`
   - Type: MQTT
   - Host: `broker.emqx.io`
   - Topic: `garden/sensors`
3. **Device sends**:
   ```json
   {
     "sensors": [
       {"type": "temperature", "value": "22.5 celsius"},
       {"type": "humidity", "value": "65 percent"}
     ]
   }
   ```
4. **Result**: Temperature and humidity sensors appear automatically
5. **Monitor**: Check web interface for live data

Your IoT monitoring system is now ready to use!
