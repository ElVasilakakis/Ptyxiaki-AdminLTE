#include <Arduino.h>
#line 1 "C:\\Users\\tsigk\\Desktop\\Ptyxiaki\\ptyxiaki-final\\ptyxiaki-final\\arduino\\sensor-monitor\\sensor-monitor.ino"
#include <WiFi.h>
#include <MQTT.h>
#include <ArduinoJson.h>
#include <DHT.h>
#include <LiquidCrystal_I2C.h>
#include <TinyGPS++.h>

// DHT22 Configuration
#define DHTPIN 15
#define DHTTYPE DHT22
DHT dht(DHTPIN, DHTTYPE);

// LCD Configuration
LiquidCrystal_I2C lcd(0x27, 16, 2);

// GPS Configuration
HardwareSerial gpsSerial(2);
TinyGPSPlus gps;

// Pin Definitions
#define PHOTORESISTOR_PIN 32
#define POTENTIOMETER_PIN 34

const char ssid[] = "Wokwi-GUEST";
const char pass[] = "";

// MQTT Configuration
const char* mqtt_broker = "broker.emqx.io";
const int mqtt_port = 1883;
const char* mqtt_username = "mqttuser";
const char* mqtt_password = "12345678";

WiFiClient net;
MQTTClient client(1024); 
unsigned long lastMillis = 0;

// Device Configuration
const String device_id = "ESP32-DEV-001";

// Sensor variables
float temperature = 0.0;
float humidity = 0.0;
int lightLevel = 0;
int potValue = 0;

// GPS variables
double latitude = 0.0;
double longitude = 0.0;
bool gpsValid = false;

// Simplified geofence testing
bool generateInsideGeofence = true;
unsigned long lastGeofenceToggle = 0;

#line 55 "C:\\Users\\tsigk\\Desktop\\Ptyxiaki\\ptyxiaki-final\\ptyxiaki-final\\arduino\\sensor-monitor\\sensor-monitor.ino"
void setup();
#line 91 "C:\\Users\\tsigk\\Desktop\\Ptyxiaki\\ptyxiaki-final\\ptyxiaki-final\\arduino\\sensor-monitor\\sensor-monitor.ino"
void loop();
#line 126 "C:\\Users\\tsigk\\Desktop\\Ptyxiaki\\ptyxiaki-final\\ptyxiaki-final\\arduino\\sensor-monitor\\sensor-monitor.ino"
void connect();
#line 142 "C:\\Users\\tsigk\\Desktop\\Ptyxiaki\\ptyxiaki-final\\ptyxiaki-final\\arduino\\sensor-monitor\\sensor-monitor.ino"
void readSensors();
#line 156 "C:\\Users\\tsigk\\Desktop\\Ptyxiaki\\ptyxiaki-final\\ptyxiaki-final\\arduino\\sensor-monitor\\sensor-monitor.ino"
void generateGPSData();
#line 172 "C:\\Users\\tsigk\\Desktop\\Ptyxiaki\\ptyxiaki-final\\ptyxiaki-final\\arduino\\sensor-monitor\\sensor-monitor.ino"
void publishSensorData();
#line 214 "C:\\Users\\tsigk\\Desktop\\Ptyxiaki\\ptyxiaki-final\\ptyxiaki-final\\arduino\\sensor-monitor\\sensor-monitor.ino"
void publishGeoData();
#line 240 "C:\\Users\\tsigk\\Desktop\\Ptyxiaki\\ptyxiaki-final\\ptyxiaki-final\\arduino\\sensor-monitor\\sensor-monitor.ino"
void updateLCD();
#line 55 "C:\\Users\\tsigk\\Desktop\\Ptyxiaki\\ptyxiaki-final\\ptyxiaki-final\\arduino\\sensor-monitor\\sensor-monitor.ino"
void setup() {
    Serial.begin(115200);
    
    // Initialize sensors
    dht.begin();
    gpsSerial.begin(9600, SERIAL_8N1, 16, 17);
    
    // Initialize LCD
    lcd.init();
    lcd.backlight();
    lcd.setCursor(0, 0);
    lcd.print("Initializing...");
    
    // Connect WiFi and MQTT
    WiFi.begin(ssid, pass);
    client.begin(mqtt_broker, mqtt_port, net);
    
    connect();
    
    // Generate initial GPS data
    generateGPSData();
    
    Serial.println("=== ESP32 GEOFENCE DEVICE READY ===");
    Serial.println("Device ID: " + device_id);
    Serial.println("Sending to channels:");
    Serial.println("- " + device_id + "/sensors");
    Serial.println("- " + device_id + "/geosensors");
    Serial.println("===================================");
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Device Ready");
    lcd.setCursor(0, 1);
    lcd.print("Mode: " + String(generateInsideGeofence ? "INSIDE" : "OUTSIDE"));
}

