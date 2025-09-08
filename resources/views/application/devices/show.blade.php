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
        }

        .leaflet-container {
            height: 400px !important;
            width: 100% !important;
        }

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
            transition: all 0.3s ease;
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

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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

                let defaultLat = 39.0742;
                let defaultLng = 21.8243;
                let defaultZoom = 6;

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

                map = L.map('map', {
                    center: [defaultLat, defaultLng],
                    zoom: defaultZoom,
                    scrollWheelZoom: true,
                    zoomControl: true,
                    attributionControl: true
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

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

                setTimeout(function() {
                    map.invalidateSize();
                }, 100);

                console.log('✅ Map initialized successfully');
            }, 100);
        }

        // Update MQTT status
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

        // Enhanced MQTT connection with extensive debugging
        function connectMqtt() {
            console.log('🚀 Connect button clicked, isConnected:', isConnected);
            
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
                    port = device.ws_port || 8083;
                    const wsPath = mqttBroker.path || '/mqtt';
                    brokerUrl = `ws://${mqttBroker.host}:${port}${wsPath}`;
                    break;
                case 'wss':
                    port = device.wss_port || 8084;
                    const wssPath = mqttBroker.path || '/mqtt';
                    brokerUrl = `wss://${mqttBroker.host}:${port}${wssPath}`;
                    break;
                case 'mqtt':
                    const wsPort = device.ws_port || 8083;
                    const mqttPath = mqttBroker.path || '/mqtt';
                    brokerUrl = `ws://${mqttBroker.host}:${wsPort}${mqttPath}`;
                    console.warn('⚠️ MQTT protocol converted to WebSocket for browser compatibility');
                    break;
                case 'mqtts':
                    const wssPort = device.wss_port || 8084;
                    const mqttsPath = mqttBroker.path || '/mqtt';
                    brokerUrl = `wss://${mqttBroker.host}:${wssPort}${mqttsPath}`;
                    console.warn('⚠️ MQTTS protocol converted to WSS for browser compatibility');
                    break;
                default:
                    port = device.ws_port || 8083;
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
                    passwordLength: options.password.length
                });
            } else {
                console.log('🔐 No MQTT authentication configured');
            }

            try {
                console.log('🔌 Creating MQTT client...');
                mqttClient = mqtt.connect(brokerUrl, options);
                console.log('✅ MQTT client created, waiting for events...');

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

                mqttClient.on('connect', function(connack) {
                    clearTimeout(connectionTimeout);
                    console.log('✅ Connected to MQTT broker successfully!', connack);
                    isConnected = true;
                    updateMqttStatus('connected');

                    // Subscribe to device topics
                    if (device.topics && device.topics.length > 0) {
                        console.log('📨 Subscribing to topics:', device.topics);
                        device.topics.forEach(topic => {
                            mqttClient.subscribe(topic, { qos: 0 }, function(err) {
                                if (err) {
                                    console.error(`❌ Failed to subscribe to topic: ${topic}`, err);
                                } else {
                                    console.log(`✅ Successfully subscribed to topic: ${topic}`);
                                }
                            });
                        });
                    } else {
                        console.warn('⚠️ No topics defined for subscription');
                    }

                    // Start scanning every 10 seconds
                    scanInterval = setInterval(function() {
                        console.log('🔍 Scanning for messages... (client connected:', mqttClient.connected, ')');
                    }, 10000);
                });

                mqttClient.on('message', function(topic, message, packet) {
                    const messageStr = message.toString();
                    console.log('📩 Received MQTT message:', {
                        topic: topic,
                        messageLength: messageStr.length,
                        qos: packet.qos,
                        retain: packet.retain,
                        messagePreview: messageStr.substring(0, 200) + (messageStr.length > 200 ? '...' : ''),
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
                        code: error.code
                    });
                    
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
                    updateMqttStatus('disconnected', 'Client offline');
                });

                mqttClient.on('reconnect', function() {
                    console.log('🔄 MQTT attempting to reconnect...');
                    updateMqttStatus('connecting', 'Reconnecting...');
                });

                mqttClient.on('disconnect', function(packet) {
                    console.log('↔️ MQTT client disconnected', packet);
                    updateMqttStatus('disconnected', 'Disconnected');
                });

            } catch (error) {
                console.error('💥 Failed to create MQTT client:', error);
                updateMqttStatus('disconnected', error.message);
            }
        }

        // Handle incoming MQTT messages with debugging
        function handleMqttMessage(topic, messageStr) {
            console.log('🔄 Processing MQTT message from topic:', topic);
            console.log('📝 Message content:', messageStr);

            try {
                const message = JSON.parse(messageStr);
                console.log('✅ Successfully parsed JSON message:', message);

                if (message.type === 'location') {
                    console.log('📍 Handling location message');
                    handleLocationMessage(message);
                } else if (message.sensors) {
                    console.log('📊 Handling sensors message with', message.sensors.length, 'sensors');
                    handleSensorsMessage(message);
                } else {
                    console.log('❓ Unknown message type or structure:', message);
                }

            } catch (error) {
                console.error('❌ Failed to parse MQTT message as JSON:', error);
                console.error('🔍 Raw message content:', messageStr);
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
                console.log('💾 Storing location data in backend...');
                storeSensorData({
                    device_id: device.id,
                    sensor_type: 'location',
                    value: { latitude, longitude, status },
                    unit: null
                });

            } else {
                console.warn('⚠️ Invalid location data received - missing coordinates:', locationData);
            }
        }

        // Handle sensors messages with debugging
        function handleSensorsMessage(sensorsData) {
            console.log('📊 Processing sensors data:', sensorsData);
            
            if (sensorsData.sensors && Array.isArray(sensorsData.sensors)) {
                console.log('📊 Found', sensorsData.sensors.length, 'sensors to process');
                
                sensorsData.sensors.forEach((sensor, index) => {
                    const { type, value } = sensor;
                    console.log(`📊 Processing sensor ${index + 1}:`, { type, value });
                    
                    // Parse value to separate numeric value and unit
                    const parsedValue = parseValueAndUnit(value);
                    console.log(`📊 Parsed value for ${type}:`, parsedValue);
                    
                    storeSensorData({
                        device_id: device.id,
                        sensor_type: type,
                        value: parsedValue.value,
                        unit: parsedValue.unit
                    });
                });
            } else {
                console.warn('⚠️ Invalid sensors data - missing sensors array:', sensorsData);
            }
        }

        // Parse value and unit from string like "54.0 celsius"
        function parseValueAndUnit(valueString) {
            if (typeof valueString !== 'string') {
                return { value: valueString, unit: null };
            }

            const parts = valueString.trim().split(' ');
            if (parts.length >= 2) {
                const numericPart = parts[0];
                const unitPart = parts.slice(1).join(' ');
                
                if (!isNaN(numericPart)) {
                    return { 
                        value: parseFloat(numericPart), 
                        unit: unitPart 
                    };
                }
            }
            
            return { value: valueString, unit: null };
        }

        // Store sensor data via AJAX with debugging
        function storeSensorData(sensorData) {
            console.log('💾 Attempting to store sensor data:', sensorData);
            
            fetch(`{{ route('app.devices.store-sensors', $device) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(sensorData)
            })
            .then(response => {
                console.log('📡 Backend response received:', response.status, response.statusText);
                return response.json();
            })
            .then(data => {
                console.log('✅ Backend response data:', data);
                if (data.success) {
                    console.log('✅ Sensor data stored successfully, updating UI...');
                    updateSensorCard(data.sensor);
                    updateAlerts();
                } else {
                    console.error('❌ Backend reported failure:', data.message);
                }
            })
            .catch(error => {
                console.error('❌ Error storing sensor data:', error);
            });
        }

        // Update sensor card in the UI
        function updateSensorCard(sensor) {
            console.log('🎨 Updating sensor card for:', sensor.sensor_type, sensor);
            
            const container = document.getElementById('sensors-container');
            
            if (container.querySelector('.text-muted')) {
                container.innerHTML = '';
                console.log('🧹 Cleared placeholder text');
            }

            let card = document.getElementById(`sensor-${sensor.id}`);
            
            if (!card) {
                console.log('➕ Creating new sensor card for:', sensor.sensor_type);
                card = createSensorCard(sensor);
                container.appendChild(card);
            } else {
                console.log('🔄 Updating existing sensor card for:', sensor.sensor_type);
                updateExistingSensorCard(card, sensor);
            }
        }

        // Create new sensor card
        function createSensorCard(sensor) {
            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4 mb-3';
            
            const alertClass = getAlertClass(sensor.alert_status);
            
            col.innerHTML = `
                <div id="sensor-${sensor.id}" class="sensor-card ${alertClass}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="sensor-type">${sensor.sensor_type}</div>
                        <div class="pulse" style="width: 8px; height: 8px; background: #16a34a; border-radius: 50%;"></div>
                    </div>
                    <div class="sensor-value">${sensor.formatted_value}</div>
                    <div class="last-update">Just now</div>
                    ${sensor.alert_status !== 'normal' ? `
                        <div class="sensor-alert ${sensor.alert_status} mt-2">
                            <i class="${sensor.alert_status === 'high' ? 'ph-arrow-up' : 'ph-arrow-down'}"></i>
                            ${sensor.alert_status === 'high' ? 'High Alert' : 'Low Alert'}
                        </div>
                    ` : ''}
                </div>
            `;
            
            return col;
        }

        // Update existing sensor card with null checks
        function updateExistingSensorCard(card, sensor) {
            const alertClass = getAlertClass(sensor.alert_status);
            const sensorCard = card.querySelector('.sensor-card');
            
            // Add null check
            if (!sensorCard) {
                console.warn('⚠️ Sensor card element not found, recreating...');
                // Remove the old card and create a new one
                card.remove();
                const container = document.getElementById('sensors-container');
                const newCard = createSensorCard(sensor);
                container.appendChild(newCard);
                return;
            }
            
            sensorCard.className = `sensor-card ${alertClass}`;
            
            const valueElement = sensorCard.querySelector('.sensor-value');
            const updateElement = sensorCard.querySelector('.last-update');
            
            if (valueElement) valueElement.textContent = sensor.formatted_value;
            if (updateElement) updateElement.textContent = 'Just now';
            
            // Update alert
            let alertDiv = sensorCard.querySelector('.sensor-alert');
            if (sensor.alert_status !== 'normal') {
                if (!alertDiv) {
                    alertDiv = document.createElement('div');
                    alertDiv.className = `sensor-alert ${sensor.alert_status} mt-2`;
                    sensorCard.appendChild(alertDiv);
                }
                alertDiv.innerHTML = `
                    <i class="${sensor.alert_status === 'high' ? 'ph-arrow-up' : 'ph-arrow-down'}"></i>
                    ${sensor.alert_status === 'high' ? 'High Alert' : 'Low Alert'}
                `;
            } else if (alertDiv) {
                alertDiv.remove();
            }
        }

        // Get alert CSS class
        function getAlertClass(alertStatus) {
            switch (alertStatus) {
                case 'high': return 'alert-high';
                case 'low': return 'alert-low';
                default: return '';
            }
        }

        // Update alerts section
        function updateAlerts() {
            console.log('🚨 Updating alerts section...');
            fetch(`{{ route('app.devices.alerts', $device) }}`)
                .then(response => response.json())
                .then(data => {
                    const alertsContainer = document.getElementById('sensor-alerts-container');
                    if (alertsContainer && data.alerts) {
                        alertsContainer.innerHTML = data.alerts;
                        console.log('✅ Alerts updated, count:', data.alert_count);
                    }
                })
                .catch(error => {
                    console.error('❌ Error updating alerts:', error);
                });
        }

        // Helper functions for map and location
        function addDeviceMarker(lat, lng, popupText) {
            console.log('📌 Adding device marker:', { lat, lng, popupText });
            
            const isInside = checkLocationStatus(lat, lng);
            
            let iconColor = '#6b7280'; // default gray
            let iconClass = 'ph-map-pin';
            
            if (isInside === true) {
                iconColor = '#16a34a'; // green for inside
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
            // Check if point is within GeoJSON boundaries
            if (landGeoJSONLayer) {
                const point = L.latLng(lat, lng);
                let isInside = false;
                
                landGeoJSONLayer.eachLayer(function(layer) {
                    if (layer.getBounds && layer.getBounds().contains(point)) {
                        isInside = true;
                    }
                });
                
                return isInside;
            }
            return null;
        }

        function updateLocationStatus(lat, lng) {
            const statusElement = document.getElementById('location-status');
            if (!statusElement) return;
            
            const isInside = checkLocationStatus(lat, lng);
            
            if (isInside === true) {
                statusElement.className = 'location-status inside';
                statusElement.textContent = 'Inside Geofence';
            } else if (isInside === false) {
                statusElement.className = 'location-status outside';
                statusElement.textContent = 'Outside Geofence';
            } else {
                statusElement.className = 'location-status unknown';
                statusElement.textContent = 'Location Status Unknown';
            }
        }

        function loadPreviousSensorData() {
            console.log('📈 Loading previous sensor data...');
            
            if (previousSensors && previousSensors.length > 0) {
                console.log('📊 Found', previousSensors.length, 'previous sensors');
                const container = document.getElementById('sensors-container');
                container.innerHTML = '';
                
                previousSensors.forEach(sensor => {
                    console.log('📊 Loading sensor:', sensor.sensor_type);
                    updateSensorCard(sensor);
                });
            } else {
                console.log('📊 No previous sensor data found');
            }
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Page loaded, initializing application...');
            
            initMap();
            loadPreviousSensorData();
            updateMqttStatus('disconnected');

            const connectBtn = document.getElementById('connect-btn');
            if (connectBtn) {
                connectBtn.addEventListener('click', connectMqtt);
                console.log('✅ Connect button event listener added');
            } else {
                console.error('❌ Connect button not found!');
            }
            
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
                            <div id="location-status" class="location-status unknown">Location Status Unknown</div>
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
                                    @if ($device->sensors->count() > 0)
                                        @foreach ($device->sensors as $sensor)
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div id="sensor-{{ $sensor->id }}" class="sensor-card {{ $sensor->getAlertStatus() === 'high' ? 'alert-high' : ($sensor->getAlertStatus() === 'low' ? 'alert-low' : '') }}">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="sensor-type">{{ $sensor->sensor_type }}</div>
                                                        <div style="width: 8px; height: 8px; background: {{ $sensor->hasRecentReading() ? '#16a34a' : '#6b7280' }}; border-radius: 50%;"></div>
                                                    </div>
                                                    <div class="sensor-value">{{ $sensor->getFormattedValue() }}</div>
                                                    <div class="last-update">{{ $sensor->getTimeSinceLastReading() ?? 'No readings yet' }}</div>
                                                    @if ($sensor->getAlertStatus() !== 'normal')
                                                        <div class="sensor-alert {{ $sensor->getAlertStatus() }} mt-2">
                                                            <i class="{{ $sensor->getAlertStatus() === 'high' ? 'ph-arrow-up' : 'ph-arrow-down' }}"></i>
                                                            {{ $sensor->getAlertStatus() === 'high' ? 'High Alert' : 'Low Alert' }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="col-12">
                                            <p class="text-muted">Connect to MQTT broker to see live sensor data</p>
                                        </div>
                                    @endif
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
