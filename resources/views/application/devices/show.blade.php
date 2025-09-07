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
    </style>
@endsection


@section('scripts')
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- MQTT.js -->
    <script src="https://unpkg.com/mqtt@5.3.4/dist/mqtt.min.js"></script>

    <script>
        let map;
        let deviceMarker;
        let mqttClient;
        let isConnected = false;
        let scanInterval;
        let isPaused = false;
        let landGeoJSONLayer = null;

        // Device data
        const device = @json($device);
        const mqttBroker = @json($device->mqttBroker);
        const landData = @json($device->land);
        const previousSensors = @json($device->sensors);

        // Initialize map
        function initMap() {
            // Wait for DOM to be ready
            setTimeout(function() {
                const mapContainer = document.getElementById('map');
                if (!mapContainer) {
                    console.error('Map container not found');
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
                } else if (device.location && device.location.coordinates) {
                    defaultLat = device.location.coordinates[1];
                    defaultLng = device.location.coordinates[0];
                    defaultZoom = 13;
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
                        console.log('Land GeoJSON added to map');
                    } catch (error) {
                        console.error('Error adding land GeoJSON:', error);
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

                console.log('Map initialized successfully');
            }, 100);
        }

        // Load previous sensor data on page load
        function loadPreviousSensorData() {
            if (previousSensors && previousSensors.length > 0) {
                const sensorContainer = document.getElementById('sensors-container');
                
                // Clear the default message
                sensorContainer.innerHTML = '';
                
                // Group sensors by type and get the latest reading for each type
                const latestSensors = {};
                previousSensors.forEach(sensor => {
                    if (!latestSensors[sensor.sensor_type] || 
                        new Date(sensor.reading_timestamp) > new Date(latestSensors[sensor.sensor_type].reading_timestamp)) {
                        latestSensors[sensor.sensor_type] = sensor;
                    }
                });

                // Display each sensor type
                Object.values(latestSensors).forEach(sensor => {
                    if (sensor.sensor_type !== 'location') { // Skip location sensors as they're shown on map
                        displayPreviousSensorValue(sensor);
                    }
                });

                if (Object.keys(latestSensors).length === 0 || (Object.keys(latestSensors).length === 1 && latestSensors['location'])) {
                    sensorContainer.innerHTML = '<div class="col-12"><p class="text-muted">No previous sensor data available</p></div>';
                }
            } else {
                document.getElementById('sensors-container').innerHTML = '<div class="col-12"><p class="text-muted">No previous sensor data available</p></div>';
            }
        }

        // Display previous sensor value
        function displayPreviousSensorValue(sensor) {
            const sensorContainer = document.getElementById('sensors-container');
            
            const sensorCard = document.createElement('div');
            sensorCard.id = `sensor-${sensor.sensor_type}`;
            sensorCard.className = 'sensor-card col-md-6 col-lg-3';
            
            let displayValue = sensor.value;
            if (typeof sensor.value === 'object') {
                displayValue = JSON.stringify(sensor.value);
            } else if (typeof sensor.value === 'number') {
                displayValue = sensor.value + (sensor.unit ? ` ${sensor.unit}` : '');
            }
            
            const lastUpdate = sensor.reading_timestamp ? 
                new Date(sensor.reading_timestamp).toLocaleString() : 
                'Unknown';
            
            sensorCard.innerHTML = `
                <div class="sensor-type">${sensor.sensor_type}</div>
                <div class="sensor-value">${displayValue}</div>
                <div class="last-update">Last reading: ${lastUpdate}</div>
            `;
            
            sensorContainer.appendChild(sensorCard);
        }


        // Update MQTT status
        function updateMqttStatus(status, message = '') {
            const statusElement = document.getElementById('mqtt-status');
            const connectBtn = document.getElementById('connect-btn');
            const pauseBtn = document.getElementById('pause-btn');

            statusElement.className = `mqtt-status ${status}`;

            switch (status) {
                case 'disconnected':
                    statusElement.textContent = 'Disconnected' + (message ? ': ' + message : '');
                    connectBtn.textContent = 'Connect';
                    connectBtn.disabled = false;
                    pauseBtn.style.display = 'none';
                    break;
                case 'connecting':
                    statusElement.textContent = 'Connecting...';
                    connectBtn.textContent = 'Connecting...';
                    connectBtn.disabled = true;
                    pauseBtn.style.display = 'none';
                    break;
                case 'connected':
                    statusElement.textContent = isPaused ? 'Connected (Paused)' : 'Connected to MQTT Broker';
                    connectBtn.textContent = 'Disconnect';
                    connectBtn.disabled = false;
                    pauseBtn.style.display = 'inline-block';
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
                console.log('Data processing paused');
            } else {
                pauseBtn.innerHTML = '<i class="ph-pause me-2"></i>Pause';
                pauseBtn.classList.remove('btn-success');
                pauseBtn.classList.add('btn-warning');
                console.log('Data processing resumed');
            }
            
            updateMqttStatus('connected');
        }

        // Connect to MQTT broker
        function connectMqtt() {
            if (isConnected) {
                // Disconnect
                if (mqttClient) {
                    mqttClient.end();
                }
                if (scanInterval) {
                    clearInterval(scanInterval);
                }
                isConnected = false;
                updateMqttStatus('disconnected');
                return;
            }

            updateMqttStatus('connecting');

            // Build MQTT broker URL using device protocol
            const protocol = device.protocol || 'ws'; // Default to 'ws' if not set
            
            // Determine the correct port based on protocol
            let port;
            switch (protocol) {
                case 'ws':
                case 'wss':
                    // Use websocket_port for WebSocket connections
                    port = mqttBroker.websocket_port || 8083; // Default WebSocket port
                    break;
                case 'mqtt':
                case 'mqtts':
                    // Use regular port for standard MQTT connections
                    port = mqttBroker.use_ssl && mqttBroker.ssl_port ? mqttBroker.ssl_port : mqttBroker.port;
                    break;
                default:
                    port = mqttBroker.port;
            }
            
            // Build the broker URL with path for WebSocket connections
            let brokerUrl;
            if (protocol === 'ws' || protocol === 'wss') {
                const path = mqttBroker.path || '/mqtt'; // Default path for WebSocket
                brokerUrl = `${protocol}://${mqttBroker.host}:${port}${path}`;
            } else {
                brokerUrl = `${protocol}://${mqttBroker.host}:${port}`;
            }
            
            console.log('Connecting to MQTT broker:', brokerUrl);

            // MQTT connection options
            const options = {
                clientId: `web_client_${Math.random().toString(16).substr(2, 8)}`,
                clean: true,
                connectTimeout: 4000,
                reconnectPeriod: 1000,
            };

            // Add authentication if provided
            if (mqttBroker.username) {
                options.username = mqttBroker.username;
                options.password = mqttBroker.password || '';
            }

            try {
                mqttClient = mqtt.connect(brokerUrl, options);

                mqttClient.on('connect', function() {
                    console.log('Connected to MQTT broker');
                    isConnected = true;
                    updateMqttStatus('connected');

                    // Subscribe to device topics
                    if (device.topics && device.topics.length > 0) {
                        device.topics.forEach(topic => {
                            mqttClient.subscribe(topic, function(err) {
                                if (err) {
                                    console.error('Failed to subscribe to topic:', topic, err);
                                } else {
                                    console.log('Subscribed to topic:', topic);
                                }
                            });
                        });
                    }

                    // Start scanning every 10 seconds
                    scanInterval = setInterval(function() {
                        console.log('Scanning for messages...');
                    }, 10000);
                });

                mqttClient.on('message', function(topic, message) {
                    console.log('Received message:', topic, message.toString());
                    handleMqttMessage(topic, message.toString());
                });

                mqttClient.on('error', function(error) {
                    console.error('MQTT connection error:', error);
                    updateMqttStatus('disconnected', error.message);
                    isConnected = false;
                });

                mqttClient.on('close', function() {
                    console.log('MQTT connection closed');
                    updateMqttStatus('disconnected');
                    isConnected = false;
                    if (scanInterval) {
                        clearInterval(scanInterval);
                    }
                });

            } catch (error) {
                console.error('Failed to connect to MQTT broker:', error);
                updateMqttStatus('disconnected', error.message);
            }
        }

        // Handle incoming MQTT messages
        function handleMqttMessage(topic, messageStr) {
            // Skip processing if paused
            if (isPaused) {
                console.log('Message received but processing is paused:', topic);
                return;
            }

            try {
                const message = JSON.parse(messageStr);

                if (message.type === 'location') {
                    handleLocationMessage(message);
                } else if (message.sensors) {
                    handleSensorsMessage(message);
                }

            } catch (error) {
                console.error('Failed to parse MQTT message:', error, messageStr);
            }
        }

        // Handle location messages
        function handleLocationMessage(locationData) {
            const {
                latitude,
                longitude,
                status
            } = locationData;

            if (latitude && longitude) {
                // Update device marker with new location and status
                addDeviceMarker(latitude, longitude, `Status: ${status || 'unknown'}`);
                
                // Update location status display
                updateLocationStatus(latitude, longitude);

                // Center map on new location
                map.setView([latitude, longitude], 13);

                // Store sensor data
                storeSensorData({
                    device_id: device.id,
                    sensor_type: 'location',
                    value: {
                        latitude,
                        longitude,
                        status
                    },
                    unit: null
                });

                console.log('Location updated:', latitude, longitude, status);
            }
        }

        // Point-in-polygon checking function
        function isPointInPolygon(point, polygon) {
            const x = point[0], y = point[1];
            let inside = false;
            
            for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
                const xi = polygon[i][0], yi = polygon[i][1];
                const xj = polygon[j][0], yj = polygon[j][1];
                
                if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
                    inside = !inside;
                }
            }
            
            return inside;
        }

        // Check if point is inside GeoJSON geometry
        function isPointInGeoJSON(lat, lng, geoJSON) {
            const point = [lng, lat]; // GeoJSON uses [longitude, latitude]
            
            if (geoJSON.type === 'Polygon') {
                // For Polygon, coordinates[0] is the exterior ring
                return isPointInPolygon(point, geoJSON.coordinates[0]);
            } else if (geoJSON.type === 'MultiPolygon') {
                // For MultiPolygon, check each polygon
                for (let polygon of geoJSON.coordinates) {
                    if (isPointInPolygon(point, polygon[0])) {
                        return true;
                    }
                }
                return false;
            } else if (geoJSON.type === 'Feature') {
                return isPointInGeoJSON(lat, lng, geoJSON.geometry);
            } else if (geoJSON.type === 'FeatureCollection') {
                for (let feature of geoJSON.features) {
                    if (isPointInGeoJSON(lat, lng, feature.geometry)) {
                        return true;
                    }
                }
                return false;
            }
            
            return false;
        }

        // Add device marker with appropriate styling
        function addDeviceMarker(lat, lng, popupText) {
            // Determine if device is inside land territory
            const isInside = checkLocationStatus(lat, lng);
            
            // Create custom icon based on location status
            let iconColor = '#6b7280'; // default gray
            let iconClass = 'ph-map-pin';
            
            if (isInside === true) {
                iconColor = '#16a34a'; // green for inside
                iconClass = 'ph-map-pin';
            } else if (isInside === false) {
                iconColor = '#dc2626'; // red for outside
                iconClass = 'ph-warning';
            }
            
            // Create custom HTML marker
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

        // Check location status and return boolean
        function checkLocationStatus(lat, lng) {
            if (!landData || !landData.geojson) {
                return null; // unknown
            }
            
            try {
                const landGeoJSON = typeof landData.geojson === 'string' ? JSON.parse(landData.geojson) : landData.geojson;
                return isPointInGeoJSON(lat, lng, landGeoJSON);
            } catch (error) {
                console.error('Error checking location status:', error);
                return null;
            }
        }

        // Update location status display
        function updateLocationStatus(lat, lng) {
            const isInside = checkLocationStatus(lat, lng);
            
            // Find or create location status element
            let statusElement = document.getElementById('location-status');
            if (!statusElement) {
                statusElement = document.createElement('div');
                statusElement.id = 'location-status';
                
                // Insert before the map
                const mapContainer = document.getElementById('map');
                mapContainer.parentNode.insertBefore(statusElement, mapContainer);
            }
            
            // Update status display
            statusElement.className = 'location-status';
            
            if (isInside === true) {
                statusElement.classList.add('inside');
                statusElement.innerHTML = '<i class="ph-check-circle me-2"></i>Device is inside land territory';
            } else if (isInside === false) {
                statusElement.classList.add('outside');
                statusElement.innerHTML = '<i class="ph-warning-circle me-2"></i>Warning: Device is outside land territory!';
            } else {
                statusElement.classList.add('unknown');
                statusElement.innerHTML = '<i class="ph-question me-2"></i>Location status unknown';
            }
        }

        // Handle sensor messages
        function handleSensorsMessage(sensorsData) {
            const {
                sensors
            } = sensorsData;

            if (Array.isArray(sensors)) {
                sensors.forEach(sensor => {
                    const {
                        type,
                        value
                    } = sensor;

                    // Update sensor display
                    updateSensorDisplay(type, value);

                    // Extract numeric value and unit
                    const valueMatch = value.match(/^([\d.]+)\s*(.*)$/);
                    const numericValue = valueMatch ? parseFloat(valueMatch[1]) : null;
                    const unit = valueMatch ? valueMatch[2].trim() : value;

                    // Store sensor data
                    storeSensorData({
                        device_id: device.id,
                        sensor_type: type,
                        value: numericValue || value,
                        unit: unit
                    });
                });
            }
        }

        // Update sensor display
        function updateSensorDisplay(type, value) {
            const sensorContainer = document.getElementById('sensors-container');
            let sensorCard = document.getElementById(`sensor-${type}`);

            if (!sensorCard) {
                // Create new sensor card
                sensorCard = document.createElement('div');
                sensorCard.id = `sensor-${type}`;
                sensorCard.className = 'sensor-card col-md-6 col-lg-3';
                sensorContainer.appendChild(sensorCard);
            }

            const now = new Date().toLocaleTimeString();
            sensorCard.innerHTML = `
        <div class="sensor-type">${type}</div>
        <div class="sensor-value">${value}</div>
        <div class="last-update">Updated: ${now}</div>
    `;
        }

        // Store sensor data via AJAX
        function storeSensorData(sensorData) {
            fetch('/app/sensors/store', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(sensorData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Sensor data stored successfully');
                    } else {
                        console.error('Failed to store sensor data:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error storing sensor data:', error);
                });
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            loadPreviousSensorData();
            updateMqttStatus('disconnected');

            // Connect button event
            document.getElementById('connect-btn').addEventListener('click', connectMqtt);
            
            // Pause button event
            document.getElementById('pause-btn').addEventListener('click', togglePause);
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (mqttClient && isConnected) {
                mqttClient.end();
            }
            if (scanInterval) {
                clearInterval(scanInterval);
            }
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
