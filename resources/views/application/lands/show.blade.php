@extends('layouts.application.app')

@section('pageheader')
    <div class="page-header-content d-lg-flex">
        <div class="d-flex">
            <h4 class="page-title mb-0">
                Land: {{ $land->land_name }} - <span class="fw-normal">Overview & Device Monitoring</span>
            </h4>

            <a href="#page_header"
                class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
                data-bs-toggle="collapse">
                <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
            </a>
        </div>

        <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
            <div class="hstack gap-3 mb-3 mb-lg-0">
                <a href="{{ route('app.lands.edit', $land) }}" class="btn btn-outline-primary">
                    <i class="ph-pencil me-2"></i>Edit Land
                </a>
                <a href="{{ route('app.lands.index') }}" class="btn btn-outline-secondary">
                    <i class="ph-arrow-left me-2"></i>Back to Lands
                </a>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="content">
        <!-- Land Overview Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-map-pin-area text-primary" style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Land Status</h6>
                                <span class="badge bg-{{ $land->enabled ? 'success' : 'secondary' }}">
                                    {{ $land->enabled ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-devices text-info" style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Total Devices</h6>
                                <h4 class="mb-0" id="total-devices">{{ $land->devices->count() }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-wifi-high text-success" style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Online Devices</h6>
                                <h4 class="mb-0" id="online-devices">{{ $land->devices->where('status', 'online')->count() }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-warning-circle text-warning" style="font-size: 2rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Alerts</h6>
                                <h4 class="mb-0" id="alert-count">
                                    @php
                                        $alertCount = 0;
                                        foreach($land->devices as $device) {
                                            foreach($device->sensors as $sensor) {
                                                if($sensor->getAlertStatus() !== 'normal') {
                                                    $alertCount++;
                                                }
                                            }
                                        }
                                    @endphp
                                    {{ $alertCount }}
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interactive Map -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="ph-map-trifold me-2"></i>Land & Device Map
                        </h6>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="locateLandOnMap()" title="Center map on land boundary">
                                <i class="ph-crosshairs me-1"></i>Locate Land
                            </button>
                            <div class="live-indicator">
                                <span class="badge bg-success">
                                    <i class="ph-broadcast me-1"></i>Live Updates
                                </span>
                            </div>
                            <small class="text-muted">Auto-refreshes every 10 seconds</small>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="land-map" style="height: 600px; width: 100%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map Legend -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="ph-info me-2"></i>Map Legend
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="legend-item" style="width: 20px; height: 20px; background-color: {{ $land->color ?? '#3388ff' }}; border-radius: 3px; opacity: 0.3;"></div>
                                    <span class="ms-2">Land Boundary</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="legend-item" style="width: 20px; height: 20px; background-color: #16a34a; border-radius: 50%;"></div>
                                    <span class="ms-2">Online Device</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="legend-item" style="width: 20px; height: 20px; background-color: #6b7280; border-radius: 50%;"></div>
                                    <span class="ms-2">Offline Device</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="legend-item" style="width: 20px; height: 20px; background-color: #dc2626; border-radius: 50%;"></div>
                                    <span class="ms-2">Device with Alerts</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="legend-item" style="width: 20px; height: 20px; background-color: #ef4444; border-radius: 50%; position: relative;">
                                        <div style="position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; background-color: #fbbf24; border-radius: 50%; animation: pulse 1s infinite;"></div>
                                    </div>
                                    <span class="ms-2">Outside Geofence</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Devices List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="ph-devices me-2"></i>Devices in {{ $land->land_name }}
                        </h6>
                        <span class="badge bg-info">{{ $land->devices->count() }} devices</span>
                    </div>
                    <div class="card-body">
                        @if($land->devices->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Device</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Location</th>
                                            <th>Sensors</th>
                                            <th>Alerts</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="devices-table-body">
                                        @foreach($land->devices as $device)
                                            <tr data-device-id="{{ $device->id }}">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="ph-device-mobile me-2 text-primary"></i>
                                                        <div>
                                                            <div class="fw-medium">{{ $device->name }}</div>
                                                            <small class="text-muted">{{ $device->device_id }}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">{{ ucfirst($device->device_type) }}</span>
                                                </td>
                                                <td class="device-status">
                                                    <span class="badge bg-{{ $device->status === 'online' ? 'success' : 'secondary' }}">
                                                        {{ ucfirst($device->status) }}
                                                    </span>
                                                </td>
                                                <td class="device-location">
                                                    @php
                                                        $latSensor = $device->sensors->where('sensor_type', 'latitude')->first();
                                                        $lngSensor = $device->sensors->where('sensor_type', 'longitude')->first();
                                                    @endphp
                                                    @if($latSensor && $lngSensor)
                                                        <small class="text-success">
                                                            <i class="ph-map-pin me-1"></i>
                                                            {{ number_format($latSensor->value, 6) }}, {{ number_format($lngSensor->value, 6) }}
                                                        </small>
                                                    @else
                                                        <small class="text-muted">
                                                            <i class="ph-map-pin me-1"></i>No GPS data
                                                        </small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary">{{ $device->sensors->count() }} sensors</span>
                                                </td>
                                                <td class="device-alerts">
                                                    @php
                                                        $deviceAlerts = $device->sensors->filter(function($sensor) {
                                                            return $sensor->alert_enabled && $sensor->getAlertStatus() !== 'normal';
                                                        });
                                                    @endphp
                                                    @if($deviceAlerts->count() > 0)
                                                        <span class="badge bg-warning">{{ $deviceAlerts->count() }} alerts</span>
                                                    @else
                                                        <span class="badge bg-success">Normal</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="locateDeviceOnMap({{ $device->id }})" title="Locate device on map">
                                                            <i class="ph-crosshairs"></i>Locate
                                                        </button>
                                                        <a href="{{ route('app.devices.show', $device) }}" class="btn btn-sm btn-outline-primary">
                                                            <i class="ph-eye me-1"></i>View
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="ph-devices text-muted" style="font-size: 3rem;"></i>
                                <h6 class="mt-3 text-muted">No devices found</h6>
                                <p class="text-muted">Add devices to this land to start monitoring.</p>
                                <a href="{{ route('app.devices.create') }}" class="btn btn-primary">
                                    <i class="ph-plus me-2"></i>Add Device
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        .live-indicator .badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .device-updated {
            background-color: #e8f5e8 !important;
            transition: background-color 0.5s ease;
        }
        
        .legend-item {
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
    </style>

    <script>
        // Global variables
        let map;
        let landLayer;
        let deviceMarkers = {};
        let updateInterval;
        
        // Land and device data
        const landData = @json($land);
        const devicesData = @json($land->devices);
        
        console.log('🗺️ Land Data:', landData);
        console.log('📱 Devices Data:', devicesData);
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeMap();
            startLiveUpdates();
        });
        
        // Initialize the map
        function initializeMap() {
            console.log('🚀 Initializing land map...');
            
            // Calculate center from land location or use default
            let centerLat = 39.0742;
            let centerLng = 21.8243;
            let zoom = 10;
            
            if (landData.location && landData.location.coordinates) {
                centerLng = landData.location.coordinates[0];
                centerLat = landData.location.coordinates[1];
                zoom = 13;
            }
            
            // Create map
            map = L.map('land-map').setView([centerLat, centerLng], zoom);
            
            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add land polygon
            addLandPolygon();
            
            // Add device markers
            addDeviceMarkers();
            
            // Fit map to show all content
            fitMapToContent();
            
            console.log('✅ Map initialized successfully');
        }
        
        // Add land polygon to map
        function addLandPolygon() {
            if (landData.geojson) {
                console.log('🗺️ Adding land polygon...');
                
                const landColor = landData.color || '#3388ff';
                
                landLayer = L.geoJSON(landData.geojson, {
                    style: {
                        color: landColor,
                        weight: 3,
                        opacity: 0.8,
                        fillColor: landColor,
                        fillOpacity: 0.2
                    }
                }).addTo(map);
                
                landLayer.bindPopup(`
                    <div class="p-2">
                        <h6 class="mb-1">${landData.land_name}</h6>
                        <small class="text-muted">Land Boundary</small>
                        ${landData.data ? '<br><small>Area: ' + (landData.data.area || 'Unknown') + '</small>' : ''}
                    </div>
                `);
                
                console.log('✅ Land polygon added');
            }
        }
        
        // Add device markers to map
        function addDeviceMarkers() {
            console.log('📍 Adding device markers...');
            
            devicesData.forEach(device => {
                addDeviceMarker(device);
            });
            
            console.log(`✅ Added ${Object.keys(deviceMarkers).length} device markers`);
        }
        
        // Check if a point is inside the land polygon (geofence detection)
        function isDeviceInsideGeofence(lat, lng) {
            if (!landLayer || !landData.geojson) {
                return true; // If no geofence defined, assume inside
            }
            
            try {
                const point = L.latLng(lat, lng);
                const layers = landLayer.getLayers();
                
                for (let layer of layers) {
                    if (layer.getBounds && layer.getBounds().contains(point)) {
                        // More precise check using ray casting algorithm
                        if (layer.feature && layer.feature.geometry) {
                            return isPointInPolygon([lng, lat], layer.feature.geometry);
                        }
                    }
                }
                return false;
            } catch (error) {
                console.warn('Error checking geofence:', error);
                return true; // Default to inside if error
            }
        }
        
        // Ray casting algorithm to check if point is inside polygon
        function isPointInPolygon(point, polygon) {
            const [x, y] = point;
            let inside = false;
            
            // Handle different geometry types
            let coordinates;
            if (polygon.type === 'Polygon') {
                coordinates = polygon.coordinates[0]; // Use outer ring
            } else if (polygon.type === 'MultiPolygon') {
                // Check all polygons in multipolygon
                for (let poly of polygon.coordinates) {
                    if (isPointInPolygonCoords(point, poly[0])) {
                        return true;
                    }
                }
                return false;
            } else {
                return true; // Unknown geometry type, assume inside
            }
            
            return isPointInPolygonCoords(point, coordinates);
        }
        
        // Helper function for point-in-polygon check
        function isPointInPolygonCoords(point, coordinates) {
            const [x, y] = point;
            let inside = false;
            
            for (let i = 0, j = coordinates.length - 1; i < coordinates.length; j = i++) {
                const [xi, yi] = coordinates[i];
                const [xj, yj] = coordinates[j];
                
                if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
                    inside = !inside;
                }
            }
            
            return inside;
        }
        
        // Add individual device marker
        function addDeviceMarker(device) {
            // Find GPS coordinates from sensors
            const latSensor = device.sensors.find(s => s.sensor_type === 'latitude');
            const lngSensor = device.sensors.find(s => s.sensor_type === 'longitude');
            
            if (!latSensor || !lngSensor || !latSensor.value || !lngSensor.value) {
                console.log(`⚠️ No GPS data for device ${device.name}`);
                return;
            }
            
            const lat = parseFloat(latSensor.value);
            const lng = parseFloat(lngSensor.value);
            
            if (isNaN(lat) || isNaN(lng)) {
                console.log(`⚠️ Invalid GPS coordinates for device ${device.name}`);
                return;
            }
            
            // Check if device is inside geofence
            const isInsideGeofence = isDeviceInsideGeofence(lat, lng);
            
            // Determine marker color based on device status, alerts, and geofence
            let markerColor = '#6b7280'; // Default gray (offline)
            let markerIcon = 'ph-device-mobile';
            let hasSignal = false;
            
            // Check for alerts first
            const hasAlerts = device.sensors.some(sensor => {
                return sensor.alert_enabled && sensor.alert_status && sensor.alert_status !== 'normal';
            });
            
            if (!isInsideGeofence) {
                // Device is outside geofence - red with signal
                markerColor = '#ef4444';
                markerIcon = 'ph-warning-octagon';
                hasSignal = true;
            } else if (hasAlerts) {
                // Device has alerts - red
                markerColor = '#dc2626';
                markerIcon = 'ph-warning-circle';
            } else if (device.status === 'online') {
                // Device is online - green
                markerColor = '#16a34a';
                markerIcon = 'ph-device-mobile';
            }
            
            // Create custom marker with optional signal indicator
            const signalIndicator = hasSignal ? `
                <div style="
                    position: absolute; 
                    top: -3px; 
                    right: -3px; 
                    width: 12px; 
                    height: 12px; 
                    background-color: #fbbf24; 
                    border-radius: 50%; 
                    border: 2px solid white;
                    animation: pulse 1s infinite;
                "></div>
            ` : '';
            
            const markerHtml = `
                <div style="
                    background-color: ${markerColor}; 
                    width: 30px; 
                    height: 30px; 
                    border-radius: 50%; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    border: 3px solid white; 
                    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
                    cursor: pointer;
                    position: relative;
                ">
                    <i class="${markerIcon}" style="color: white; font-size: 16px;"></i>
                    ${signalIndicator}
                </div>
            `;
            
            const customIcon = L.divIcon({
                html: markerHtml,
                className: 'custom-device-marker',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            // Create marker
            const marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
            
            // Create popup content with geofence status
            const popupContent = createDevicePopup(device, isInsideGeofence);
            marker.bindPopup(popupContent);
            
            // Store marker reference
            deviceMarkers[device.id] = marker;
            
            const geofenceStatus = isInsideGeofence ? 'inside' : 'OUTSIDE';
            console.log(`📍 Added marker for device ${device.name} at ${lat}, ${lng} (${geofenceStatus} geofence)`);
        }
        
        // Create device popup content
        function createDevicePopup(device, isInsideGeofence = null) {
            const latSensor = device.sensors.find(s => s.sensor_type === 'latitude');
            const lngSensor = device.sensors.find(s => s.sensor_type === 'longitude');
            const tempSensor = device.sensors.find(s => s.sensor_type === 'temperature');
            const humiditySensor = device.sensors.find(s => s.sensor_type === 'humidity');
            const batterySensor = device.sensors.find(s => s.sensor_type === 'battery');
            
            // Check geofence status if not provided
            if (isInsideGeofence === null && latSensor && lngSensor) {
                const lat = parseFloat(latSensor.value);
                const lng = parseFloat(lngSensor.value);
                if (!isNaN(lat) && !isNaN(lng)) {
                    isInsideGeofence = isDeviceInsideGeofence(lat, lng);
                }
            }
            
            const alertSensors = device.sensors.filter(sensor => {
                return sensor.alert_enabled && sensor.alert_status && sensor.alert_status !== 'normal';
            });
            
            return `
                <div class="device-popup p-3" style="min-width: 250px;">
                    <div class="d-flex align-items-center mb-2">
                        <i class="ph-device-mobile me-2 text-primary"></i>
                        <div>
                            <h6 class="mb-0">${device.name}</h6>
                            <small class="text-muted">${device.device_id}</small>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <span class="badge bg-${device.status === 'online' ? 'success' : 'secondary'} me-2">
                            ${device.status.charAt(0).toUpperCase() + device.status.slice(1)}
                        </span>
                        <span class="badge bg-info">${device.device_type}</span>
                        ${isInsideGeofence === false ? `
                            <span class="badge bg-danger ms-1">
                                <i class="ph-warning-octagon me-1"></i>Outside Geofence
                            </span>
                        ` : ''}
                    </div>
                    
                    ${!isInsideGeofence ? `
                        <div class="alert alert-danger py-2 mb-2">
                            <i class="ph-warning-octagon me-1"></i>
                            <strong>Device is outside the land boundary!</strong>
                        </div>
                    ` : ''}
                    
                    ${alertSensors.length > 0 ? `
                        <div class="alert alert-warning py-2 mb-2">
                            <i class="ph-warning-circle me-1"></i>
                            <strong>${alertSensors.length} Alert${alertSensors.length > 1 ? 's' : ''}</strong>
                        </div>
                    ` : ''}
                    
                    <div class="row g-2 mb-2">
                        ${tempSensor ? `
                            <div class="col-6">
                                <small class="text-muted">Temperature</small>
                                <div class="fw-medium">${tempSensor.value}°C</div>
                            </div>
                        ` : ''}
                        ${humiditySensor ? `
                            <div class="col-6">
                                <small class="text-muted">Humidity</small>
                                <div class="fw-medium">${humiditySensor.value}%</div>
                            </div>
                        ` : ''}
                        ${batterySensor ? `
                            <div class="col-6">
                                <small class="text-muted">Battery</small>
                                <div class="fw-medium">${batterySensor.value}%</div>
                            </div>
                        ` : ''}
                        <div class="col-6">
                            <small class="text-muted">Sensors</small>
                            <div class="fw-medium">${device.sensors.length}</div>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <small class="text-muted">Location</small>
                        <div class="small">${latSensor ? parseFloat(latSensor.value).toFixed(6) : 'N/A'}, ${lngSensor ? parseFloat(lngSensor.value).toFixed(6) : 'N/A'}</div>
                        ${isInsideGeofence !== null ? `
                            <div class="small mt-1">
                                <span class="badge bg-${isInsideGeofence ? 'success' : 'danger'} badge-sm">
                                    ${isInsideGeofence ? 'Inside Geofence' : 'Outside Geofence'}
                                </span>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="d-flex gap-2 mt-3">
                        <a href="/app/devices/${device.id}" class="btn btn-sm btn-primary flex-fill">
                            <i class="ph-eye me-1"></i>View Details
                        </a>
                    </div>
                </div>
            `;
        }
        
        // Fit map to show all content
        function fitMapToContent() {
            const group = new L.featureGroup();
            
            // Add land layer to group
            if (landLayer) {
                group.addLayer(landLayer);
            }
            
            // Add device markers to group
            Object.values(deviceMarkers).forEach(marker => {
                group.addLayer(marker);
            });
            
            // Fit map to group bounds
            if (group.getLayers().length > 0) {
                map.fitBounds(group.getBounds(), { padding: [20, 20] });
            }
        }
        
        // Start live updates
        function startLiveUpdates() {
            console.log('🔄 Starting live updates...');
            
            updateInterval = setInterval(() => {
                updateDeviceData();
            }, 10000); // Update every 10 seconds
        }
        
        // Update device data
        async function updateDeviceData() {
            console.log('🔄 Updating device data...');
            
            try {
                const response = await fetch(`/api/lands/${landData.id}/devices`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    updateDeviceMarkers(data.devices);
                    updateDeviceTable(data.devices);
                    updateOverviewCards(data.devices);
                }
                
            } catch (error) {
                console.error('❌ Error updating device data:', error);
            }
        }
        
        // Update device markers on map
        function updateDeviceMarkers(devices) {
            devices.forEach(device => {
                if (deviceMarkers[device.id]) {
                    // Update existing marker
                    const marker = deviceMarkers[device.id];
                    
                    // Update popup content
                    const popupContent = createDevicePopup(device);
                    marker.setPopupContent(popupContent);
                    
                    // Update marker position if GPS coordinates changed
                    const latSensor = device.sensors.find(s => s.sensor_type === 'latitude');
                    const lngSensor = device.sensors.find(s => s.sensor_type === 'longitude');
                    
                    if (latSensor && lngSensor) {
                        const newLat = parseFloat(latSensor.value);
                        const newLng = parseFloat(lngSensor.value);
                        
                        if (!isNaN(newLat) && !isNaN(newLng)) {
                            marker.setLatLng([newLat, newLng]);
                        }
                    }
                } else {
                    // Add new marker
                    addDeviceMarker(device);
                }
            });
        }
        
        // Update device table
        function updateDeviceTable(devices) {
            devices.forEach(device => {
                const row = document.querySelector(`tr[data-device-id="${device.id}"]`);
                if (row) {
                    // Update status
                    const statusCell = row.querySelector('.device-status');
                    if (statusCell) {
                        statusCell.innerHTML = `
                            <span class="badge bg-${device.status === 'online' ? 'success' : 'secondary'}">
                                ${device.status.charAt(0).toUpperCase() + device.status.slice(1)}
                            </span>
                        `;
                    }
                    
                    // Update location
                    const locationCell = row.querySelector('.device-location');
                    if (locationCell) {
                        const latSensor = device.sensors.find(s => s.sensor_type === 'latitude');
                        const lngSensor = device.sensors.find(s => s.sensor_type === 'longitude');
                        
                        if (latSensor && lngSensor) {
                            locationCell.innerHTML = `
                                <small class="text-success">
                                    <i class="ph-map-pin me-1"></i>
                                    ${parseFloat(latSensor.value).toFixed(6)}, ${parseFloat(lngSensor.value).toFixed(6)}
                                </small>
                            `;
                        }
                    }
                    
                    // Update alerts
                    const alertsCell = row.querySelector('.device-alerts');
                    if (alertsCell) {
                        const alertSensors = device.sensors.filter(sensor => {
                            return sensor.alert_enabled && sensor.alert_status && sensor.alert_status !== 'normal';
                        });
                        
                        if (alertSensors.length > 0) {
                            alertsCell.innerHTML = `<span class="badge bg-warning">${alertSensors.length} alerts</span>`;
                        } else {
                            alertsCell.innerHTML = `<span class="badge bg-success">Normal</span>`;
                        }
                    }
                    
                    // Update last seen
                    const lastSeenCell = row.querySelector('.device-last-seen');
                    if (lastSeenCell && device.last_seen_at) {
                        const lastSeen = new Date(device.last_seen_at);
                        const now = new Date();
                        const diffMinutes = Math.floor((now - lastSeen) / (1000 * 60));
                        
                        let timeText = 'Just now';
                        if (diffMinutes > 0) {
                            if (diffMinutes < 60) {
                                timeText = `${diffMinutes} minutes ago`;
                            } else {
                                const diffHours = Math.floor(diffMinutes / 60);
                                timeText = `${diffHours} hours ago`;
                            }
                        }
                        
                        lastSeenCell.innerHTML = `<small>${timeText}</small>`;
                    }
                    
                    // Add visual feedback
                    row.classList.add('device-updated');
                    setTimeout(() => row.classList.remove('device-updated'), 2000);
                }
            });
        }
        
        // Update overview cards
        function updateOverviewCards(devices) {
            const totalDevices = devices.length;
            const onlineDevices = devices.filter(d => d.status === 'online').length;
            
            let totalAlerts = 0;
            devices.forEach(device => {
                device.sensors.forEach(sensor => {
                    if (sensor.alert_enabled && sensor.alert_status && sensor.alert_status !== 'normal') {
                        totalAlerts++;
                    }
                });
            });
            
            document.getElementById('total-devices').textContent = totalDevices;
            document.getElementById('online-devices').textContent = onlineDevices;
            document.getElementById('alert-count').textContent = totalAlerts;
        }
        
        // Function to locate land on map (center on land boundary)
        function locateLandOnMap() {
            console.log('🎯 Locating land on map...');
            
            if (landLayer) {
                // Fit map to land boundary with some padding
                map.fitBounds(landLayer.getBounds(), { 
                    padding: [50, 50],
                    maxZoom: 16 
                });
                
                // Open land popup after a short delay
                setTimeout(() => {
                    landLayer.openPopup();
                }, 500);
                
                console.log('✅ Map centered on land boundary');
            } else {
                console.warn('⚠️ No land boundary found to locate');
                
                // Fallback: center on land location if available
                if (landData.location && landData.location.coordinates) {
                    const [lng, lat] = landData.location.coordinates;
                    map.setView([lat, lng], 15);
                    console.log('✅ Map centered on land location');
                } else {
                    alert('Unable to locate land on map - no boundary or location data available');
                }
            }
        }
        
        // Function to locate specific device on map
        function locateDeviceOnMap(deviceId) {
            console.log(`🎯 Locating device ${deviceId} on map...`);
            
            const marker = deviceMarkers[deviceId];
            if (marker) {
                // Center map on device marker
                const markerLatLng = marker.getLatLng();
                map.setView(markerLatLng, 18); // Zoom in close to the device
                
                // Open device popup after a short delay
                setTimeout(() => {
                    marker.openPopup();
                }, 500);
                
                // Add temporary highlight effect to the marker
                const markerElement = marker.getElement();
                if (markerElement) {
                    markerElement.style.transform = 'scale(1.3)';
                    markerElement.style.transition = 'transform 0.3s ease';
                    
                    setTimeout(() => {
                        markerElement.style.transform = 'scale(1)';
                    }, 1000);
                }
                
                console.log(`✅ Map centered on device ${deviceId}`);
            } else {
                console.warn(`⚠️ Device marker ${deviceId} not found on map`);
                
                // Try to find device in data and check if it has GPS coordinates
                const device = devicesData.find(d => d.id === deviceId);
                if (device) {
                    const latSensor = device.sensors.find(s => s.sensor_type === 'latitude');
                    const lngSensor = device.sensors.find(s => s.sensor_type === 'longitude');
                    
                    if (latSensor && lngSensor && latSensor.value && lngSensor.value) {
                        const lat = parseFloat(latSensor.value);
                        const lng = parseFloat(lngSensor.value);
                        
                        if (!isNaN(lat) && !isNaN(lng)) {
                            map.setView([lat, lng], 18);
                            console.log(`✅ Map centered on device ${deviceId} coordinates`);
                            return;
                        }
                    }
                }
                
                alert(`Unable to locate device on map - device not found or no GPS data available`);
            }
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (updateInterval) {
                clearInterval(updateInterval);
            }
        });
    </script>
@endsection
