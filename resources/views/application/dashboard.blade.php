@extends('layouts.application.app')

@section('pageheader')
    <div class="page-header-content d-lg-flex">
        <div class="d-flex">
            <h4 class="page-title mb-0">
                Dashboard - <span class="fw-normal">IoT Monitoring Overview</span>
            </h4>

            <a href="#page_header"
                class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
                data-bs-toggle="collapse">
                <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
            </a>
        </div>

        <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
            <div class="hstack gap-3 mb-3 mb-lg-0">
                <div class="live-indicator">
                    <span class="badge bg-success">
                        <i class="ph-broadcast me-1"></i>Live Updates
                    </span>
                </div>
                <small class="text-muted">Auto-refreshes every 30 seconds</small>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="content">
        <!-- Overview Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-map-pin-area" style="font-size: 2.5rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h3 class="mb-0" id="total-lands">{{ $stats['total_lands'] }}</h3>
                                <div class="fw-medium">Total Lands</div>
                                <small class="opacity-75">{{ $stats['active_lands'] }} active, {{ $stats['inactive_lands'] }} inactive</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-devices" style="font-size: 2.5rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h3 class="mb-0" id="total-devices">{{ $stats['total_devices'] }}</h3>
                                <div class="fw-medium">Total Devices</div>
                                <small class="opacity-75" id="device-status">{{ $stats['online_devices'] }} online, {{ $stats['offline_devices'] }} offline</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-thermometer" style="font-size: 2.5rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h3 class="mb-0">{{ $stats['total_sensors'] }}</h3>
                                <div class="fw-medium">Total Sensors</div>
                                <small class="opacity-75">Across all devices</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-warning-circle" style="font-size: 2.5rem;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h3 class="mb-0" id="total-alerts">{{ $stats['total_alerts'] }}</h3>
                                <div class="fw-medium">Active Alerts</div>
                                <small class="opacity-75" id="alert-breakdown">{{ $stats['high_alerts'] }} high, {{ $stats['low_alerts'] }} low</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats Row -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="d-inline-flex align-items-center justify-content-center w-60px h-60px bg-light rounded-circle mb-3">
                            <i class="ph-wifi-high text-success" style="font-size: 1.5rem;"></i>
                        </div>
                        <h4 class="mb-1" id="online-devices">{{ $stats['online_devices'] }}</h4>
                        <div class="text-muted">Online Devices</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="d-inline-flex align-items-center justify-content-center w-60px h-60px bg-light rounded-circle mb-3">
                            <i class="ph-activity text-primary" style="font-size: 1.5rem;"></i>
                        </div>
                        <h4 class="mb-1" id="recent-activity">{{ $stats['recent_activity'] }}</h4>
                        <div class="text-muted">Recent Activity</div>
                        <small class="text-muted">Last 24 hours</small>
                    </div>
                </div>
            </div>


            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="d-inline-flex align-items-center justify-content-center w-60px h-60px bg-light rounded-circle mb-3">
                            <i class="ph-map-pin text-danger" style="font-size: 1.5rem;"></i>
                        </div>
                        <h4 class="mb-1">{{ $devicesWithGPS->count() }}</h4>
                        <div class="text-muted">GPS Enabled</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Alerts Row -->
        <div class="row mb-4">
            <!-- Device Types Chart -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="ph-chart-pie me-2"></i>Device Types Distribution
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="deviceTypesChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Alerts -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="ph-warning-circle me-2"></i>Recent Alerts
                        </h6>
                        <span class="badge bg-warning" id="alerts-count">{{ $recentAlerts->count() }}</span>
                    </div>
                    <div class="card-body">
                        <div id="alerts-container" style="max-height: 300px; overflow-y: auto;">
                            @if($recentAlerts->count() > 0)
                                @foreach($recentAlerts as $alert)
                                    @php
                                        $alertStatus = $alert->getAlertStatus();
                                        $isGeofenceViolation = in_array($alert->sensor_type, ['latitude', 'longitude']) && $alertStatus === 'high';
                                        $alertIcon = $isGeofenceViolation ? 'warning-octagon' : ($alertStatus === 'high' ? 'arrow-up' : 'arrow-down');
                                        $alertClass = $alertStatus === 'high' ? 'danger' : 'warning';
                                    @endphp
                                    <div class="d-flex align-items-center mb-3 alert-item">
                                        <div class="flex-shrink-0">
                                            <div class="bg-{{ $alertClass }} text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="ph-{{ $alertIcon }}"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-medium">{{ $alert->device->name }}</div>
                                            <div class="text-muted small">
                                                @if($isGeofenceViolation)
                                                    <i class="ph-map-pin me-1 text-danger"></i>Geofence Violation - Device outside {{ $alert->device->land->land_name }}
                                                @else
                                                    {{ $alert->sensor_type }}: {{ $alert->getFormattedValue() }}
                                                @endif
                                            </div>
                                            <div class="text-muted small">{{ $alert->reading_timestamp ? \Carbon\Carbon::parse($alert->reading_timestamp)->diffForHumans() : 'Unknown' }}</div>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <span class="badge bg-{{ $alertClass }}">
                                                @if($isGeofenceViolation)
                                                    Geofence Alert
                                                @else
                                                    {{ ucfirst($alertStatus) }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center py-4">
                                    <i class="ph-check-circle text-success" style="font-size: 3rem;"></i>
                                    <h6 class="mt-3 text-muted">No Recent Alerts</h6>
                                    <p class="text-muted">All sensors are operating normally.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sensor Types and System Status -->
        <div class="row mb-4">
            <!-- Sensor Types Chart -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="ph-chart-bar me-2"></i>Sensor Types Distribution
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="sensorTypesChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="ph-gear me-2"></i>System Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="ph-check"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="fw-medium">Database</div>
                                        <div class="text-success small">Connected</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="ph-clock"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="fw-medium">Uptime</div>
                                        <div class="text-info small" id="system-uptime">Loading...</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="ph-users"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="fw-medium">User</div>
                                        <div class="text-primary small">{{ Auth::user()->name }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Devices Overview Map -->
        @if($devicesWithGPS->count() > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="ph-map-trifold me-2"></i>Devices Overview Map
                        </h6>
                        <span class="badge bg-info">{{ $devicesWithGPS->count() }} devices with GPS</span>
                    </div>
                    <div class="card-body p-0">
                        <div id="overview-map" style="height: 400px; width: 100%;"></div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Recent Devices Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="ph-devices me-2"></i>Recent Device Activity
                        </h6>
                        <span class="badge bg-primary">Last 24 hours</span>
                    </div>
                    <div class="card-body">
                        @if($devices->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Device</th>
                                            <th>Land</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Sensors</th>
                                            <th>Alerts</th>
                                            <th>Last Seen</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($devices->sortByDesc('last_seen_at')->take(10) as $device)
                                            <tr>
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
                                                    <span class="badge bg-light text-dark">{{ $device->land->land_name }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">{{ ucfirst($device->device_type) }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $device->status === 'online' ? 'success' : 'secondary' }}">
                                                        {{ ucfirst($device->status) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary">{{ $device->sensors->count() }} sensors</span>
                                                </td>
                                                <td>
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
                                                    @if($device->last_seen_at)
                                                        <small>{{ $device->last_seen_at->diffForHumans() }}</small>
                                                    @else
                                                        <small class="text-muted">Never</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('app.devices.show', $device) }}" class="btn btn-sm btn-outline-primary">
                                                        <i class="ph-eye me-1"></i>View
                                                    </a>
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
                                <p class="text-muted">Add devices to start monitoring your IoT infrastructure.</p>
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

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Include Leaflet for map -->
    @if($devicesWithGPS->count() > 0)
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @endif

    <style>
        .live-indicator .badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .alert-item {
            transition: background-color 0.3s ease;
        }
        
        .alert-item:hover {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 0.5rem;
            margin: -0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .w-60px {
            width: 60px !important;
        }
        
        .h-60px {
            height: 60px !important;
        }
    </style>

    <script>
        // Global variables
        let deviceTypesChart;
        let sensorTypesChart;
        let overviewMap;
        let updateInterval;
        let startTime = Date.now();
        
        // Data from server
        const deviceTypes = @json($deviceTypes);
        const sensorTypes = @json($sensorTypes);
        const devicesWithGPS = @json($devicesWithGPS);
        
        // Initialize dashboard when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            @if($devicesWithGPS->count() > 0)
            initializeMap();
            @endif
            startLiveUpdates();
            updateSystemUptime();
            
            // Update uptime every minute
            setInterval(updateSystemUptime, 60000);
        });
        
        // Initialize charts
        function initializeCharts() {
            // Device Types Pie Chart
            const deviceTypesCtx = document.getElementById('deviceTypesChart').getContext('2d');
            const deviceTypesData = Object.entries(deviceTypes);
            
            if (deviceTypesData.length > 0) {
                deviceTypesChart = new Chart(deviceTypesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: deviceTypesData.map(([type, count]) => type.charAt(0).toUpperCase() + type.slice(1)),
                        datasets: [{
                            data: deviceTypesData.map(([type, count]) => count),
                            backgroundColor: [
                                '#3b82f6', // blue
                                '#10b981', // green
                                '#f59e0b', // yellow
                                '#ef4444', // red
                                '#8b5cf6', // purple
                                '#06b6d4', // cyan
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
            } else {
                document.getElementById('deviceTypesChart').parentElement.innerHTML = `
                    <div class="text-center py-4">
                        <i class="ph-chart-pie text-muted" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-muted">No device data</h6>
                        <p class="text-muted">Add devices to see distribution.</p>
                    </div>
                `;
            }
            
            // Sensor Types Bar Chart
            const sensorTypesCtx = document.getElementById('sensorTypesChart').getContext('2d');
            const sensorTypesData = Object.entries(sensorTypes);
            
            if (sensorTypesData.length > 0) {
                sensorTypesChart = new Chart(sensorTypesCtx, {
                    type: 'bar',
                    data: {
                        labels: sensorTypesData.map(([type, count]) => type.charAt(0).toUpperCase() + type.slice(1)),
                        datasets: [{
                            label: 'Number of Sensors',
                            data: sensorTypesData.map(([type, count]) => count),
                            backgroundColor: '#3b82f6',
                            borderColor: '#1d4ed8',
                            borderWidth: 1,
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            } else {
                document.getElementById('sensorTypesChart').parentElement.innerHTML = `
                    <div class="text-center py-4">
                        <i class="ph-chart-bar text-muted" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-muted">No sensor data</h6>
                        <p class="text-muted">Add sensors to see distribution.</p>
                    </div>
                `;
            }
        }
        
        @if($devicesWithGPS->count() > 0)
        // Initialize overview map
        function initializeMap() {
            // Calculate center point
            let centerLat = 39.0742;
            let centerLng = 21.8243;
            
            if (devicesWithGPS.length > 0) {
                const latitudes = devicesWithGPS.map(device => {
                    const latSensor = device.sensors.find(s => s.sensor_type === 'latitude');
                    return latSensor ? parseFloat(latSensor.value) : null;
                }).filter(lat => lat !== null);
                
                const longitudes = devicesWithGPS.map(device => {
                    const lngSensor = device.sensors.find(s => s.sensor_type === 'longitude');
                    return lngSensor ? parseFloat(lngSensor.value) : null;
                }).filter(lng => lng !== null);
                
                if (latitudes.length > 0 && longitudes.length > 0) {
                    centerLat = latitudes.reduce((a, b) => a + b) / latitudes.length;
                    centerLng = longitudes.reduce((a, b) => a + b) / longitudes.length;
                }
            }
            
            // Create map
            overviewMap = L.map('overview-map').setView([centerLat, centerLng], 10);
            
            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(overviewMap);
            
            // Add device markers
            devicesWithGPS.forEach(device => {
                const latSensor = device.sensors.find(s => s.sensor_type === 'latitude');
                const lngSensor = device.sensors.find(s => s.sensor_type === 'longitude');
                
                if (latSensor && lngSensor) {
                    const lat = parseFloat(latSensor.value);
                    const lng = parseFloat(lngSensor.value);
                    
                    if (!isNaN(lat) && !isNaN(lng)) {
                        // Determine marker color
                        let markerColor = device.status === 'online' ? '#10b981' : '#6b7280';
                        
                        // Check for alerts
                        const hasAlerts = device.sensors.some(sensor => {
                            return sensor.alert_enabled && sensor.alert_status && sensor.alert_status !== 'normal';
                        });
                        
                        if (hasAlerts) {
                            markerColor = '#ef4444';
                        }
                        
                        const markerHtml = `
                            <div style="
                                background-color: ${markerColor}; 
                                width: 20px; 
                                height: 20px; 
                                border-radius: 50%; 
                                border: 2px solid white; 
                                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                            "></div>
                        `;
                        
                        const customIcon = L.divIcon({
                            html: markerHtml,
                            className: 'custom-marker',
                            iconSize: [20, 20],
                            iconAnchor: [10, 10]
                        });
                        
                        const marker = L.marker([lat, lng], { icon: customIcon }).addTo(overviewMap);
                        
                        marker.bindPopup(`
                            <div class="p-2">
                                <h6 class="mb-1">${device.name}</h6>
                                <small class="text-muted">${device.land.land_name}</small><br>
                                <span class="badge bg-${device.status === 'online' ? 'success' : 'secondary'} mt-1">
                                    ${device.status.charAt(0).toUpperCase() + device.status.slice(1)}
                                </span>
                            </div>
                        `);
                    }
                }
            });
        }
        @endif
        
        // Start live updates
        function startLiveUpdates() {
            updateInterval = setInterval(() => {
                updateDashboardData();
            }, 30000); // Update every 30 seconds
        }
        
        // Update dashboard data
        async function updateDashboardData() {
            try {
                const response = await fetch('/api/dashboard/data', {
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
                    // Update statistics
                    document.getElementById('online-devices').textContent = data.stats.online_devices;
                    document.getElementById('total-alerts').textContent = data.stats.total_alerts;
                    document.getElementById('recent-activity').textContent = data.stats.recent_activity;
                    document.getElementById('device-status').textContent = `${data.stats.online_devices} online, ${data.stats.offline_devices} offline`;
                    
                    // Update alerts
                    updateAlertsSection(data.alerts);
                }
                
            } catch (error) {
                console.error('❌ Error updating dashboard data:', error);
            }
        }
        
        // Update alerts section
        function updateAlertsSection(alerts) {
            const alertsContainer = document.getElementById('alerts-container');
            const alertsCount = document.getElementById('alerts-count');
            
            alertsCount.textContent = alerts.length;
            
            if (alerts.length > 0) {
                let alertsHtml = '';
                alerts.forEach(alert => {
                    const alertClass = alert.alert_status === 'high' ? 'danger' : 'warning';
                    const alertIcon = alert.alert_status === 'high' ? 'arrow-up' : 'arrow-down';
                    
                    alertsHtml += `
                        <div class="d-flex align-items-center mb-3 alert-item">
                            <div class="flex-shrink-0">
                                <div class="bg-${alertClass} text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="ph-${alertIcon}"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-medium">${alert.device_name}</div>
                                <div class="text-muted small">${alert.sensor_type}: ${alert.value}</div>
                                <div class="text-muted small">${alert.timestamp}</div>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="badge bg-${alertClass}">
                                    ${alert.alert_status.charAt(0).toUpperCase() + alert.alert_status.slice(1)}
                                </span>
                            </div>
                        </div>
                    `;
                });
                alertsContainer.innerHTML = alertsHtml;
            } else {
                alertsContainer.innerHTML = `
                    <div class="text-center py-4">
                        <i class="ph-check-circle text-success" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-muted">No Recent Alerts</h6>
                        <p class="text-muted">All sensors are operating normally.</p>
                    </div>
                `;
            }
        }
        
        // Update system uptime
        function updateSystemUptime() {
            const now = Date.now();
            const uptime = now - startTime;
            
            const hours = Math.floor(uptime / (1000 * 60 * 60));
            const minutes = Math.floor((uptime % (1000 * 60 * 60)) / (1000 * 60));
            
            let uptimeText = '';
            if (hours > 0) {
                uptimeText = `${hours}h ${minutes}m`;
            } else {
                uptimeText = `${minutes}m`;
            }
            
            document.getElementById('system-uptime').textContent = uptimeText;
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (updateInterval) {
                clearInterval(updateInterval);
            }
        });
    </script>
@endsection
