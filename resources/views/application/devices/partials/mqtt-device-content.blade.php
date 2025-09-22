<div class="card-header d-flex justify-content-between align-items-center">
    <div>
        <h6 class="mb-1">{{ $device->name }}</h6>
        <div class="text-muted">
            <i class="ph-broadcast me-1"></i>
            MQTT Device - {{ $device->device_id }}
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-info bg-opacity-10 text-info">
            <i class="ph-broadcast me-1"></i>
            MQTT
        </span>
        @php
            $statusColor = match($device->status) {
                'online' => 'success',
                'offline' => 'secondary', 
                'maintenance' => 'warning',
                'error' => 'danger',
                default => 'secondary'
            };
        @endphp
        <span class="badge bg-{{ $statusColor }} bg-opacity-10 text-{{ $statusColor }}">
            {{ ucfirst($device->status) }}
        </span>
        @if($device->connection_broker)
            <span class="badge bg-primary bg-opacity-10 text-primary">
                {{ ucfirst(str_replace('_', ' ', $device->connection_broker)) }}
            </span>
        @endif
    </div>
</div>

<div class="card-body">
    <!-- Device Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="ph-info me-2"></i>Device Information
                    </h6>
                    <div class="row">
                        <div class="col-sm-6">
                            <strong>Device Type:</strong><br>
                            <span class="text-muted">{{ ucfirst($device->device_type) }}</span>
                        </div>
                        <div class="col-sm-6">
                            <strong>Land:</strong><br>
                            <span class="text-muted">{{ $device->land->land_name }}</span>
                        </div>
                        <div class="col-sm-6 mt-2">
                            <strong>Status:</strong><br>
                            <span class="badge bg-{{ $statusColor }} bg-opacity-10 text-{{ $statusColor }}">
                                {{ ucfirst($device->status) }}
                            </span>
                        </div>
                        <div class="col-sm-6 mt-2">
                            <strong>Active:</strong><br>
                            <span class="badge bg-{{ $device->is_active ? 'success' : 'secondary' }} bg-opacity-10 text-{{ $device->is_active ? 'success' : 'secondary' }}">
                                {{ $device->is_active ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        @if($device->description)
                            <div class="col-12 mt-2">
                                <strong>Description:</strong><br>
                                <span class="text-muted">{{ $device->description }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="ph-broadcast me-2"></i>MQTT Connection
                    </h6>
                    <div class="row">
                        @if($device->client_id)
                            <div class="col-sm-6">
                                <strong>Client ID:</strong><br>
                                <span class="text-muted">{{ $device->client_id }}</span>
                            </div>
                        @endif
                        @if($device->connection_broker)
                            <div class="col-sm-6">
                                <strong>Broker:</strong><br>
                                <span class="text-muted">{{ ucfirst(str_replace('_', ' ', $device->connection_broker)) }}</span>
                            </div>
                        @endif
                        @if($device->mqtt_host)
                            <div class="col-sm-6 mt-2">
                                <strong>MQTT Host:</strong><br>
                                <span class="text-muted">{{ $device->mqtt_host }}</span>
                            </div>
                        @endif
                        @if($device->port)
                            <div class="col-sm-6 mt-2">
                                <strong>Port:</strong><br>
                                <span class="text-muted">{{ $device->port }}</span>
                            </div>
                        @endif
                        <div class="col-sm-6 mt-2">
                            <strong>SSL/TLS:</strong><br>
                            <span class="badge bg-{{ $device->use_ssl ? 'success' : 'secondary' }} bg-opacity-10 text-{{ $device->use_ssl ? 'success' : 'secondary' }}">
                                {{ $device->use_ssl ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        @if($device->username)
                            <div class="col-sm-6 mt-2">
                                <strong>Username:</strong><br>
                                <span class="text-muted">{{ $device->username }}</span>
                            </div>
                        @endif
                        <div class="col-sm-6 mt-2">
                            <strong>Auto Reconnect:</strong><br>
                            <span class="badge bg-{{ $device->auto_reconnect ? 'success' : 'secondary' }} bg-opacity-10 text-{{ $device->auto_reconnect ? 'success' : 'secondary' }}">
                                {{ $device->auto_reconnect ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        <div class="col-sm-6 mt-2">
                            <strong>Keep Alive:</strong><br>
                            <span class="text-muted">{{ $device->keepalive }}s</span>
                        </div>
                        <div class="col-sm-6 mt-2">
                            <strong>Timeout:</strong><br>
                            <span class="text-muted">{{ $device->timeout }}s</span>
                        </div>
                        @if($device->mqtt_topics && count($device->mqtt_topics) > 0)
                            <div class="col-12 mt-3">
                                <strong>MQTT Topics:</strong><br>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    @foreach($device->mqtt_topics as $topic)
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">{{ $topic }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Sensor Data -->
    @if($device->sensors->count() > 0)
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="mb-3">
                    <i class="ph-thermometer me-2"></i>Sensor Data
                    <span class="badge bg-info bg-opacity-10 text-info ms-2">{{ $device->sensors->count() }} sensors</span>
                </h6>
                
                <div class="table-responsive">
                    <table class="table table-striped sensor-table">
                        <thead class="table-dark">
                            <tr>
                                <th>Sensor</th>
                                <th>Current Value</th>
                                <th>Status</th>
                                <th>Last Reading</th>
                                <th>Alert Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($device->sensors as $sensor)
                                @php 
                                    $alertStatus = $sensor->getAlertStatus();
                                    $thresholdClass = match($alertStatus) {
                                        'high' => 'threshold-violation',
                                        'low' => 'threshold-warning', 
                                        default => 'threshold-normal'
                                    };
                                @endphp
                                <tr class="sensor-row {{ $thresholdClass }}" id="sensor-{{ $sensor->id }}">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @switch($sensor->sensor_type)
                                                @case('temperature')
                                                    <i class="ph-thermometer ph-lg text-danger me-2"></i>
                                                    @break
                                                @case('humidity')
                                                    <i class="ph-drop ph-lg text-info me-2"></i>
                                                    @break
                                                @case('pressure')
                                                    <i class="ph-gauge ph-lg text-warning me-2"></i>
                                                    @break
                                                @case('light')
                                                    <i class="ph-sun ph-lg text-yellow me-2"></i>
                                                    @break
                                                @case('soil_moisture')
                                                    <i class="ph-plant ph-lg text-success me-2"></i>
                                                    @break
                                                @case('ph')
                                                    <i class="ph-test-tube ph-lg text-purple me-2"></i>
                                                    @break
                                                @case('latitude')
                                                @case('longitude')
                                                    <i class="ph-map-pin ph-lg text-primary me-2"></i>
                                                    @break
                                                @default
                                                    <i class="ph-gauge ph-lg text-secondary me-2"></i>
                                            @endswitch
                                            <div>
                                                <div class="fw-semibold">{{ $sensor->sensor_name ?: ucfirst(str_replace('_', ' ', $sensor->sensor_type)) }}</div>
                                                <div class="text-muted fs-sm">{{ ucfirst($sensor->sensor_type) }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="sensor-value fw-semibold">
                                            {{ $sensor->getFormattedValue() }}
                                        </div>
                                    </td>
                                    <td>
                                        @if($sensor->hasRecentReading())
                                            <span class="sensor-status-indicator sensor-status-online"></span>
                                            <span class="text-success">Online</span>
                                        @else
                                            <span class="sensor-status-indicator sensor-status-offline"></span>
                                            <span class="text-muted">Offline</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="sensor-timestamp">
                                            @if($sensor->reading_timestamp)
                                                <div class="text-muted fs-sm">{{ $sensor->reading_timestamp->format('M d, H:i') }}</div>
                                                <div class="text-muted fs-xs">{{ $sensor->getTimeSinceLastReading() }}</div>
                                            @else
                                                <span class="text-muted">No data</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php $alertStatus = $sensor->getAlertStatus(); @endphp
                                        <span class="alert-badge {{ $alertStatus }}">
                                            @if($alertStatus === 'high')
                                                <i class="ph-arrow-up me-1"></i>High Alert
                                            @elseif($alertStatus === 'low')
                                                <i class="ph-arrow-down me-1"></i>Low Alert
                                            @else
                                                <i class="ph-check me-1"></i>Normal
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-4">
            <i class="ph-thermometer ph-3x text-muted mb-3"></i>
            <h6 class="text-muted">No sensor data available</h6>
            <p class="text-muted">This MQTT device hasn't sent any sensor data yet.</p>
        </div>
    @endif

    <!-- Land Boundary Status -->
    @if($device->land && $device->land->geojson)
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="mb-3">
                    <i class="ph-map-trifold me-2"></i>Land Boundary Status
                </h6>
                <div id="boundary-alert" class="land-boundary-alert unknown">
                    <i class="ph-question me-2"></i>
                    Checking boundary status...
                </div>
            </div>
        </div>
    @endif

    <!-- Device Location Map -->
    @php
        $hasLocationData = $device->sensors->whereIn('sensor_type', ['latitude', 'longitude'])->count() >= 2;
        $latSensor = $device->sensors->where('sensor_type', 'latitude')->first();
        $lngSensor = $device->sensors->where('sensor_type', 'longitude')->first();
    @endphp

    <div class="row mb-4">
        <div class="col-12">
            <h6 class="mb-3">
                <i class="ph-map-pin me-2"></i>Device Location
                @if($device->land && $device->land->geojson)
                    <span class="badge bg-info bg-opacity-10 text-info ms-2">
                        <i class="ph-polygon me-1"></i>{{ $device->land->land_name }}
                    </span>
                @endif
            </h6>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    @if($hasLocationData && $latSensor && $lngSensor && $latSensor->value && $lngSensor->value)
                        <span class="text-muted">Coordinates: </span>
                        <strong>{{ number_format($latSensor->value, 6) }}, {{ number_format($lngSensor->value, 6) }}</strong>
                    @else
                        <span class="text-muted">Waiting for location data from MQTT...</span>
                    @endif
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="locateDevice()">
                        <i class="ph-crosshairs me-1"></i>Locate Device
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="distance-btn" onclick="toggleDistanceMode()">
                        <i class="ph-ruler me-1"></i>Measure Distance
                    </button>
                </div>
            </div>
            
            <div id="map"></div>
        </div>
    </div>

    <!-- Actions -->
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <a href="{{ route('app.devices.index') }}" class="btn btn-outline-secondary">
                <i class="ph-arrow-left me-2"></i>Back to Devices
            </a>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('app.devices.edit', $device) }}" class="btn btn-warning">
                <i class="ph-pencil me-2"></i>Edit Device
            </a>
            <form action="{{ route('app.devices.destroy', $device) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Are you sure you want to delete this device? This will also delete all associated sensor data.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                    <i class="ph-trash me-2"></i>Delete Device
                </button>
            </form>
        </div>
    </div>
</div>
