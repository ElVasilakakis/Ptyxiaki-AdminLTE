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

                    // Check for latitude and longitude from sensor data
                    const latSensor = previousSensors.find(s => s.sensor_type.toLowerCase().includes('latitude') || s.sensor_type.toLowerCase().includes('lat'));
                    const lngSensor = previousSensors.find(s => s.sensor_type.toLowerCase().includes('longitude') || s.sensor_type.toLowerCase().includes('lng') || s.sensor_type.toLowerCase().includes('lon'));
                    
                    if (latSensor && lngSensor && latSensor.value !== null && lngSensor.value !== null) {
                        const lat = parseFloat(latSensor.value);
                        const lng = parseFloat(lngSensor.value);
                        if (!isNaN(lat) && !isNaN(lng)) {
                            defaultLat = lat;
                            defaultLng = lng;
                            defaultZoom = 13;
                            console.log('📍 Using sensor location data:', defaultLat, defaultLng);
                        }
                    } else if (device.current_location && device.current_location.coordinates) {
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

                    // Add device marker based on sensor data first, then fallback to device location
                    if (latSensor && lngSensor && latSensor.value !== null && lngSensor.value !== null) {
                        const lat = parseFloat(latSensor.value);
                        const lng = parseFloat(lngSensor.value);
                        if (!isNaN(lat) && !isNaN(lng)) {
                            addDeviceMarker(lat, lng, 'Location from sensors');
                            updateLocationStatus(lat, lng);
                        }
                    } else if (device.current_location && device.current_location.coordinates) {
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
                const testBtn = document.getElementById('test-btn');

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
                        if (testBtn) testBtn.style.display = 'none';
                        break;
                    case 'connecting':
                        statusElement.textContent = 'Connecting...';
                        connectBtn.textContent = 'Connecting...';
                        connectBtn.disabled = true;
                        if (pauseBtn) pauseBtn.style.display = 'none';
                        if (testBtn) testBtn.style.display = 'none';
                        break;
                    case 'connected':
                        statusElement.textContent = isPaused ? 'Connected (Paused)' : 'Connected to MQTT Broker';
                        connectBtn.textContent = 'Disconnect';
                        connectBtn.disabled = false;
                        if (pauseBtn) pauseBtn.style.display = 'inline-block';
                        if (testBtn) testBtn.style.display = 'inline-block';
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

            // Send test data function
            function sendTestData() {
                if (!mqttClient || !isConnected) {
                    console.warn('⚠️ MQTT client not connected');
                    return;
                }

                console.log('🧪 Sending test data...');

                // Test sensor data
                const testSensorsMessage = {
                    sensors: [
                        { type: "thermal", value: `${(Math.random() * 15 + 20).toFixed(1)} celsius` },
                        { type: "humidity", value: `${(Math.random() * 40 + 40).toFixed(1)} percent` },
                        { type: "light", value: `${(Math.random() * 50 + 25).toFixed(1)} percent` },
                        { type: "potentiometer", value: `${(Math.random() * 100).toFixed(1)} percent` }
                    ]
                };

                // Test location data  
                const testLocationMessage = {
                    type: "location",
                    latitude: 37.7749 + (Math.random() - 0.5) * 0.01,
                    longitude: -122.4194 + (Math.random() - 0.5) * 0.01,
                    status: Math.random() > 0.5 ? "inside_geofence" : "outside_geofence"
                };

                // Publish test messages
                mqttClient.publish('ESP32-DEV-001/sensors', JSON.stringify(testSensorsMessage));
                mqttClient.publish('ESP32-DEV-001/geosensors', JSON.stringify(testLocationMessage));
                
                console.log('✅ Test messages published');
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
                        console.log('✅ Sensor data stored successfully, updating table...');
                        updateSensorTable(data.sensor);
                        updateAlerts();
                    } else {
                        console.error('❌ Backend reported failure:', data.message);
                    }
                })
                .catch(error => {
                    console.error('❌ Error storing sensor data:', error);
                });
            }

            // Update sensor table row with new data
            function updateSensorTable(sensor) {
                console.log('📋 Updating sensor table for:', sensor.sensor_type);
                
                const row = document.querySelector(`tr[data-sensor-type="${sensor.sensor_type}"]`);
                
                if (row) {
                    // Update existing row
                    const valueCell = row.querySelector('.sensor-value');
                    const statusCell = row.querySelector('.sensor-status');
                    const alertCell = row.querySelector('.sensor-alert');
                    const updateCell = row.querySelector('.sensor-last-update');

                    if (valueCell) {
                        valueCell.textContent = sensor.formatted_value;
                    }

                    if (statusCell) {
                        const indicator = statusCell.querySelector('.sensor-status-indicator');
                        if (indicator) {
                            indicator.className = 'sensor-status-indicator sensor-status-online';
                        }
                    }

                    if (alertCell) {
                        const badge = alertCell.querySelector('.alert-badge');
                        if (badge) {
                            badge.className = `alert-badge ${sensor.alert_status}`;
                            badge.textContent = sensor.alert_status === 'normal' ? 'Normal' : 
                                            sensor.alert_status === 'high' ? 'High Alert' : 'Low Alert';
                        }
                    }

                    if (updateCell) {
                        updateCell.textContent = 'Just now';
                    }

                    console.log('✅ Updated existing table row for:', sensor.sensor_type);
                } else {
                    // Add new row if sensor doesn't exist
                    addSensorTableRow(sensor);
                    console.log('➕ Added new table row for:', sensor.sensor_type);
                }

                // Check if this is a location sensor and update map
                checkAndUpdateLocationFromSensors(sensor);
            }

            // Add new sensor row to table
            function addSensorTableRow(sensor) {
                const tableBody = document.getElementById('sensors-table-body');
                if (!tableBody) return;

                const row = document.createElement('tr');
                row.className = 'sensor-row';
                row.setAttribute('data-sensor-type', sensor.sensor_type);
                
                const alertBadgeClass = sensor.alert_status === 'normal' ? 'normal' : 
                                    sensor.alert_status === 'high' ? 'high' : 'low';
                const alertBadgeText = sensor.alert_status === 'normal' ? 'Normal' : 
                                    sensor.alert_status === 'high' ? 'High Alert' : 'Low Alert';

                row.innerHTML = `
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="ph-thermometer me-2 text-primary"></i>
                            <div>
                                <div class="fw-medium">${sensor.sensor_type}</div>
                                <small class="text-muted">${sensor.sensor_name || 'Auto-created sensor'}</small>
                            </div>
                        </div>
                    </td>
                    <td class="sensor-value fw-semibold">${sensor.formatted_value}</td>
                    <td class="sensor-status">
                        <span class="sensor-status-indicator sensor-status-online"></span>
                        Online
                    </td>
                    <td class="sensor-alert">
                        <span class="alert-badge ${alertBadgeClass}">${alertBadgeText}</span>
                    </td>
                    <td class="sensor-last-update last-update">Just now</td>
                `;

                tableBody.appendChild(row);

                // Show table if it was hidden
                const noDataRow = document.getElementById('no-sensors-row');
                if (noDataRow) {
                    noDataRow.style.display = 'none';
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
                // Check if point is within GeoJSON boundaries using proper point-in-polygon detection
                if (landGeoJSONLayer) {
                    const point = L.latLng(lat, lng);
                    let isInside = false;
                    
                    landGeoJSONLayer.eachLayer(function(layer) {
                        // Use Leaflet's built-in point-in-polygon detection
                        if (layer.feature && layer.feature.geometry) {
                            const geometry = layer.feature.geometry;
                            
                            // Handle different geometry types
                            if (geometry.type === 'Polygon') {
                                isInside = isPointInPolygon([lng, lat], geometry.coordinates[0]);
                            } else if (geometry.type === 'MultiPolygon') {
                                for (let polygon of geometry.coordinates) {
                                    if (isPointInPolygon([lng, lat], polygon[0])) {
                                        isInside = true;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Fallback to bounding box check if geometry is not available
                        if (!isInside && layer.getBounds && layer.getBounds().contains(point)) {
                            isInside = true;
                        }
                    });
                    
                    return isInside;
                }
                return null;
            }

            // Point-in-polygon algorithm (ray casting)
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

            // Check if updated sensor is a location sensor and update map
            function checkAndUpdateLocationFromSensors(updatedSensor) {
                console.log('🔍 Checking if sensor update affects location:', updatedSensor.sensor_type);
                
                // Check if this is a latitude or longitude sensor
                const isLatSensor = updatedSensor.sensor_type.toLowerCase().includes('latitude') || updatedSensor.sensor_type.toLowerCase().includes('lat');
                const isLngSensor = updatedSensor.sensor_type.toLowerCase().includes('longitude') || updatedSensor.sensor_type.toLowerCase().includes('lng') || updatedSensor.sensor_type.toLowerCase().includes('lon');
                
                if (isLatSensor || isLngSensor) {
                    console.log('📍 Location sensor updated, checking for complete coordinates...');
                    
                    // Get current latitude and longitude from all sensor rows
                    let currentLat = null;
                    let currentLng = null;
                    
                    // Check all sensor rows for latitude and longitude
                    const sensorRows = document.querySelectorAll('tr[data-sensor-type]');
                    sensorRows.forEach(row => {
                        const sensorType = row.getAttribute('data-sensor-type').toLowerCase();
                        const valueCell = row.querySelector('.sensor-value');
                        
                        if (valueCell) {
                            const valueText = valueCell.textContent.trim();
                            // Extract numeric value (remove unit if present)
                            const numericValue = parseFloat(valueText.split(' ')[0]);
                            
                            if (!isNaN(numericValue)) {
                                if (sensorType.includes('latitude') || sensorType.includes('lat')) {
                                    currentLat = numericValue;
                                    console.log('📍 Found latitude sensor:', sensorType, '=', currentLat);
                                } else if (sensorType.includes('longitude') || sensorType.includes('lng') || sensorType.includes('lon')) {
                                    currentLng = numericValue;
                                    console.log('📍 Found longitude sensor:', sensorType, '=', currentLng);
                                }
                            }
                        }
                    });
                    
                    // If we have both coordinates, update the map
                    if (currentLat !== null && currentLng !== null) {
                        console.log('✅ Complete coordinates found, updating map:', { lat: currentLat, lng: currentLng });
                        
                        // Update device marker
                        addDeviceMarker(currentLat, currentLng, 'Location from sensors');
                        
                        // Update location status
                        updateLocationStatus(currentLat, currentLng);
                        
                        // Optionally center map on new location (with smooth animation)
                        if (map) {
                            map.setView([currentLat, currentLng], map.getZoom(), { animate: true, duration: 1 });
                            console.log('🗺️ Map updated with new sensor location');
                        }
                    } else {
                        console.log('⚠️ Incomplete coordinates - lat:', currentLat, 'lng:', currentLng);
                    }
                } else {
                    console.log('📊 Non-location sensor updated:', updatedSensor.sensor_type);
                }
            }

            // Locate device function - centers map on device location
            function locateDevice() {
                console.log('🎯 Locate device button clicked');
                
                const locateBtn = document.getElementById('locate-device-btn');
                if (locateBtn) {
                    // Add loading state
                    const originalContent = locateBtn.innerHTML;
                    locateBtn.innerHTML = '<i class="ph-spinner ph-spin me-1"></i>Locating...';
                    locateBtn.disabled = true;
                }
                
                // Try to get coordinates from sensors first
                const latSensor = previousSensors.find(s => s.sensor_type.toLowerCase().includes('latitude') || s.sensor_type.toLowerCase().includes('lat'));
                const lngSensor = previousSensors.find(s => s.sensor_type.toLowerCase().includes('longitude') || s.sensor_type.toLowerCase().includes('lng') || s.sensor_type.toLowerCase().includes('lon'));
                
                let deviceLat = null;
                let deviceLng = null;
                let locationSource = 'unknown';
                
                // Check sensor data first
                if (latSensor && lngSensor && latSensor.value !== null && lngSensor.value !== null) {
                    deviceLat = parseFloat(latSensor.value);
                    deviceLng = parseFloat(lngSensor.value);
                    locationSource = 'sensors';
                    console.log('📍 Using sensor coordinates:', deviceLat, deviceLng);
                } 
                // Fallback to device current_location
                else if (device.current_location && device.current_location.coordinates) {
                    deviceLat = device.current_location.coordinates[1];
                    deviceLng = device.current_location.coordinates[0];
                    locationSource = 'current_location';
                    console.log('📍 Using current location:', deviceLat, deviceLng);
                } 
                // Fallback to device location
                else if (device.location && device.location.coordinates) {
                    deviceLat = device.location.coordinates[1];
                    deviceLng = device.location.coordinates[0];
                    locationSource = 'device_location';
                    console.log('📍 Using device location:', deviceLat, deviceLng);
                }
                
                if (deviceLat !== null && deviceLng !== null && !isNaN(deviceLat) && !isNaN(deviceLng)) {
                    console.log('✅ Valid coordinates found, centering map');
                    
                    // Center map on device location with smooth animation
                    if (map) {
                        map.setView([deviceLat, deviceLng], 15, {
                            animate: true,
                            duration: 1.5
                        });
                        
                        // Update or add device marker
                        addDeviceMarker(deviceLat, deviceLng, `Location from ${locationSource}`);
                        
                        // Flash the marker for visual feedback
                        if (deviceMarker) {
                            setTimeout(() => {
                                deviceMarker.openPopup();
                            }, 1000);
                        }
                        
                        console.log('🗺️ Map centered on device location');
                    }
                    
                    // Reset button after successful location
                    setTimeout(() => {
                        if (locateBtn) {
                            locateBtn.innerHTML = '<i class="ph-crosshairs me-1"></i>Locate Device';
                            locateBtn.disabled = false;
                        }
                    }, 1500);
                } else {
                    console.warn('⚠️ No valid device coordinates found');
                    
                    // Show error state
                    if (locateBtn) {
                        locateBtn.innerHTML = '<i class="ph-warning me-1"></i>No Location';
                        locateBtn.classList.add('btn-outline-warning');
                        locateBtn.classList.remove('btn-outline-primary');
                        
                        // Reset button after error
                        setTimeout(() => {
                            locateBtn.innerHTML = '<i class="ph-crosshairs me-1"></i>Locate Device';
                            locateBtn.classList.remove('btn-outline-warning');
                            locateBtn.classList.add('btn-outline-primary');
                            locateBtn.disabled = false;
                        }, 3000);
                    }
                }
            }

            // Update geofence status for location sensors in the table
            function updateGeofenceStatusInTable() {
                const latSensor = previousSensors.find(s => s.sensor_type === 'latitude');
                const lngSensor = previousSensors.find(s => s.sensor_type === 'longitude');
                
                if (latSensor && lngSensor && latSensor.value !== null && lngSensor.value !== null) {
                    const lat = parseFloat(latSensor.value);
                    const lng = parseFloat(lngSensor.value);
                    
                    if (!isNaN(lat) && !isNaN(lng)) {
                        const isInside = checkLocationStatus(lat, lng);
                        
                        // Update latitude geofence status
                        const latGeofenceElement = document.getElementById('geofence-status-latitude');
                        if (latGeofenceElement) {
                            updateGeofenceElement(latGeofenceElement, isInside);
                        }
                        
                        // Update longitude geofence status
                        const lngGeofenceElement = document.getElementById('geofence-status-longitude');
                        if (lngGeofenceElement) {
                            updateGeofenceElement(lngGeofenceElement, isInside);
                        }
                    }
                }
            }
            
            function updateGeofenceElement(element, isInside) {
                if (isInside === true) {
                    element.innerHTML = `
                        <span class="badge bg-success bg-opacity-10 text-success">
                            <i class="ph-check-circle me-1"></i>
                            Inside Land
                        </span>
                    `;
                } else if (isInside === false) {
                    element.innerHTML = `
                        <span class="badge bg-danger bg-opacity-10 text-danger">
                            <i class="ph-warning-circle me-1"></i>
                            Outside Land
                        </span>
                    `;
                } else {
                    element.innerHTML = `
                        <span class="badge bg-secondary bg-opacity-10 text-secondary">
                            <i class="ph-map-trifold me-1"></i>
                            Unknown
                        </span>
                    `;
                }
            }

            // Initialize everything when page loads
            document.addEventListener('DOMContentLoaded', function() {
                console.log('🚀 Page loaded, initializing application...');
                
                initMap();
                updateMqttStatus('disconnected');
                
                // Update geofence status after map is initialized
                setTimeout(() => {
                    updateGeofenceStatusInTable();
                }, 1000);

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

                const testBtn = document.getElementById('test-btn');
                if (testBtn) {
                    testBtn.addEventListener('click', sendTestData);
                    console.log('✅ Test button event listener added');
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
