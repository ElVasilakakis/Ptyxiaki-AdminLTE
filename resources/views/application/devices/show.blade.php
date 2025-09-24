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

        .sensor-table {
            background: white;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .sensor-row {
            transition: background-color 0.3s ease;
        }

        .sensor-row:hover {
            background-color: #f8fafc;
        }

        .sensor-status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .sensor-status-online {
            background-color: #10b981;
            animation: pulse 2s infinite;
        }

        .sensor-status-offline {
            background-color: #6b7280;
        }

        .alert-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .alert-badge.normal {
            background-color: #dcfce7;
            color: #166534;
        }

        .alert-badge.high {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .alert-badge.low {
            background-color: #fef3c7;
            color: #d97706;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .last-update {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .threshold-violation {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
        }

        .threshold-warning {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
        }

        .threshold-normal {
            background-color: #f0fdf4;
            border-left: 4px solid #22c55e;
        }

        .land-boundary-alert {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .land-boundary-alert.inside {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .land-boundary-alert.outside {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .land-boundary-alert.unknown {
            background-color: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }
    </style>
@endsection

@section('scripts')
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Turf.js for geospatial calculations -->
    <script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>

    @if($device->connection_type === 'mqtt')
        <!-- MQTT Device Script -->
        <script>
            // MQTT device functionality
            let map;
            let deviceMarker;
            let landPolygon;
            let distanceMode = false;
            let distanceMarker;
            let distanceLine;

            document.addEventListener('DOMContentLoaded', function() {
                initializeMap();
                // Refresh sensor data every 10 seconds for MQTT devices
                setInterval(refreshSensorData, 10000);
            });

            function initializeMap() {
                // Check if map container exists before initializing
                const mapContainer = document.getElementById('map');
                if (!mapContainer) {
                    console.log('Map container not found, skipping map initialization');
                    return;
                }

                // Initialize map with default location
                map = L.map('map').setView([38.2466, 21.7346], 10);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                // Add land polygon if geojson data exists
                @if($device->land && $device->land->geojson)
                    const landGeojson = @json($device->land->geojson);
                    const landColor = '{{ $device->land->color ?? "#3388ff" }}';
                    
                    if (landGeojson) {
                        landPolygon = L.geoJSON(landGeojson, {
                            style: {
                                color: landColor,
                                weight: 3,
                                opacity: 0.8,
                                fillColor: landColor,
                                fillOpacity: 0.2
                            }
                        }).addTo(map);
                        
                        // Fit map to show both device and land
                        const group = new L.featureGroup([landPolygon]);
                        map.fitBounds(group.getBounds().pad(0.1));
                    }
                @endif

                // Add device marker if location data exists
                @if($device->sensors->whereIn('sensor_type', ['latitude', 'longitude'])->count() >= 2)
                    @php
                        $latSensor = $device->sensors->where('sensor_type', 'latitude')->first();
                        $lngSensor = $device->sensors->where('sensor_type', 'longitude')->first();
                    @endphp
                    @if($latSensor && $lngSensor && $latSensor->value && $lngSensor->value)
                        const deviceLat = {{ $latSensor->value }};
                        const deviceLng = {{ $lngSensor->value }};

                        deviceMarker = L.marker([deviceLat, deviceLng])
                            .addTo(map)
                            .bindPopup('<strong>{{ $device->name }}</strong><br>{{ $device->device_id }}');

                        map.setView([deviceLat, deviceLng], 15);
                        
                        // Check if device is inside land boundary
                        checkBoundaryStatus();
                    @endif
                @endif
            }

            function locateDevice() {
                if (deviceMarker) {
                    map.setView(deviceMarker.getLatLng(), 15);
                    deviceMarker.openPopup();
                }
            }

            function locateLand() {
                if (landPolygon) {
                    // Fit map to land boundary with some padding
                    map.fitBounds(landPolygon.getBounds(), { 
                        padding: [50, 50],
                        maxZoom: 16 
                    });
                    
                    // Open land popup after a short delay
                    setTimeout(() => {
                        landPolygon.openPopup();
                    }, 500);
                    
                    console.log('✅ Map centered on land boundary');
                } else {
                    console.warn('⚠️ No land boundary found to locate');
                    alert('Unable to locate land on map - no boundary data available');
                }
            }

            function toggleDistanceMode() {
                distanceMode = !distanceMode;
                const btn = document.getElementById('distance-btn');

                if (distanceMode) {
                    btn.classList.add('active');
                    btn.innerHTML = '<i class="ph-x me-1"></i>Cancel Distance';
                    map.on('click', measureDistance);
                } else {
                    btn.classList.remove('active');
                    btn.innerHTML = '<i class="ph-ruler me-1"></i>Measure Distance';
                    map.off('click', measureDistance);

                    if (distanceMarker) {
                        map.removeLayer(distanceMarker);
                        distanceMarker = null;
                    }
                    if (distanceLine) {
                        map.removeLayer(distanceLine);
                        distanceLine = null;
                    }
                }
            }

            function measureDistance(e) {
                if (!deviceMarker) return;

                const clickedPoint = e.latlng;
                const devicePoint = deviceMarker.getLatLng();
                const distance = clickedPoint.distanceTo(devicePoint);

                if (distanceMarker) map.removeLayer(distanceMarker);
                if (distanceLine) map.removeLayer(distanceLine);

                distanceMarker = L.marker(clickedPoint)
                    .addTo(map)
                    .bindPopup(`Distance to device: ${(distance / 1000).toFixed(2)} km`);

                distanceLine = L.polyline([devicePoint, clickedPoint], {color: 'red'})
                    .addTo(map);

                distanceMarker.openPopup();
            }

            function refreshSensorData() {
                // MQTT devices can have more frequent updates
                fetch(`/app/devices/{{ $device->id }}/sensor-data`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateSensorDisplay(data.sensors);
                        }
                    })
                    .catch(error => console.error('Error refreshing sensor data:', error));
            }

            function updateSensorDisplay(sensors) {
                // Update sensor values in the UI
                sensors.forEach(sensor => {
                    const element = document.getElementById(`sensor-${sensor.id}`);
                    if (element) {
                        element.querySelector('.sensor-value').textContent = sensor.formatted_value;
                        element.querySelector('.sensor-timestamp').textContent = sensor.time_since_reading;
                    }
                });
                
                // Update device location if GPS coordinates changed
                const latSensor = sensors.find(s => s.sensor_type === 'latitude');
                const lngSensor = sensors.find(s => s.sensor_type === 'longitude');
                
                if (latSensor && lngSensor && latSensor.value && lngSensor.value) {
                    const newLat = parseFloat(latSensor.value);
                    const newLng = parseFloat(lngSensor.value);
                    
                    if (deviceMarker) {
                        deviceMarker.setLatLng([newLat, newLng]);
                    } else {
                        deviceMarker = L.marker([newLat, newLng])
                            .addTo(map)
                            .bindPopup('<strong>{{ $device->name }}</strong><br>{{ $device->device_id }}');
                    }
                    
                    // Check boundary status with new coordinates
                    checkBoundaryStatus();
                }
            }

            function checkBoundaryStatus() {
                if (!deviceMarker || !landPolygon) {
                    updateBoundaryAlert('unknown', 'Location boundary status unknown');
                    return;
                }
                
                const deviceLatLng = deviceMarker.getLatLng();
                const point = turf.point([deviceLatLng.lng, deviceLatLng.lat]);
                
                // Get the polygon from the land geojson
                @if($device->land && $device->land->geojson)
                    const landGeojson = @json($device->land->geojson);
                    
                    try {
                        const isInside = turf.booleanPointInPolygon(point, landGeojson);
                        
                        if (isInside) {
                            updateBoundaryAlert('inside', 'Device is inside {{ $device->land->land_name ?? "land boundary" }}');
                        } else {
                            updateBoundaryAlert('outside', 'Device is outside {{ $device->land->land_name ?? "land boundary" }}');
                        }
                    } catch (error) {
                        console.error('Error checking boundary:', error);
                        updateBoundaryAlert('unknown', 'Error checking boundary status');
                    }
                @else
                    updateBoundaryAlert('unknown', 'No land boundary defined');
                @endif
            }

            function updateBoundaryAlert(status, message) {
                const alertElement = document.getElementById('boundary-alert');
                if (alertElement) {
                    alertElement.className = `land-boundary-alert ${status}`;
                    alertElement.innerHTML = `
                        <i class="ph-${status === 'inside' ? 'check-circle' : status === 'outside' ? 'warning-circle' : 'question'} me-2"></i>
                        ${message}
                    `;
                }
            }

        </script>
    @else
        <!-- Webhook Device Script - No real-time connection needed -->
        <script>
            // Basic map functionality for webhook devices
            let map;
            let deviceMarker;
            let distanceMode = false;
            let distanceMarker;
            let distanceLine;

            document.addEventListener('DOMContentLoaded', function() {
                initializeMap();
                // Refresh sensor data every 30 seconds
                setInterval(refreshSensorData, 30000);
            });

            function initializeMap() {
                // Check if map container exists before initializing
                const mapContainer = document.getElementById('map');
                if (!mapContainer) {
                    console.log('Map container not found, skipping map initialization');
                    return;
                }

                // Initialize map with default location
                map = L.map('map').setView([38.2466, 21.7346], 10);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                // Add land polygon if geojson data exists
                @if($device->land && $device->land->geojson)
                    const landGeojson = @json($device->land->geojson);
                    const landColor = '{{ $device->land->color ?? "#3388ff" }}';
                    
                    if (landGeojson) {
                        landPolygon = L.geoJSON(landGeojson, {
                            style: {
                                color: landColor,
                                weight: 3,
                                opacity: 0.8,
                                fillColor: landColor,
                                fillOpacity: 0.2
                            }
                        }).addTo(map);
                        
                        landPolygon.bindPopup(`
                            <div class="p-2">
                                <h6 class="mb-1">{{ $device->land->land_name }}</h6>
                                <small class="text-muted">Land Boundary</small>
                            </div>
                        `);
                    }
                @endif

                // Add device marker if location data exists
                @if($device->sensors->whereIn('sensor_type', ['latitude', 'longitude'])->count() >= 2)
                    @php
                        $latSensor = $device->sensors->where('sensor_type', 'latitude')->first();
                        $lngSensor = $device->sensors->where('sensor_type', 'longitude')->first();
                    @endphp
                    @if($latSensor && $lngSensor && $latSensor->value && $lngSensor->value)
                        const deviceLat = {{ $latSensor->value }};
                        const deviceLng = {{ $lngSensor->value }};

                        deviceMarker = L.marker([deviceLat, deviceLng])
                            .addTo(map)
                            .bindPopup('<strong>{{ $device->name }}</strong><br>{{ $device->device_id }}');

                        map.setView([deviceLat, deviceLng], 15);
                    @endif
                @endif

                // Fit map to show both device and land if both exist
                @if($device->land && $device->land->geojson)
                    if (landPolygon && deviceMarker) {
                        const group = new L.featureGroup([landPolygon, deviceMarker]);
                        map.fitBounds(group.getBounds().pad(0.1));
                    } else if (landPolygon) {
                        map.fitBounds(landPolygon.getBounds().pad(0.1));
                    }
                @endif
            }

            function locateDevice() {
                if (deviceMarker) {
                    map.setView(deviceMarker.getLatLng(), 15);
                    deviceMarker.openPopup();
                }
            }

            function locateLand() {
                if (landPolygon) {
                    // Fit map to land boundary with some padding
                    map.fitBounds(landPolygon.getBounds(), { 
                        padding: [50, 50],
                        maxZoom: 16 
                    });
                    
                    // Open land popup after a short delay
                    setTimeout(() => {
                        landPolygon.openPopup();
                    }, 500);
                    
                    console.log('✅ Map centered on land boundary');
                } else {
                    console.warn('⚠️ No land boundary found to locate');
                    alert('Unable to locate land on map - no boundary data available');
                }
            }

            function toggleDistanceMode() {
                distanceMode = !distanceMode;
                const btn = document.getElementById('distance-btn');

                if (distanceMode) {
                    btn.classList.add('active');
                    btn.innerHTML = '<i class="ph-x me-1"></i>Cancel Distance';
                    map.on('click', measureDistance);
                } else {
                    btn.classList.remove('active');
                    btn.innerHTML = '<i class="ph-ruler me-1"></i>Measure Distance';
                    map.off('click', measureDistance);

                    if (distanceMarker) {
                        map.removeLayer(distanceMarker);
                        distanceMarker = null;
                    }
                    if (distanceLine) {
                        map.removeLayer(distanceLine);
                        distanceLine = null;
                    }
                }
            }

            function measureDistance(e) {
                if (!deviceMarker) return;

                const clickedPoint = e.latlng;
                const devicePoint = deviceMarker.getLatLng();
                const distance = clickedPoint.distanceTo(devicePoint);

                if (distanceMarker) map.removeLayer(distanceMarker);
                if (distanceLine) map.removeLayer(distanceLine);

                distanceMarker = L.marker(clickedPoint)
                    .addTo(map)
                    .bindPopup(`Distance to device: ${(distance / 1000).toFixed(2)} km`);

                distanceLine = L.polyline([devicePoint, clickedPoint], {color: 'red'})
                    .addTo(map);

                distanceMarker.openPopup();
            }

            function refreshSensorData() {
                // Webhook devices don't have real-time updates
                // Data is updated when webhooks are received
                console.log('Webhook device - data updates via HTTP POST');
            }
        </script>
    @endif
@endsection

@section('content')
    <div class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    @if($device->connection_type === 'mqtt')
                        @include('application.devices.partials.mqtt-device-content')
                    @else
                        @include('application.devices.partials.webhook-device-content')
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
