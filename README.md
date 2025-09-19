# IoT Sensor Monitoring System

A Laravel-based IoT sensor monitoring system that collects sensor data via **webhooks** and provides real-time monitoring capabilities through a web dashboard.

## 🚀 Features

- **Webhook-based data collection** - Server-friendly, no background processes needed
- **Real-time sensor monitoring** - Live dashboard with sensor readings
- **Multi-device support** - Manage multiple IoT devices and sensors
- **Automatic sensor detection** - Automatically creates sensors from incoming data
- **Secure authentication** - Token-based webhook security
- **LoRaWAN integration** - Support for The Things Network (TTN)
- **User management** - Multi-user system with device ownership
- **Land/Location management** - Organize devices by location
- **Notification system** - Alerts and notifications for sensor events

## 📋 Requirements

- PHP 8.1+
- Laravel 11.x
- MySQL/PostgreSQL database
- Web server (Apache/Nginx)

## 🛠️ Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/ElVasilakakis/Ptyxiaki-AdminLTE.git
   cd Ptyxiaki-AdminLTE
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database** in `.env`
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Build assets**
   ```bash
   npm run build
   ```

## 🔧 Webhook Configuration

### Creating a Webhook Connector

1. **Login to the dashboard**
2. **Navigate to "Connectors"** (formerly MQTT Brokers)
3. **Click "Add New Connector"**
4. **Fill in the details**:
   - **Name**: Descriptive name for your connector
   - **Type**: Select "webhook" 
   - **Description**: Optional description

### Adding Devices

1. **Go to "Devices"** section
2. **Click "Add Device"**
3. **Configure device**:
   - **Device ID**: Unique identifier for your device
   - **Name**: Human-readable name
   - **Connector**: Select your webhook connector
   - **Protocol**: Will default to "webhook"

### Getting Webhook URLs

For each device, you can get the webhook URL and instructions:

**Method 1: Via API**
```bash
GET /api/webhook/mqtt/{deviceId}/instructions
```

**Method 2: Via Dashboard**
- Go to your connector details
- View webhook instructions for each device

### Webhook URL Format

```
POST https://yourapp.com/api/webhook/mqtt/{deviceId}?token={secure_token}
```

- `{deviceId}`: Your device's unique ID
- `{secure_token}`: Automatically generated secure token

## 📡 Sending Sensor Data

### Data Formats Supported

**Structured Format (Recommended)**
```json
{
  "sensors": [
    {"type": "temperature", "value": 25.5},
    {"type": "humidity", "value": 60},
    {"type": "battery", "value": 85}
  ]
}
```

**Flat Format**
```json
{
  "temperature": 25.5,
  "humidity": 60,
  "battery": 85
}
```

### Supported Sensor Types

| Type | Unit | Description |
|------|------|-------------|
| `temperature` | °C | Temperature readings |
| `humidity` | % | Humidity percentage |
| `pressure` | hPa | Atmospheric pressure |
| `light` | lux | Light intensity |
| `motion` | - | Motion detection |
| `battery` | % | Battery level |
| `latitude` | ° | GPS latitude |
| `longitude` | ° | GPS longitude |

### Example Implementations

**Arduino/ESP32 Example**
```cpp
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

void sendSensorData(float temp, float humidity) {
    HTTPClient http;
    http.begin("https://yourapp.com/api/webhook/mqtt/DEVICE123?token=your_token");
    http.addHeader("Content-Type", "application/json");
    
    StaticJsonDocument<200> doc;
    doc["temperature"] = temp;
    doc["humidity"] = humidity;
    
    String jsonString;
    serializeJson(doc, jsonString);
    
    int httpResponseCode = http.POST(jsonString);
    http.end();
}
```

