<div class="card-header d-flex justify-content-between align-items-center">
    <h6 class="mb-0">Device: {{ $device->name }}</h6>
    <div class="d-flex align-items-center gap-2">
        <div id="location-status" class="location-status unknown">Location Status Unknown</div>
        <div id="mqtt-status" class="mqtt-status disconnected">Disconnected</div>
        <button type="button" id="test-btn" class="btn btn-success" style="display: none;">
            <i class="ph-paper-plane me-2"></i>Send Test
        </button>
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
                        <span class="badge bg-{{ $device->status === 'online' ? 'success' : ($device->status === 'offline' ? 'secondary' : ($device->status === 'maintenance' ? 'warning' : 'danger')) }}">
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

    <!-- Sensor Data Table -->
    <div class="row mb-4">
        <div class="col-12">
            <h6 class="fw-semibold mb-3">Live Sensor Data</h6>
            <div class="sensor-table">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sensor</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Alert</th>
                                <th>Last Update</th>
                            </tr>
                        </thead>
                        <tbody id="sensors-table-body">
                            @if ($device->sensors->count() > 0)
                                @foreach ($device->sensors as $sensor)
                                    <tr class="sensor-row" data-sensor-type="{{ $sensor->sensor_type }}">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="ph-thermometer me-2 text-primary"></i>
                                                <div>
                                                    <div class="fw-medium">{{ $sensor->sensor_type }}</div>
                                                    <small class="text-muted">{{ $sensor->sensor_name }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="sensor-value fw-semibold">{{ $sensor->getFormattedValue() }}</td>
                                        <td class="sensor-status">
                                            <span class="sensor-status-indicator {{ $sensor->hasRecentReading() ? 'sensor-status-online' : 'sensor-status-offline' }}"></span>
                                            {{ $sensor->hasRecentReading() ? 'Online' : 'Offline' }}
                                        </td>
                                        <td class="sensor-alert">
                                            @php
                                                $alertStatus = $sensor->getAlertStatus();
                                                $alertClass = $alertStatus === 'normal' ? 'normal' : ($alertStatus === 'high' ? 'high' : 'low');
                                                $alertText = $alertStatus === 'normal' ? 'Normal' : ($alertStatus === 'high' ? 'High Alert' : 'Low Alert');
                                            @endphp
                                            <span class="alert-badge {{ $alertClass }}">{{ $alertText }}</span>
                                        </td>
                                        <td class="sensor-last-update last-update">{{ $sensor->getTimeSinceLastReading() ?? 'No readings yet' }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr id="no-sensors-row">
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="ph-broadcast me-2"></i>
                                        Connect to MQTT broker to see live sensor data
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
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
                            <small class="text-muted d-block">{{ $device->last_seen_at->diffForHumans() }}</small>
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
