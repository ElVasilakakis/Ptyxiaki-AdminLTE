# MQTT Polling Listener Guide

## Overview

The MQTT Polling Listener is a refactored solution that addresses the issue where The Things Stack (TTS) devices cause the persistent MQTT listener to get stuck. Instead of maintaining persistent connections, this approach polls devices every 10 seconds to read the latest messages.

## Problem Solved

The original `mqtt:listen-all` command maintains persistent connections to MQTT brokers, which can get stuck when The Things Stack devices are active. This happens because:

1. TTS connections can timeout or hang indefinitely
2. The persistent connection approach blocks the entire listener
3. Error handling becomes complex with multiple persistent connections

## Solution: Polling Approach

The new `mqtt:poll` command:

1. **Connects briefly** to each device every 10 seconds
2. **Reads available messages** within a 3-second window
3. **Disconnects immediately** after reading messages
4. **Processes messages** using the existing payload handler
5. **Updates device status** based on connection success/failure

## Usage

### Basic Usage
```bash
php artisan mqtt:poll
```

### With Custom Interval (default: 10 seconds)
```bash
php artisan mqtt:poll --interval=15
```

### With Timeout (stops after specified seconds)
```bash
php artisan mqtt:poll --timeout=300
```

### Filter Specific Device
```bash
php artisan mqtt:poll --device=sensor_device_2
```

### Using the Batch Script
```bash
mqtt_poll.bat
```

## Command Options

| Option | Description | Default |
|--------|-------------|---------|
| `--interval` | Polling interval in seconds | 10 |
| `--timeout` | Total runtime in seconds (0 = infinite) | 0 |
| `--device` | Filter devices by device_id pattern | none |

## Advantages

### ✅ Reliability
- No persistent connections that can hang
- Each poll is independent
- Automatic recovery from connection issues

### ✅ Timeout Protection
- Maximum 10 seconds per connection attempt
- Maximum 3 seconds for message reading
- Graceful handling of slow/unresponsive brokers

### ✅ Resource Efficiency
- Connections are closed immediately after use
- Lower memory usage
- No connection pooling overhead

### ✅ Monitoring
- Clear status updates for each poll
- Device status tracking (online/error)
- Detailed logging for troubleshooting

## Comparison with Original Listener

| Feature | Original (`mqtt:listen-all`) | New (`mqtt:poll`) |
|---------|------------------------------|-------------------|
| Connection Type | Persistent | Temporary |
| TTS Compatibility | ❌ Gets stuck | ✅ Works reliably |
| Resource Usage | Higher (persistent) | Lower (temporary) |
| Real-time | ✅ Immediate | ⏱️ 10-second intervals |
| Error Recovery | Complex | Automatic |
| Timeout Handling | Limited | Comprehensive |

## When to Use Each

### Use `mqtt:poll` when:
- You have The Things Stack devices
- You experience connection hanging issues
- You prefer reliability over real-time updates
- You want automatic error recovery

### Use `mqtt:listen-all` when:
- You need real-time message processing
- All your devices are on stable brokers (EMQX, HiveMQ)
- You don't have The Things Stack devices
- Network latency is critical

## Technical Details

### Connection Flow
1. **Connect** to device broker with 10-second timeout
2. **Subscribe** to device topics
3. **Listen** for messages for 3 seconds
4. **Collect** all received messages
5. **Disconnect** from broker
6. **Process** messages through payload handler
7. **Update** device status
8. **Wait** for next polling interval

### Error Handling
- Connection timeouts are handled gracefully
- Failed devices are marked as 'error' status
- Successful connections update 'online' status
- Individual device failures don't affect others

### Logging
- Debug logs for connection attempts
- Info logs for successful polls
- Warning logs for device errors
- Error logs for critical failures

## Configuration

The polling listener uses the same configuration as the original listener:

- Device settings from the database
- MQTT configuration from `config/mqtt.php`
- Broker detection and authentication
- SSL/TLS settings when required

## Monitoring and Troubleshooting

### Check Device Status
```sql
SELECT name, device_id, status, last_seen_at 
FROM devices 
WHERE connection_type = 'mqtt';
```

### View Logs
```bash
tail -f storage/logs/laravel.log | grep MQTT
```

### Test Single Device
```bash
php artisan mqtt:poll --device=your_device_id --timeout=30
```

## Migration from Original Listener

1. **Stop** the original listener (`Ctrl+C`)
2. **Start** the new polling listener:
   ```bash
   php artisan mqtt:poll
   ```
3. **Monitor** the output to ensure devices are being polled
4. **Check** device status in your application

No database changes or configuration updates are required.