**Python Example**
```python
import requests
import json

def send_sensor_data(device_id, token, sensor_data):
    url = f"https://yourapp.com/api/webhook/mqtt/{device_id}"
    headers = {"Content-Type": "application/json"}
    params = {"token": token}
    
    response = requests.post(url, json=sensor_data, headers=headers, params=params)
    return response.status_code == 200

# Usage
sensor_data = {
    "temperature": 25.5,
    "humidity": 60,
    "battery": 85
}
send_sensor_data("DEVICE123", "your_token", sensor_data)
```

**cURL Example**
```bash
curl -X POST 'https://yourapp.com/api/webhook/mqtt/DEVICE123?token=your_token' \
  -H 'Content-Type: application/json' \
  -d '{"temperature": 25.5, "humidity": 60}'
```

## 🔐 Security

### Webhook Authentication

Each device has a unique, automatically generated secure token:
- **Token format**: SHA256 hash of device_id + user_id + app_key
- **Validation**: Server validates token on each request
- **Security**: Prevents unauthorized data submission

### Best Practices

1. **Keep tokens secure** - Don't expose tokens in public repositories
2. **Use HTTPS** - Always use HTTPS in production
3. **Monitor logs** - Check webhook logs for suspicious activity
4. **Rotate tokens** - Regenerate device tokens if compromised

## 🌐 LoRaWAN Integration

The system also supports LoRaWAN devices via The Things Network (TTN):

1. **Configure TTN webhook** to point to:
   ```
   POST https://yourapp.com/api/lorawan/webhook
   ```

2. **TTN automatically sends** device data in the correct format

## 📊 Dashboard Features

- **Real-time sensor readings** - Live updates from all devices
- **Historical data** - Charts and graphs of sensor history
- **Device management** - Add, edit, and monitor devices
- **Land/Location organization** - Group devices by location
- **User management** - Multi-user support with permissions
- **Notifications** - Alerts for sensor thresholds and events

## 🔧 Development

### Running Locally

```bash
# Start development server
php artisan serve

# Watch for asset changes
npm run dev

# Run background jobs (if needed)
php artisan queue:work
```

### Testing Webhooks Locally

```bash
# Test webhook endpoint
curl -X POST 'http://localhost:8000/api/webhook/test' \
  -H 'Content-Type: application/json' \
  -d '{"test": "data"}'
```

## 📝 API Documentation

### Webhook Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/webhook/mqtt/{deviceId}` | Receive sensor data |
| `GET` | `/api/webhook/mqtt/{deviceId}/instructions` | Get setup instructions |
| `POST` | `/api/webhook/test` | Test webhook functionality |

### Response Formats

**Success Response**
```json
{
  "success": true,
  "message": "Processed 3 sensor readings",
  "sensors_updated": 3
}
```

**Error Response**
```json
{
  "success": false,
  "message": "Invalid or missing token"
}
```

## 🚀 Deployment

### Server Requirements

- **PHP 8.1+** with required extensions
- **Web server** (Apache/Nginx) with URL rewriting
- **Database** (MySQL/PostgreSQL)
- **HTTPS** recommended for production

### Deployment Steps

1. **Upload files** to your server
2. **Install dependencies**: `composer install --no-dev`
3. **Configure environment** variables
4. **Run migrations**: `php artisan migrate --force`
5. **Build assets**: `npm run build`
6. **Set permissions** on storage and cache directories
7. **Configure web server** to point to `/public` directory

### Environment Variables

```env
APP_NAME="IoT Sensor Monitor"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourapp.com

DB_CONNECTION=mysql
DB_HOST=your_db_host
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## 🆘 Troubleshooting

### Common Issues

**Webhook not receiving data**
- Check device token is correct
- Verify webhook URL format
- Check server logs for errors
- Ensure Content-Type header is set

**Sensors not being created**
- Verify data format is correct
- Check sensor type names are recognized
- Review webhook processing logs

**Device showing offline**
- Ensure webhook calls are successful
- Check device is sending data regularly
- Verify token authentication

### Logs

Check Laravel logs for webhook processing:
```bash
tail -f storage/logs/laravel.log
```

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📞 Support

For support and questions:
- Create an issue on GitHub
- Check the documentation
- Review the logs for error details
