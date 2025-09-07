@extends('layouts.application.app')

@section('styles')
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <style>
        #map {
            height: 400px;
            width: 100%;
            border-radius: 0.375rem;
            z-index: 1;
            /* Fix map layering issues */
        }

        .leaflet-container {
            height: 400px !important;
            width: 100% !important;
        }

        /* Your existing styles */
        .mqtt-status {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            display: inline-block;
        }

        .mqtt-status.disconnected {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .mqtt-status.connected {
            background-color: #dcfce7;
            color: #16a34a;
        }

        .mqtt-status.connecting {
            background-color: #fef3c7;
            color: #d97706;
        }

        .sensor-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f9fafb;
        }

        .sensor-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
        }

        .sensor-type {
            font-size: 0.875rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .last-update {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        /* Location status styles */
        .location-status {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .location-status.inside {
            background-color: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .location-status.outside {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .location-status.unknown {
            background-color: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }

        /* Sensor alert styles */
        .sensor-alert {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sensor-alert.high {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .sensor-alert.low {
            background-color: #fef3c7;
            color: #d97706;
            border: 1px solid #fed7aa;
        }

        .sensor-card.alert-high {
            border-color: #dc2626;
            background: #fef2f2;
        }

        .sensor-card.alert-low {
            border-color: #d97706;
            background: #fffbeb;
        }
    </style>
@endsection


@section('scripts')
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- MQTT.js -->
    <script src="https://unpkg.com/mqtt@5.3.4/dist/mqtt.min.js"></script>

    <script>
        // Global variables
        let map;
        let deviceMarker;
        let mqttClient;
        let isConnected = false;
        let scanInterval;
        let isPaused = false;
        let landGeoJSONLayer = null;
        let connectionTimeout;

        // Device data
        const device = @json($device);
        const mqttBroker = @json($device->mqttBroker);
        const landData = @json($device->land);
        const previousSensors = @json($device->sensors);

        // DEBUG: Log initial data
        console.log('🔧 Device Data:', device);
        console.log('🔧 MQTT Broker Config:', mqttBroker);
        console.log('🔧 Land Data:', landData);
        console.log('🔧 Previous Sensors:', previousSensors);

        // Initialize map
        function initMap() {
            setTimeout(function() {
                const mapContainer = document.getElementById('map');
                if (!mapContainer) {
                    console.error('❌ Map container not found');
                    return;
                }

                // Default center (Greece)
                let defaultLat = 39.0742;
                let defaultLng = 21.8243;
                let defaultZoom = 6;

                // Check if device has current location from previous data
                if (device.current_location && device.current_location.coordinates) {
                    defaultLat = device.current_location.coordinates[1];
                    defaultLng = device.current_location.coordinates[0];
                    defaultZoom = 13;
                    console.log('📍 Using current location:', defaultLat, defaultLng);
                } else if (device.location && device.location.coordinates) {
                    defaultLat = device.location.coordinates[1];
                    defaultLng = device.location.coordinates[0];
                    defaultZoom = 13;
                    console.log('📍 Using device location:', defaultLat, defaultLng);
                }

                // Initialize map with proper options
                map = L.map('map', {
                    center: [defaultLat, defaultLng],
                    zoom: defaultZoom,
                    scrollWheelZoom: true,
                    zoomControl: true,
                    attributionControl: true
                });

                // Add tile layer
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                // Add land GeoJSON polygon if available
                if (landData && landData.geojson) {
                    try {
                        const landGeoJSON = typeof landData.geojson === 'string' ? JSON.parse(landData.geojson) : landData.geojson;
                        landGeoJSONLayer = L.geoJSON(landGeoJSON, {
                            style: {
                                color: '#3388ff',
                                weight: 2,
                                opacity: 0.8,
                                fillColor: '#3388ff',
                                fillOpacity: 0.2
                            }
                        }).addTo(map).bindPopup(`<b>${landData.land_name}</b><br>Area: ${landData.area || 'Unknown'} hectares`);
                        console.log('🗺️ Land GeoJSON added to map');
                    } catch (error) {
                        console.error('❌ Error adding land GeoJSON:', error);
                    }
                }

                // Add device marker if location exists
                if (device.current_location && device.current_location.coordinates) {
                    const lat = device.current_location.coordinates[1];
                    const lng = device.current_location.coordinates[0];
                    addDeviceMarker(lat, lng, 'Last known location');
                    updateLocationStatus(lat, lng);
                } else if (device.location && device.location.coordinates) {
                    const lat = device.location.coordinates[1];
                    const lng = device.location.coordinates[0];
                    addDeviceMarker(lat, lng, 'Device location');
                    updateLocationStatus(lat, lng);
                }

                // Force map resize after initialization
                setTimeout(function() {
                    map.invalidateSize();
                }, 100);

                console.log('✅ Map initialized successfully');
            }, 100);
        }

        // Update MQTT status with debugging
        function updateMqttStatus(status, message = '') {
            const statusElement = document.getElementById('mqtt-status');
            const connectBtn = document.getElementById('connect-btn');
            const pauseBtn = document.getElementById('pause-btn');

            console.log(`📡 MQTT Status Update: ${status}${message ? ' - ' + message : ''}`);

            if (!statusElement) {
                console.warn('⚠️ MQTT status element not found');
                return;
            }

            statusElement.className = `mqtt-status ${status}`;

            switch (status) {
                case 'disconnected':
                    statusElement.textContent = 'Disconnected' + (message ? ': ' + message : '');
                    connectBtn.textContent = 'Connect';
                    connectBtn.disabled = false;
                    if (pauseBtn) pauseBtn.style.display = 'none';
                    break;
                case 'connecting':
                    statusElement.textContent = 'Connecting...';
                    connectBtn.textContent = 'Connecting...';
                    connectBtn.disabled = true;
                    if (pauseBtn) pauseBtn.style.display = 'none';
                    break;
                case 'connected':
                    statusElement.textContent = isPaused ? 'Connected (Paused)' : 'Connected to MQTT Broker';
                    connectBtn.textContent = 'Disconnect';
                    connectBtn.disabled = false;
                    if (pauseBtn) pauseBtn.style.display = 'inline-block';
                    break;
            }
        }

        // Toggle pause/resume functionality
        function togglePause() {
            const pauseBtn = document.getElementById('pause-btn');
            
            isPaused = !isPaused;
            
            if (isPaused) {
                pauseBtn.innerHTML = '<i class="ph-play me-2"></i>Resume';
                pauseBtn.classList.remove('btn-warning');
                pauseBtn.classList.add('btn-success');
                console.log('⏸️ Data processing paused');
            } else {
                pauseBtn.innerHTML = '<i class="ph-pause me-2"></i>Pause';
                pauseBtn.classList.remove('btn-success');
                pauseBtn.classList.add('btn-warning');
                console.log('▶️ Data processing resumed');
            }
            
            updateMqttStatus('connected');
        }

        // Enhanced MQTT connection with debugging
        function connectMqtt() {
            if (isConnected) {
                console.log('🔌 Disconnecting MQTT...');
                if (mqttClient) {
                    mqttClient.end();
                }
                if (scanInterval) {
                    clearInterval(scanInterval);
                }
                if (connectionTimeout) {
                    clearTimeout(connectionTimeout);
                }
                isConnected = false;
                updateMqttStatus('disconnected');
                return;
            }

            console.log('🚀 Starting MQTT connection...');
            updateMqttStatus('connecting');

            // Build MQTT broker URL using device protocol
            const protocol = device.protocol || 'ws';
            
            console.log('🔧 Device protocol:', protocol);
            console.log('🔧 MQTT Broker data:', mqttBroker);
            
            let port;
            let brokerUrl;
            
            switch (protocol) {
                case 'ws':
                case 'wss':
                    port = mqttBroker.websocket_port || 8083;
                    const path = mqttBroker.path || '/mqtt';
                    brokerUrl = `${protocol}://${mqttBroker.host}:${port}${path}`;
                    break;
                case 'mqtt':
                    console.warn('⚠️ Native MQTT protocol not supported in browsers. Using WebSocket instead.');
                    port = mqttBroker.websocket_port || 8083;
                    const mqttPath = mqttBroker.path || '/mqtt';
                    brokerUrl = `ws://${mqttBroker.host}:${port}${mqttPath}`;
                    break;
                case 'mqtts':
                    console.warn('⚠️ Native MQTTS protocol not supported in browsers. Using secure WebSocket instead.');
                    port = mqttBroker.ssl_port || 8883;
                    const mqttsPath = mqttBroker.path || '/mqtt';
                    brokerUrl = `wss://${mqttBroker.host}:${port}${mqttsPath}`;
                    break;
                default:
                    port = mqttBroker.websocket_port || 8083;
                    const defaultPath = mqttBroker.path || '/mqtt';
                    brokerUrl = `ws://${mqttBroker.host}:${port}${defaultPath}`;
            }
            
            console.log('🔗 Final broker URL:', brokerUrl);

            // MQTT connection options with debugging
            const options = {
                clientId: `web_client_${Math.random().toString(16).substr(2, 8)}`,
                clean: true,
                connectTimeout: 30000,
                reconnectPeriod: 5000,
                keepalive: mqttBroker.keepalive || 60,
                protocolVersion: 4, // Force MQTT v3.1.1
            };

            console.log('🔧 Base MQTT options:', options);

            // Add authentication if provided
            if (mqttBroker.username) {
                options.username = mqttBroker.username;
                options.password = mqttBroker.password || '';
                console.log('🔐 MQTT Authentication:', {
                    username: options.username,
                    passwordLength: options.password.length,
                    passwordPreview: options.password.substring(0, 10) + '...'
                });
            }

            try {
                console.log('🔌 Creating MQTT client...');
                mqttClient = mqtt.connect(brokerUrl, options);

                // Connection timeout handler
                connectionTimeout = setTimeout(() => {
                    if (!isConnected) {
                        console.error('⏰ MQTT connection timeout after 30 seconds');
                        updateMqttStatus('disconnected', 'Connection timeout');
                        if (mqttClient) {
                            mqttClient.end();
                        }
                    }
                }, 30000);

                mqttClient.on('connect', function() {
                    clearTimeout(connectionTimeout);
                    console.log('✅ Connected to MQTT broker successfully');
                    isConnected = true;
                    updateMqttStatus('connected');

                    // Subscribe to device topics
                    if (device.topics && device.topics.length > 0) {
                        console.log('📨 Subscribing to topics:', device.topics);
                        device.topics.forEach(topic => {
                            mqttClient.subscribe(topic, function(err) {
                                if (err) {
                                    console.error(`❌ Failed to subscribe to topic: ${topic}`, err);
                                } else {
                                    console.log(`✅ Subscribed to topic: ${topic}`);
                                }
                            });
                        });
                    } else {
                        console.warn('⚠️ No topics defined for subscription');
                    }

                    // Start scanning every 10 seconds
                    scanInterval = setInterval(function() {
                        console.log('🔍 Scanning for messages...');
                    }, 10000);
                });

                mqttClient.on('message', function(topic, message) {
                    const messageStr = message.toString();
                    console.log('📩 Received message:', {
                        topic: topic,
                        message: messageStr,
                        length: messageStr.length,
                        timestamp: new Date().toISOString()
                    });
                    
                    if (!isPaused) {
                        handleMqttMessage(topic, messageStr);
                    } else {
                        console.log('⏸️ Message processing paused, message ignored');
                    }
                });

                mqttClient.on('error', function(error) {
                    clearTimeout(connectionTimeout);
                    console.error('❌ MQTT connection error:', error);
                    console.error('🔍 Error details:', {
                        name: error.name,
                        message: error.message,
                        code: error.code,
                        stack: error.stack
                    });
                    
                    // Specific error handling
                    if (error.message && error.message.includes('Connection refused')) {
                        console.error('🚫 Connection refused - check credentials and permissions');
                    }
                    
                    updateMqttStatus('disconnected', error.message);
                    isConnected = false;
                });

                mqttClient.on('close', function() {
                    clearTimeout(connectionTimeout);
                    console.log('🔌 MQTT connection closed');
                    updateMqttStatus('disconnected');
                    isConnected = false;
                    if (scanInterval) {
                        clearInterval(scanInterval);
                    }
                });

                mqttClient.on('offline', function() {
                    console.log('📴 MQTT client offline');
                });

                mqttClient.on('reconnect', function() {
                    console.log('🔄 MQTT attempting to reconnect...');
                });

                mqttClient.on('disconnect', function() {
                    console.log('↔️ MQTT client disconnected');
                });

            } catch (error) {
                console.error('💥 Failed to connect to MQTT broker:', error);
                updateMqttStatus('disconnected', error.message);
            }
        }

        // Handle incoming MQTT messages with debugging
        function handleMqttMessage(topic, messageStr) {
            console.log('🔄 Processing MQTT message:', {
                topic: topic,
                messagePreview: messageStr.substring(0, 100) + (messageStr.length > 100 ? '...' : ''),
                fullLength: messageStr.length
            });

            try {
                const message = JSON.parse(messageStr);
                console.log('✅ Parsed message:', message);

                if (message.type === 'location') {
                    console.log('📍 Handling location message');
                    handleLocationMessage(message);
                } else if (message.sensors) {
                    console.log('📊 Handling sensors message');
                    handleSensorsMessage(message);
                } else {
                    console.log('❓ Unknown message type:', message);
                }

            } catch (error) {
                console.error('❌ Failed to parse MQTT message:', error);
                console.error('🔍 Raw message:', messageStr);
            }
        }

        // Handle location messages with debugging
        function handleLocationMessage(locationData) {
            console.log('📍 Processing location data:', locationData);
            
            const { latitude, longitude, status } = locationData;

            if (latitude && longitude) {
                console.log('✅ Valid coordinates received:', { latitude, longitude, status });
                
                // Update device marker with new location and status
                addDeviceMarker(latitude, longitude, `Status: ${status || 'unknown'}`);
                
                // Update location status display
                updateLocationStatus(latitude, longitude);

                // Center map on new location
                if (map) {
                    map.setView([latitude, longitude], 13);
                    console.log('🗺️ Map centered on new location');
                }

                // Store sensor data
                storeSensorData({
                    device_id: device.id,
                    sensor_type: 'location',
                    value: { latitude, longitude, status },
                    unit: null
                });

                console.log('✅ Location updated successfully');
            } else {
                console.warn('⚠️ Invalid location data received:', locationData);
            }
        }

        // Additional helper functions...
        function addDeviceMarker(lat, lng, popupText) {
            console.log('📌 Adding device marker:', { lat, lng, popupText });
            
            const isInside = checkLocationStatus(lat, lng);
            
            let iconColor = '#6b7280'; // default gray
            let iconClass = 'ph-map-pin';
            
            if (isInside === true) {
                iconColor = '#16a34a'; // green for inside
                iconClass = 'ph-map-pin';
            } else if (isInside === false) {
                iconColor = '#dc2626'; // red for outside
                iconClass = 'ph-warning';
            }
            
            const customIcon = L.divIcon({
                html: `<div style="background-color: ${iconColor}; width: 25px; height: 25px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                         <i class="${iconClass}" style="color: white; font-size: 14px;"></i>
                       </div>`,
                className: 'custom-div-icon',
                iconSize: [25, 25],
                iconAnchor: [12, 12]
            });
            
            if (deviceMarker) {
                deviceMarker.setLatLng([lat, lng]);
                deviceMarker.setIcon(customIcon);
            } else {
                deviceMarker = L.marker([lat, lng], { icon: customIcon })
                    .addTo(map)
                    .bindPopup(`<b>${device.name}</b><br>Device ID: ${device.device_id}<br>${popupText}`);
            }
        }

        function checkLocationStatus(lat, lng) {
            // Implementation for checking if location is within boundaries
            return null; // Placeholder
        }

        function updateLocationStatus(lat, lng) {
            // Implementation for updating location status display
            console.log('📍 Updating location status display');
        }

        function handleSensorsMessage(sensorsData) {
            console.log('📊 Processing sensors data:', sensorsData);
            // Implementation for handling sensor data
        }

        function storeSensorData(sensorData) {
            console.log('💾 Storing sensor data:', sensorData);
            // Implementation for storing sensor data via AJAX
        }

        function loadPreviousSensorData() {
            console.log('📈 Loading previous sensor data');
            // Implementation for loading previous sensor data
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Initializing application...');
            
            initMap();
            loadPreviousSensorData();
            updateMqttStatus('disconnected');

            // Connect button event
            const connectBtn = document.getElementById('connect-btn');
            if (connectBtn) {
                connectBtn.addEventListener('click', connectMqtt);
                console.log('✅ Connect button event listener added');
            } else {
                console.error('❌ Connect button not found');
            }
            
            // Pause button event
            const pauseBtn = document.getElementById('pause-btn');
            if (pauseBtn) {
                pauseBtn.addEventListener('click', togglePause);
                console.log('✅ Pause button event listener added');
            } else {
                console.error('❌ Pause button not found');
            }

            console.log('✅ Application initialized successfully');
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            console.log('🧹 Cleaning up on page unload...');
            if (mqttClient && isConnected) {
                mqttClient.end();
            }
            if (scanInterval) {
                clearInterval(scanInterval);
            }
            if (connectionTimeout) {
                clearTimeout(connectionTimeout);
            }
        });

        // Global error handler for debugging
        window.addEventListener('error', function(e) {
            console.error('💥 Global error:', {
                message: e.message,
                filename: e.filename,
                lineno: e.lineno,
                colno: e.colno,
                error: e.error
            });
        });
    </script>
@endsection


@section('content')
    <div class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Device: {{ $device->name }}</h6>
                        <div class="d-flex align-items-center gap-3">
                            <div id="mqtt-status" class="mqtt-status disconnected">Disconnected</div>
                            <button type="button" id="pause-btn" class="btn btn-warning" style="display: none;">
                                <i class="ph-pause me-2"></i>Pause
                            </button>
                            <button type="button" id="connect-btn" class="btn btn-primary">
                                Connect
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Device Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="fw-semibold mb-3">Device Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Device ID:</strong></td>
                                        <td>{{ $device->device_id }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Type:</strong></td>
                                        <td>
                                            <span class="badge bg-info">{{ ucfirst($device->device_type) }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span
                                                class="badge bg-{{ $device->status === 'online' ? 'success' : ($device->status === 'offline' ? 'secondary' : ($device->status === 'maintenance' ? 'warning' : 'danger')) }}">
                                                {{ ucfirst($device->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Active:</strong></td>
                                        <td>
                                            <span class="badge bg-{{ $device->is_active ? 'success' : 'secondary' }}">
                                                {{ $device->is_active ? 'Yes' : 'No' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>MQTT Broker:</strong></td>
                                        <td>{{ $device->mqttBroker->name }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Land:</strong></td>
                                        <td>{{ $device->land->land_name }}</td>
                                    </tr>
                                </table>
                            </div>

                            <div class="col-md-6">
                                <h6 class="fw-semibold mb-3">MQTT Topics</h6>
                                @if ($device->topics && count($device->topics) > 0)
                                    <ul class="list-group list-group-flush">
                                        @foreach ($device->topics as $topic)
                                            <li class="list-group-item px-0">
                                                <code>{{ $topic }}</code>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-muted">No topics configured</p>
                                @endif
                            </div>
                        </div>

                        <!-- Map -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="fw-semibold mb-3">Device Location</h6>
                                <div id="map"></div>
                            </div>
                        </div>

                        <!-- Sensor Alerts -->
                        @php
                            $alertSensors = $device->sensors->filter(function($sensor) {
                                return $sensor->alert_enabled && $sensor->getAlertStatus() !== 'normal';
                            });
                        @endphp
                        @if ($alertSensors->count() > 0)
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold mb-3">
                                        <i class="ph-warning-circle me-2 text-danger"></i>Sensor Alerts
                                    </h6>
                                    <div id="sensor-alerts-container" class="row">
                                        @foreach ($alertSensors as $sensor)
                                            @php
                                                $alertStatus = $sensor->getAlertStatus();
                                                $alertClass = $alertStatus === 'high' ? 'danger' : 'warning';
                                                $alertIcon = $alertStatus === 'high' ? 'ph-arrow-up' : 'ph-arrow-down';
                                                $alertText = $alertStatus === 'high' ? 'High Alert' : 'Low Alert';
                                            @endphp
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="alert alert-{{ $alertClass }} mb-0">
                                                    <div class="d-flex align-items-center">
                                                        <i class="{{ $alertIcon }} me-2"></i>
                                                        <div>
                                                            <strong>{{ $sensor->sensor_type }}</strong> - {{ $alertText }}
                                                            <br>
                                                            <small>Current: {{ $sensor->getFormattedValue() }}</small>
                                                            <br>
                                                            <small>Threshold: {{ $alertStatus === 'high' ? 'Max ' . $sensor->alert_threshold_max : 'Min ' . $sensor->alert_threshold_min }}{{ $sensor->unit ? ' ' . $sensor->unit : '' }}</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Sensors -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="fw-semibold mb-3">Live Sensor Data</h6>
                                <div id="sensors-container" class="row">
                                    <div class="col-12">
                                        <p class="text-muted">Connect to MQTT broker to see live sensor data</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Device Details -->
                        @if ($device->description)
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold mb-3">Description</h6>
                                    <p>{{ $device->description }}</p>
                                </div>
                            </div>
                        @endif

                        <!-- Timestamps -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Installation Date</h6>
                                        <p class="card-text">
                                            @if ($device->installed_at)
                                                <i class="ph-calendar me-2 text-info"></i>
                                                {{ $device->installed_at->format('M d, Y') }}
                                            @else
                                                <i class="ph-calendar me-2 text-muted"></i>
                                                Not specified
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Last Seen</h6>
                                        <p class="card-text">
                                            @if ($device->last_seen_at)
                                                <i class="ph-clock me-2 text-success"></i>
                                                {{ $device->last_seen_at->format('M d, Y H:i') }}
                                                <small
                                                    class="text-muted d-block">{{ $device->last_seen_at->diffForHumans() }}</small>
                                            @else
                                                <i class="ph-clock me-2 text-muted"></i>
                                                Never seen
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="{{ route('app.devices.index') }}" class="btn btn-outline-secondary">
                                <i class="ph-arrow-left me-2"></i>Back to Devices
                            </a>
                            <div class="d-flex gap-2">
                                <a href="{{ route('app.devices.edit', $device) }}" class="btn btn-outline-primary">
                                    <i class="ph-pencil me-2"></i>Edit Device
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
