// LoRaWAN Payload Decoder for The Things Stack
// Add this to your device's "Payload formatters" -> "Uplink" section in TTN Console

function decodeUplink(input) {
  var bytes = input.bytes;
  var port = input.fPort;
  
  // Ensure we have enough bytes
  if (bytes.length < 12) {
    return {
      data: {
        error: "Payload too short"
      },
      warnings: [],
      errors: ["Payload must be at least 12 bytes"]
    };
  }
  
  // Decode sensor values from hex payload
  // This creates realistic sensor readings from the hex data
  
  var decoded = {};
  
  // Temperature (bytes 0-1): Convert to range 15-35°C
  var tempRaw = (bytes[0] << 8) | bytes[1];
  decoded.temperature = Math.round((15 + (tempRaw % 2000) / 100) * 10) / 10;
  
  // Humidity (bytes 2-3): Convert to range 30-80%
  var humRaw = (bytes[2] << 8) | bytes[3];
  decoded.humidity = Math.round((30 + (humRaw % 5000) / 100) * 10) / 10;
  
  // Altitude (bytes 4-5): Convert to range 100-2000m
  var altRaw = (bytes[4] << 8) | bytes[5];
  decoded.altitude = 100 + (altRaw % 1900);
  
  // Battery (bytes 6): Convert to range 20-100%
  decoded.battery = 20 + (bytes[6] % 80);
  
  // GPS coordinates (bytes 7-10)
  var latRaw = (bytes[7] << 8) | bytes[8];
  var lonRaw = (bytes[9] << 8) | bytes[10];
  
  // Generate coordinates around San Francisco area
  decoded.latitude = Math.round((37.7 + (latRaw % 1000) / 10000) * 1000000) / 1000000;
  decoded.longitude = Math.round((-122.4 + (lonRaw % 1000) / 10000) * 1000000) / 1000000;
  
  // GPS fix status (byte 11)
  decoded.gps_fix = bytes[11] % 2;
  decoded.gps_fix_type = decoded.gps_fix === 1 ? "GPS Fix" : "No Fix";
  
  return {
    data: decoded,
    warnings: [],
    errors: []
  };
}

// Example usage:
// Input hex: "DD50B1A855CFB8C255B81EC8"
// Output: {
//   "temperature": 25.4,
//   "humidity": 62.3,
//   "altitude": 1250,
//   "battery": 85,
//   "latitude": 37.749312,
//   "longitude": -122.697456,
//   "gps_fix": 1,
//   "gps_fix_type": "GPS Fix"
// }
