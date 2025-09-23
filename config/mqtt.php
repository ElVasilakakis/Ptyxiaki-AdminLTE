<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MQTT Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for MQTT connections and message processing.
    |
    */

    'default_connection_timeout' => env('MQTT_CONNECTION_TIMEOUT', 5),
    'default_keepalive' => env('MQTT_KEEPALIVE', 60),
    'default_reconnect_attempts' => env('MQTT_RECONNECT_ATTEMPTS', 3),
    'message_processing_sleep' => env('MQTT_MESSAGE_SLEEP', 100000), // microseconds
    'device_reload_interval' => env('MQTT_DEVICE_RELOAD_INTERVAL', 60), // seconds
    
    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    
    'queue' => [
        'connection' => env('MQTT_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'default_queue' => env('MQTT_DEFAULT_QUEUE', 'mqtt'),
        'tts_queue' => env('MQTT_TTS_QUEUE', 'tts'), // Separate queue for The Things Stack
        'job_timeout' => env('MQTT_JOB_TIMEOUT', 30), // seconds
        'job_tries' => env('MQTT_JOB_TRIES', 3),
        'job_backoff' => [5, 10, 30], // seconds
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Exponential Backoff Configuration
    |--------------------------------------------------------------------------
    */
    
    'backoff' => [
        'delays' => [5, 10, 30, 60, 120], // seconds
        'max_attempts' => env('MQTT_MAX_RECONNECT_ATTEMPTS', 10),
        'jitter_percentage' => 0.1, // 10% jitter
    ],
    
    /*
    |--------------------------------------------------------------------------
    | SSL/TLS Configuration
    |--------------------------------------------------------------------------
    */
    
    'ssl' => [
        'verify_peer' => env('MQTT_SSL_VERIFY_PEER', false),
        'verify_peer_name' => env('MQTT_SSL_VERIFY_PEER_NAME', false),
        'allow_self_signed' => env('MQTT_SSL_ALLOW_SELF_SIGNED', true),
        'certificates_path' => storage_path('certificates'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Broker-specific Configuration
    |--------------------------------------------------------------------------
    */
    
    'brokers' => [
        'thethings_stack' => [
            'max_keepalive' => 30,
            'library' => 'bluerhinos', // Force Bluerhinos for TTS
            'connection_timeout' => 10,
            'processing_timeout' => 5, // seconds for TTS message processing
            'backoff_multiplier' => 2, // Longer backoff for TTS
        ],
        'hivemq' => [
            'requires_certificates' => true,
            'library' => 'auto', // Auto-select based on SSL
            'connection_timeout' => 15,
        ],
        'emqx' => [
            'library' => 'auto', // Auto-select based on SSL
            'connection_timeout' => 10,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Known Problematic Brokers
    |--------------------------------------------------------------------------
    */
    
    'problematic_brokers' => [
        'eu1.cloud.thethings.industries',
        // Add more as needed
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Sensor Type Mappings
    |--------------------------------------------------------------------------
    */
    
    'sensor_mappings' => [
        'temp' => 'temperature',
        'temperature' => 'temperature',
        'thermal' => 'temperature',
        'humid' => 'humidity',
        'humidity' => 'humidity',
        'light' => 'light',
        'potentiometer' => 'potentiometer',
        'pot' => 'potentiometer',
        'lat' => 'latitude',
        'latitude' => 'latitude',
        'lng' => 'longitude',
        'lon' => 'longitude',
        'longitude' => 'longitude',
        'pressure' => 'pressure',
        'soil_moisture' => 'soil_moisture',
        'ph' => 'ph',
        'battery' => 'battery',
        'altitude' => 'altitude',
        'alt' => 'altitude',
        'gps_fix' => 'gps_quality',
        'gps_quality' => 'gps_quality',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Sensor Units
    |--------------------------------------------------------------------------
    */
    
    'sensor_units' => [
        'temperature' => '°C',
        'humidity' => '%',
        'light' => '%',
        'potentiometer' => '%',
        'pressure' => 'hPa',
        'soil_moisture' => '%',
        'latitude' => '°',
        'longitude' => '°',
        'battery' => '%',
        'altitude' => 'm',
        'gps_quality' => 'fix_code',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Unit Text Mappings
    |--------------------------------------------------------------------------
    */
    
    'unit_mappings' => [
        'celsius' => '°C',
        'fahrenheit' => '°F',
        'percent' => '%',
        'percentage' => '%',
        'degrees' => '°',
    ],
];