void loop() {
    client.loop();
    
    if (!client.connected()) {
        connect();
    }
    
    // Toggle geofence mode every 2 minutes
    if (millis() - lastGeofenceToggle > 120000) {
        generateInsideGeofence = !generateInsideGeofence;
        lastGeofenceToggle = millis();
        generateGPSData();
        
        Serial.println("\n=== GEOFENCE MODE SWITCHED ===");
        Serial.println("Now: " + String(generateInsideGeofence ? "INSIDE" : "OUTSIDE"));
        Serial.println("==============================\n");
        
        // Update LCD
        lcd.clear();
        lcd.setCursor(0, 1);
        lcd.print("Mode: " + String(generateInsideGeofence ? "INSIDE" : "OUTSIDE"));
    }
    
    // Send data every 10 seconds
    if (millis() - lastMillis > 10000) {
        lastMillis = millis();
        readSensors();
        publishSensorData();
        publishGeoData();
        updateLCD();
    }
    
    delay(100);
}

void connect() {
    Serial.print("Connecting to WiFi");
    while (WiFi.status() != WL_CONNECTED) {
        Serial.print(".");
        delay(1000);
    }
    Serial.println(" Connected!");
    
    Serial.print("Connecting to MQTT");
    while (!client.connect(device_id.c_str(), mqtt_username, mqtt_password)) {
        Serial.print(".");
        delay(1000);
    }
    Serial.println(" Connected!");
}

void readSensors() {
    temperature = dht.readTemperature();
    humidity = dht.readHumidity();
    
    if (isnan(temperature)) temperature = 0.0;
    if (isnan(humidity)) humidity = 0.0;
    
    int rawLight = analogRead(PHOTORESISTOR_PIN);
    lightLevel = map(rawLight, 0, 4095, 0, 100);
    
    int rawPot = analogRead(POTENTIOMETER_PIN);
    potValue = map(rawPot, 0, 4095, 0, 100);
}

void generateGPSData() {
    if (generateInsideGeofence) {
        // Inside Colorado geofence
        latitude = 39.495387 + (random(0, 1000001) / 1000000.0) * (39.529577 - 39.495387);
        longitude = -107.744122 + (random(0, 1000001) / 1000000.0) * (-107.653999 - (-107.744122));
        Serial.println("✓ Generated INSIDE geofence coordinates");
    } else {
        // Outside geofence - San Francisco
        latitude = 37.7749 + (random(-1000, 1001) / 100000.0);
        longitude = -122.4194 + (random(-1000, 1001) / 100000.0);
        Serial.println("✓ Generated OUTSIDE geofence coordinates");
    }
    gpsValid = true;
    Serial.println("GPS: " + String(latitude, 6) + ", " + String(longitude, 6));
}

void publishSensorData() {
    String topic = device_id + "/sensors";
    
    // Create JSON document with sensors array
    DynamicJsonDocument doc(800);
    JsonArray sensors = doc.createNestedArray("sensors");
    
    // Add temperature sensor
    JsonObject tempSensor = sensors.createNestedObject();
    tempSensor["type"] = "thermal";
    tempSensor["value"] = String(temperature, 1) + " celsius";
    
    // Add humidity sensor
    JsonObject humSensor = sensors.createNestedObject();
    humSensor["type"] = "humidity";
    humSensor["value"] = String(humidity, 1) + " percent";
    
    // Add light sensor
    JsonObject lightSensor = sensors.createNestedObject();
    lightSensor["type"] = "light";
    lightSensor["value"] = String(lightLevel) + " percent";
    
    // Add potentiometer sensor
    JsonObject potSensor = sensors.createNestedObject();
    potSensor["type"] = "potentiometer";
    potSensor["value"] = String(potValue) + " percent";
    
    // Serialize and send
    String message;
    serializeJson(doc, message);
    
    if (client.publish(topic, message)) {
        Serial.println("✓ Sensors data sent to: " + topic);
        Serial.println("  Temperature: " + String(temperature, 1) + "°C");
        Serial.println("  Humidity: " + String(humidity, 1) + "%");
        Serial.println("  Light: " + String(lightLevel) + "%");
        Serial.println("  Potentiometer: " + String(potValue) + "%");
    } else {
        Serial.println("✗ Failed to send sensors data");
    }
}

void publishGeoData() {
    if (!gpsValid) {
        Serial.println("GPS not valid, skipping geo data");
        return;
    }
    
    String topic = device_id + "/geosensors";
    
    DynamicJsonDocument doc(300);
    doc["type"] = "location";
    doc["latitude"] = latitude;
    doc["longitude"] = longitude;
    doc["status"] = generateInsideGeofence ? "inside_geofence" : "outside_geofence";
    
    String message;
    serializeJson(doc, message);
    
    if (client.publish(topic, message)) {
        Serial.println("✓ Location data sent to: " + topic);
        Serial.println("  GPS: " + String(latitude, 6) + ", " + String(longitude, 6));
        Serial.println("  Status: " + String(generateInsideGeofence ? "INSIDE" : "OUTSIDE"));
    } else {
        Serial.println("✗ Failed to send location data");
    }
}

void updateLCD() {
    lcd.clear();
    
    // First line: GPS coordinates
    lcd.setCursor(0, 0);
    lcd.print("GPS: " + String(latitude, 4));
    
    // Second line: Status and longitude
    lcd.setCursor(0, 1);
    String status = generateInsideGeofence ? "IN" : "OUT";
    lcd.print(status + ": " + String(longitude, 4));
}

