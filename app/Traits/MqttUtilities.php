<?php

namespace App\Traits;

trait MqttUtilities
{
    /**
     * Check if an MQTT topic pattern matches a given topic.
     */
    public function topicMatches(string $pattern, string $topic): bool
    {
        // Convert MQTT wildcards to regex
        $pattern = str_replace(['+', '#'], ['[^/]+', '.*'], $pattern);
        $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';
        
        return preg_match($pattern, $topic);
    }

    /**
     * Normalize sensor type using configuration mappings.
     */
    public function normalizeSensorType(string $key): string
    {
        $key = strtolower($key);
        $mappings = config('mqtt.sensor_mappings', []);
        
        return $mappings[$key] ?? $key;
    }

    /**
     * Get unit for sensor type from configuration.
     */
    public function getUnitForSensorType(string $sensorType): ?string
    {
        $units = config('mqtt.sensor_units', []);
        return $units[$sensorType] ?? null;
    }

    /**
     * Extract numeric value from mixed input.
     */
    public function extractNumericValue($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            preg_match('/(-?\d+\.?\d*)/', $value, $matches);
            return isset($matches[0]) ? (float) $matches[0] : 0.0;
        }

        return 0.0;
    }

    /**
     * Extract unit from string value.
     */
    public function extractUnit($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $unitMappings = config('mqtt.unit_mappings', []);

        foreach ($unitMappings as $text => $symbol) {
            if (stripos($value, $text) !== false) {
                return $symbol;
            }
        }

        return null;
    }

    /**
     * Detect broker type from device configuration.
     */
    public function detectBrokerType($device): string
    {
        // Check if explicitly set in device configuration
        if ($device->connection_broker) {
            return strtolower($device->connection_broker);
        }

        // Auto-detect based on hostname
        $host = strtolower($device->mqtt_host);
        
        if (str_contains($host, 'thethings') || str_contains($host, 'ttn')) {
            return 'thethings_stack';
        }
        
        if (str_contains($host, 'hivemq')) {
            return 'hivemq';
        }
        
        if (str_contains($host, 'emqx')) {
            return 'emqx';
        }
        
        if (str_contains($host, 'mosquitto') || str_contains($host, 'eclipse-mosquitto')) {
            return 'mosquitto';
        }
        
        // Check for common Mosquitto broker hostnames/IPs
        if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1') || 
            str_contains($host, 'test.mosquitto.org') || str_contains($host, 'broker.mqttdashboard.com')) {
            return 'mosquitto';
        }

        // Default fallback
        return 'emqx';
    }

    /**
     * Get the actual port for a device, respecting custom port configurations.
     */
    public function getDevicePort($device): int
    {
        // Use the device's configured port if available
        if ($device->port && is_numeric($device->port)) {
            return (int) $device->port;
        }
        
        // Fall back to standard ports based on SSL setting only if no port is configured
        return $device->use_ssl ? 8883 : 1883;
    }

    /**
     * Generate a unique client ID for MQTT connection.
     */
    public function generateClientId(string $brokerType, string $brokerKey): string
    {
        $prefix = 'laravel_' . strtolower($brokerType);
        $timestamp = time();
        $hash = substr(md5($brokerKey), 0, 8);
        
        return "{$prefix}_{$timestamp}_{$hash}";
    }

    /**
     * Check if a broker is known to be problematic.
     */
    public function isProblematicBroker(string $host): bool
    {
        $problematicBrokers = config('mqtt.problematic_brokers', []);
        return in_array($host, $problematicBrokers);
    }

    /**
     * Get broker-specific configuration.
     */
    public function getBrokerConfig(string $brokerType): array
    {
        $brokers = config('mqtt.brokers', []);
        return $brokers[$brokerType] ?? [];
    }

    /**
     * Determine which MQTT library to use for a device.
     */
    public function selectMqttLibrary($device, string $brokerType): string
    {
        $brokerConfig = $this->getBrokerConfig($brokerType);
        
        // Check if broker forces a specific library
        if (isset($brokerConfig['library']) && $brokerConfig['library'] !== 'auto') {
            return $brokerConfig['library'];
        }
        
        // For The Things Stack, always use Bluerhinos
        if ($brokerType === 'thethings_stack') {
            return 'bluerhinos';
        }
        
        // For SSL connections, prefer php-mqtt/client
        if ($device->use_ssl) {
            return 'php-mqtt';
        }
        
        // Default to Bluerhinos for non-SSL
        return 'bluerhinos';
    }

    /**
     * Get keepalive value considering broker-specific limits.
     */
    public function getKeepaliveForBroker($device, string $brokerType): int
    {
        $brokerConfig = $this->getBrokerConfig($brokerType);
        $deviceKeepalive = $device->keepalive ?: config('mqtt.default_keepalive', 60);
        
        // Apply broker-specific maximum if configured
        if (isset($brokerConfig['max_keepalive'])) {
            return min($deviceKeepalive, $brokerConfig['max_keepalive']);
        }
        
        return $deviceKeepalive;
    }
}
