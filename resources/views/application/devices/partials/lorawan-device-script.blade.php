<script>
    // Global variables
    let map;
    let deviceMarker;
    let landGeoJSONLayer = null;
    let lorawanPollingInterval;
    let isPolling = false;

    // Device data
    const device = @json($device);
    const mqttBroker = @json($device->mqttBroker);
    const landData = @json($device->land);
    const previousSensors = @json($device->sensors);

    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Store last sensor data to avoid unnecessary updates
    let lastSensorData = {};
    let debugMode = false; // Set to true for detailed logging
    
    // DEBUG: Log initial data (only in debug mode)
    if (debugMode) {
        console.log('🔧 LoRaWAN Device Data:', device);
        console.log('🔧 LoRaWAN Network Server Config:', mqttBroker);
        console.log('🔧 Land Data:', landData);
        console.log('🔧 Previous Sensors:', previousSensors);
    }

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

    // Start LoRaWAN data polling - SIMPLIFIED to use existing sensor data API
    function startLorawanPolling() {
        if (isPolling) {
            console.log('⚠️ LoRaWAN polling already active');
            return;
        }

        console.log('🚀 Starting LoRaWAN sensor data polling...');
        isPolling = true;

        // Poll data immediately
        pollLorawanData();

        // Set up polling interval (every 30 seconds to reduce server load)
        lorawanPollingInterval = setInterval(function() {
            pollLorawanData();
        }, 30000);

        console.log('✅ LoRaWAN polling started (30 second intervals)');
    }

    // Stop LoRaWAN data polling
    function stopLorawanPolling() {
        if (!isPolling) {
            console.log('⚠️ LoRaWAN polling not active');
            return;
        }

        console.log('🛑 Stopping LoRaWAN data polling...');
        isPolling = false;

        if (lorawanPollingInterval) {
            clearInterval(lorawanPollingInterval);
            lorawanPollingInterval = null;
        }

        console.log('✅ LoRaWAN polling stopped');
    }

    // Poll LoRaWAN sensor data - SIMPLIFIED to use existing getSensorData API
    async function pollLorawanData() {
        if (debugMode) console.log('🔍 Polling LoRaWAN sensor data...');

        try {
            // Use the existing getSensorData API endpoint
            const response = await fetch(`/api/devices/${device.id}/sensors`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            if (debugMode) console.log('📡 LoRaWAN sensor data received:', data);
            
            if (data.success) {
                if (debugMode) console.log('✅ LoRaWAN sensor data updated successfully');
                
                // Check if data has actually changed
                const dataHash = JSON.stringify(data.sensors);
                if (lastSensorData.hash === dataHash) {
                    if (debugMode) console.log('📊 No sensor data changes detected, skipping update');
                    return;
                }
                lastSensorData.hash = dataHash;
                
                // Update device status
                if (data.device_status) {
                    updateDeviceStatus(data.device_status);
                }

                // Process sensor data
                if (data.sensors && data.sensors.length > 0) {
                    if (debugMode) console.log('📊 Processing', data.sensors.length, 'sensor readings');
                    data.sensors.forEach(sensor => {
                        updateSensorTable(sensor);
                    });
                    updateAlerts();
                }

                // Update last seen timestamp
                if (data.last_seen) {
                    updateLastSeen(data.last_seen);
                }
            } else {
                console.warn('⚠️ LoRaWAN sensor data polling failed:', data.message);
            }

        } catch (error) {
            console.error('❌ Error polling LoRaWAN sensor data:', error);
        }
    }

    // Update device status display
    function updateDeviceStatus(status) {
        console.log('📊 Updating device status to:', status);
        
        // Update any status indicators on the page
        const statusElements = document.querySelectorAll('.device-status');
        statusElements.forEach(element => {
            element.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            element.className = `badge bg-${status === 'online' ? 'success' : (status === 'offline' ? 'secondary' : (status === 'maintenance' ? 'warning' : 'danger'))}`;
        });
    }

    // Handle location data from LoRaWAN - PROCESSES BACKEND LOCATION RESPONSE
    function handleLocationData(locationData) {
        console.log('📍 Processing LoRaWAN location data:', locationData);
        
        const { latitude, longitude, source } = locationData; // UPDATED TO MATCH BACKEND RESPONSE

        if (latitude && longitude) {
            console.log('✅ Valid coordinates received:', { latitude, longitude, source });
            
            // Update device marker with new location and source
            addDeviceMarker(latitude, longitude, `Source: ${source || 'unknown'}`);
            
            // Update location status display
            updateLocationStatus(latitude, longitude);

            // Center map on new location
            if (map) {
                map.setView([latitude, longitude], 13);
                console.log('🗺️ Map centered on new location');
            }
        } else {
            console.warn('⚠️ Invalid location data received - missing coordinates:', locationData);
        }
    }

    // Update sensor table row with new data - PROCESSES BACKEND SENSOR DATA
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
                // Add visual indication of update
                valueCell.classList.add('sensor-updated');
                setTimeout(() => valueCell.classList.remove('sensor-updated'), 2000);
            }

            if (statusCell) {
                const indicator = statusCell.querySelector('.sensor-status-indicator');
                const statusText = statusCell.querySelector('.status-text');
                if (indicator) {
                    indicator.className = 'sensor-status-indicator sensor-status-online';
                }
                if (statusText) {
                    statusText.textContent = 'Online';
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
    }

    // Add new sensor row to table - CREATES NEW ROWS FOR BACKEND SENSOR DATA
    function addSensorTableRow(sensor) {
        const tableBody = document.getElementById('sensors-table-body');
        if (!tableBody) return;

        const row = document.createElement('tr');
        row.className = 'sensor-row';
        row.setAttribute('data-sensor-type', sensor.sensor_type);
        
        // Get appropriate icon for sensor type
        const sensorIcon = getSensorIcon(sensor.sensor_type);
        
        const alertBadgeClass = sensor.alert_status === 'normal' ? 'normal' : 
                            sensor.alert_status === 'high' ? 'high' : 'low';
        const alertBadgeText = sensor.alert_status === 'normal' ? 'Normal' : 
                            sensor.alert_status === 'high' ? 'High Alert' : 'Low Alert';

        row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    <i class="${sensorIcon} me-2 text-primary"></i>
                    <div>
                        <div class="fw-medium">${sensor.sensor_type}</div>
                        <small class="text-muted">${sensor.sensor_name || 'Auto-created sensor'}</small>
                    </div>
                </div>
            </td>
            <td class="sensor-value fw-semibold">${sensor.formatted_value}</td>
            <td class="sensor-status">
                <span class="sensor-status-indicator sensor-status-online"></span>
                <span class="status-text">Online</span>
            </td>
            <td class="sensor-alert">
                <span class="alert-badge ${alertBadgeClass}">${alertBadgeText}</span>
            </td>
            <td class="sensor-last-update last-update">Just now</td>
        `;

        tableBody.appendChild(row);

        // Hide "no sensors" row if it exists
        const noDataRow = document.getElementById('no-sensors-row');
        if (noDataRow) {
            noDataRow.style.display = 'none';
        }
    }

    // Get appropriate icon for sensor type - HELPER FUNCTION
    function getSensorIcon(sensorType) {
        const iconMap = {
            'temperature': 'ph-thermometer',
            'humidity': 'ph-drop',
            'battery': 'ph-battery-charging',
            'rssi': 'ph-wifi-medium',
            'snr': 'ph-graph',
            'location': 'ph-map-pin',
            'altitude': 'ph-mountains',
            'frequency': 'ph-wave-sine',
            'spreading_factor': 'ph-broadcast',
            'bandwidth': 'ph-charts'
        };
        return iconMap[sensorType] || 'ph-gauge';
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

    // Update last seen timestamp
    function updateLastSeen(timestamp) {
        const lastSeenElements = document.querySelectorAll('.last-seen-timestamp');
        lastSeenElements.forEach(element => {
            const date = new Date(timestamp);
            element.textContent = date.toLocaleString();
        });
    }

    // Helper functions for map and location (UNCHANGED)
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
        const latSensor = previousSensors.find(s => s.sensor_type === 'latitude');
        const lngSensor = previousSensors.find(s => s.sensor_type === 'longitude');
        
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
                
                // Also update map marker and location status
                addDeviceMarker(lat, lng, 'Location from stored sensors');
                updateLocationStatus(lat, lng);
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
        console.log('🚀 LoRaWAN page loaded, initializing application...');
        
        initMap();
        
        // Update geofence status and show stored sensor data after map is initialized
        setTimeout(() => {
            updateGeofenceStatusInTable();
        }, 1000);
        
        // Start LoRaWAN polling automatically
        startLorawanPolling();

        console.log('✅ LoRaWAN application initialized successfully');
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        console.log('🧹 Cleaning up LoRaWAN polling on page unload...');
        stopLorawanPolling();
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

    // Export functions for manual control
    window.startLorawanPolling = startLorawanPolling;
    window.stopLorawanPolling = stopLorawanPolling;
</script>

<!-- Add this CSS for visual feedback -->
<style>
.sensor-updated {
    background-color: #e8f5e8 !important;
    transition: background-color 0.5s ease;
}
</style>
