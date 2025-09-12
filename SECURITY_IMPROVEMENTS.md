# Security Improvements: User-Specific Broker Access

## Overview

The console commands have been updated to ensure that they only access brokers and devices that belong to the authenticated user. This prevents unauthorized access to other users' data and improves the overall security of the application.

## Changes Made

### 1. MQTTListener Command (`app/Console/Commands/MQTTListener.php`)

**Before:**
- Queried ALL active MQTT brokers in the system
- Connected to ALL devices regardless of ownership
- No user authentication or authorization

**After:**
- Requires `--user` parameter to specify which user's resources to access
- Only queries brokers owned by the specified user (`user_id` filter)
- Only connects to devices owned by the specified user
- Validates that the user exists before proceeding

### 2. LoRaWANListener Command (`app/Console/Commands/LoRaWANListener.php`)

**Before:**
- Queried ALL LoRaWAN devices in the system
- No user authentication or authorization
- Could process messages from any device

**After:**
- Requires `--user` parameter to specify which user's devices to access
- Only queries devices owned by the specified user (`user_id` filter)
- Only processes messages from devices owned by the specified user
- Validates that the user exists before proceeding

## Usage Examples

### MQTT Listener

```bash
# Listen to all MQTT devices for user ID 1
php artisan mqtt:listen --user=1

# Listen to a specific device for user ID 1
php artisan mqtt:listen --user=1 --device=sensor_001

# Listen to devices on a specific broker for user ID 1
php artisan mqtt:listen --user=1 --broker=5
```

### LoRaWAN Listener

```bash
# Listen to all LoRaWAN devices for user ID 1
php artisan lorawan:listen --user=1

# Listen to a specific LoRaWAN device for user ID 1
php artisan lorawan:listen --user=1 --device=lorawan_device_001
```

## Payload Formats

### MQTT Payload Format

The MQTT listener expects messages in JSON format or simple values. Here are the supported formats:

#### JSON Payload (Recommended)
```json
{
  "temperature": 23.5,
  "humidity": 65.2,
  "pressure": 1013.25,
  "light": 450,
  "battery": 85,
  "motion": 1,
  "latitude": 37.7749,
  "longitude": -122.4194
}
```

#### Simple Value Payload
For single sensor readings, you can send just the value:
```
23.5
```

#### Supported MQTT Topics
The listener subscribes to these topic patterns for each device:
- `devices/{device_id}/sensors/+`
- `sensors/{device_id}/+`
- `{device_id}/sensors/+`
- `{device_id}/data`
- `device/{device_id}/+`
- `{device_id}` (simple device ID topic)

#### Supported Sensor Types (MQTT)
The system automatically recognizes these sensor types:

| Sensor Key | Type | Unit | Description |
|------------|------|------|-------------|
| `temperature`, `temp`, `celsius` | temperature | °C | Temperature readings |
| `humidity`, `humid`, `rh` | humidity | % | Humidity percentage |
| `pressure`, `press`, `atm` | pressure | hPa | Atmospheric pressure |
| `light`, `lux`, `brightness` | light | lux | Light intensity |
| `motion`, `pir`, `movement` | motion | - | Motion detection |
| `battery`, `bat`, `power` | battery | % | Battery level |
| `latitude`, `lat` | latitude | ° | GPS latitude |
| `longitude`, `lng`, `lon` | longitude | ° | GPS longitude |

#### Example MQTT Messages

**Multi-sensor payload:**
```json
{
  "temperature": 22.3,
  "humidity": 58.7,
  "battery": 92,
  "timestamp": "2025-09-10T18:30:00Z"
}
```

**Single sensor on topic `sensors/device_001/temperature`:**
```
22.3
```

### LoRaWAN Payload Format

The LoRaWAN listener expects messages from The Things Stack (TTN) in their standard uplink format.

#### TTN Uplink Message Structure
```json
{
  "end_device_ids": {
    "device_id": "your-device-id",
    "application_ids": {
      "application_id": "your-app-id"
    }
  },
  "received_at": "2025-09-10T18:30:00.123456789Z",
  "uplink_message": {
    "decoded_payload": {
      "temperature": 23.5,
      "humidity": 65.2,
      "altitude": 150.5,
      "battery": 85,
      "latitude": 37.7749,
      "longitude": -122.4194,
      "gps_fix": 1,
      "gps_fix_type": "3D"
    },
    "rx_metadata": [...],
    "settings": {...}
  }
}
```

#### LoRaWAN Topic Pattern
The listener subscribes to:
```
v3/{ttn_username}/devices/{device_id}/up
```

#### Supported Sensor Types (LoRaWAN)
The system recognizes these keys in the `decoded_payload`:

| Sensor Key | Type | Unit | Description |
|------------|------|------|-------------|
| `temperature` | temperature | °C | Temperature readings |
| `humidity` | humidity | % | Humidity percentage |
| `altitude` | altitude | m | Altitude/elevation |
| `battery` | battery | % | Battery level |
| `latitude` | latitude | ° | GPS latitude |
| `longitude` | longitude | ° | GPS longitude |
| `gps_fix` | gps_fix | - | GPS fix status (0/1) |
| `gps_fix_type` | gps_fix_type | - | GPS fix type (2D/3D) |

#### Example LoRaWAN Decoded Payloads

**Environmental sensor:**
```json
{
  "temperature": 24.1,
  "humidity": 62.3,
  "battery": 78
}
```

**GPS tracker:**
```json
{
  "latitude": 37.7749,
  "longitude": -122.4194,
  "altitude": 45.2,
  "gps_fix": 1,
  "gps_fix_type": "3D",
  "battery": 65
}
```

**Combined environmental + GPS:**
```json
{
  "temperature": 21.8,
  "humidity": 55.4,
  "latitude": 37.7749,
  "longitude": -122.4194,
  "altitude": 120.5,
  "battery": 88,
  "gps_fix": 1
}
```

### Message Processing Notes

1. **Automatic Sensor Creation**: The system automatically creates sensor records for recognized sensor types
2. **Timestamp Handling**: 
   - MQTT: Uses current timestamp if not provided
   - LoRaWAN: Uses `received_at` from TTN or current timestamp
3. **Device Status**: Both listeners automatically update device status to "online" when messages are received
4. **Unknown Sensors**: Unknown sensor types in MQTT are created as generic sensors; in LoRaWAN they are logged as warnings
5. **Data Validation**: Non-numeric values are accepted but may not trigger alerts or calculations

## Security Benefits

1. **Data Isolation**: Each user can only access their own brokers and devices
2. **Unauthorized Access Prevention**: Commands will fail if no user is specified
3. **User Validation**: Commands verify that the specified user exists
4. **Audit Trail**: All operations are logged with user context
5. **Principle of Least Privilege**: Commands only access the minimum required resources

## Error Handling

The commands now provide clear error messages for security violations:

- `❌ User ID is required. Use --user=<user_id> to specify which user's brokers to access.`
- `❌ User with ID {userId} not found.`
- `⚠️ No active MQTT brokers found for user {userId}`
- `⚠️ No active LoRaWAN devices found for user {userId}`

## Database Schema Requirements

Both commands rely on the following database relationships:

### MqttBroker Model
- `user_id` field to associate brokers with users
- `forUser($userId)` scope for filtering

### Device Model
- `user_id` field to associate devices with users
- `forUser($userId)` scope for filtering

## Implementation Details

### Key Security Filters Added

1. **Broker Discovery** (MQTTListener):
   ```php
   $query = MqttBroker::where('status', 'active')
       ->where('user_id', $userId)  // User filter added
       ->where(function($q) {
           $q->where('type', '!=', 'lorawan')
             ->orWhereNull('type')
             ->orWhere('type', 'mqtt');
       });
   ```

2. **Device Subscription** (MQTTListener):
   ```php
   $query = Device::where('mqtt_broker_id', $broker->id)
       ->where('user_id', $userId)  // User filter added
       ->where('is_active', true);
   ```

3. **LoRaWAN Device Discovery**:
   ```php
   $query = Device::with('mqttBroker')
       ->where('user_id', $userId)  // User filter added
       ->whereHas('mqttBroker', function($q) {
           $q->where('type', 'lorawan')
             ->orWhere('host', 'like', '%thethings.industries%');
       })
       ->where('is_active', true);
   ```

4. **Message Processing Validation** (LoRaWANListener):
   ```php
   $device = Device::where('device_id', $deviceId)
       ->where('user_id', $this->currentUserId)  // User filter added
       ->first();
   ```

## Backward Compatibility

⚠️ **Breaking Change**: These updates introduce a breaking change as the `--user` parameter is now required. Any existing scripts or automation that use these commands will need to be updated to include the user parameter.

## Recommendations

1. **Update Deployment Scripts**: Ensure all deployment and monitoring scripts include the `--user` parameter
2. **User Context**: Consider implementing a way to automatically determine the user context (e.g., from environment variables or configuration)
3. **Logging**: Monitor logs for unauthorized access attempts
4. **Documentation**: Update any operational documentation to reflect the new command syntax

## Testing

The security improvements have been tested with:

- ✅ Commands require `--user` parameter
- ✅ Commands validate user existence
- ✅ Commands only access user-specific resources
- ✅ Proper error messages for invalid scenarios
- ✅ Help documentation updated
