@extends('layouts.application.app')

@section('pageheader')
    <div class="page-header-content d-lg-flex">
        <div class="d-flex">
            <h4 class="page-title mb-0">
                SENSORS - <span class="fw-normal">Monitor and Manage All Sensors</span>
            </h4>

            <a href="#page_header"
                class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
                data-bs-toggle="collapse">
                <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
            </a>
        </div>

        <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
            <div class="hstack gap-3 mb-3 mb-lg-0">
                <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                    <i class="ph-x me-2"></i>Clear Filters
                </button>
                <button type="button" class="btn btn-outline-info" onclick="toggleLiveUpdates()" id="live-toggle-btn">
                    <i class="ph-pause me-2"></i>Pause Live Updates
                </button>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="content">
        <!-- Filters Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="ph-funnel me-2"></i>Filters & Search
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('app.sensors.index') }}" id="filter-form">
                            <div class="row g-3">
                                <!-- Search -->
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ph-magnifying-glass"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control" 
                                               name="search" 
                                               value="{{ request('search') }}" 
                                               placeholder="Search sensors, devices...">
                                    </div>
                                </div>

                                <!-- Device Filter -->
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Device</label>
                                    <select class="form-select" name="device_id">
                                        <option value="">All Devices</option>
                                        @foreach($devices as $device)
                                            <option value="{{ $device->id }}" 
                                                    {{ request('device_id') == $device->id ? 'selected' : '' }}>
                                                {{ $device->name }} ({{ $device->land->land_name }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Sensor Type Filter -->
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Sensor Type</label>
                                    <select class="form-select" name="sensor_type">
                                        <option value="">All Types</option>
                                        @foreach($sensorTypes as $type)
                                            <option value="{{ $type }}" 
                                                    {{ request('sensor_type') == $type ? 'selected' : '' }}>
                                                {{ ucfirst($type) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Status Filter -->
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <option value="enabled" {{ request('status') == 'enabled' ? 'selected' : '' }}>Enabled</option>
                                        <option value="disabled" {{ request('status') == 'disabled' ? 'selected' : '' }}>Disabled</option>
                                    </select>
                                </div>

                                <!-- Alert Status Filter -->
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Alert Status</label>
                                    <select class="form-select" name="alert_status">
                                        <option value="">All Alerts</option>
                                        <option value="all" {{ request('alert_status') == 'all' ? 'selected' : '' }}>Alert Enabled</option>
                                        <option value="normal" {{ request('alert_status') == 'normal' ? 'selected' : '' }}>Normal</option>
                                        <option value="high" {{ request('alert_status') == 'high' ? 'selected' : '' }}>High Alert</option>
                                        <option value="low" {{ request('alert_status') == 'low' ? 'selected' : '' }}>Low Alert</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ph-funnel me-2"></i>Apply Filters
                                    </button>
                                    <a href="{{ route('app.sensors.index') }}" class="btn btn-outline-secondary ms-2">
                                        <i class="ph-arrow-clockwise me-2"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Summary -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <h6 class="mb-0">
                            <i class="ph-gauge me-2"></i>
                            Sensors ({{ $sensors->total() }} total)
                        </h6>
                        <span class="badge bg-success" id="live-indicator">
                            <i class="ph-broadcast me-1"></i>Live Updates
                        </span>
                    </div>
                    <div class="text-muted">
                        Showing {{ $sensors->firstItem() ?? 0 }} to {{ $sensors->lastItem() ?? 0 }} of {{ $sensors->total() }} results
                    </div>
                </div>
            </div>
        </div>

        <!-- Sensors Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        @if ($sensors->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Sensor Type</th>
                                            <th>Name</th>
                                            <th>Device</th>
                                            <th>Land</th>
                                            <th>Current Value</th>
                                            <th>Unit</th>
                                            <th>Status</th>
                                            <th>Alerts</th>
                                            <th>Last Reading</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sensors-table-body">
                                        @foreach ($sensors as $sensor)
                                            @php
                                                $alertStatus = $sensor->getAlertStatus();
                                                $shouldShow = true;
                                                
                                                // Apply alert status filter if specified
                                                if (request('alert_status') && request('alert_status') !== 'all') {
                                                    if (!$sensor->alert_enabled || $alertStatus !== request('alert_status')) {
                                                        $shouldShow = false;
                                                    }
                                                }
                                            @endphp
                                            
                                            @if($shouldShow)
                                                <tr data-sensor-id="{{ $sensor->id }}">
                                                    <td>
                                                        <span class="badge bg-info bg-opacity-10 text-info">
                                                            <i class="ph-gauge me-1"></i>
                                                            {{ ucfirst($sensor->sensor_type) }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-medium">{{ $sensor->sensor_name ?: 'Unnamed' }}</div>
                                                        @if($sensor->description)
                                                            <small class="text-muted">{{ Str::limit($sensor->description, 30) }}</small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="ph-device-mobile me-2 text-primary"></i>
                                                            <div>
                                                                <div class="fw-medium">{{ $sensor->device->name }}</div>
                                                                <small class="text-muted">{{ $sensor->device->device_id }}</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="ph-map-pin me-2 text-success"></i>
                                                            <span class="fw-medium">{{ $sensor->device->land->land_name }}</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if ($sensor->value !== null)
                                                            @if (is_numeric($sensor->value))
                                                                <span class="fw-medium">{{ number_format((float) $sensor->value, 2) }}</span>
                                                            @else
                                                                <span class="fw-medium">{{ is_array($sensor->value) || is_object($sensor->value) ? json_encode($sensor->value) : $sensor->value }}</span>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">No reading</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="text-muted">{{ $sensor->unit ?: '-' }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{{ $sensor->enabled ? 'success' : 'secondary' }} bg-opacity-10 text-{{ $sensor->enabled ? 'success' : 'secondary' }}">
                                                            <i class="ph-{{ $sensor->enabled ? 'check-circle' : 'x-circle' }} me-1"></i>
                                                            {{ $sensor->enabled ? 'Enabled' : 'Disabled' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if ($sensor->alert_enabled)
                                                            <span class="badge bg-{{ $alertStatus === 'normal' ? 'success' : ($alertStatus === 'high' ? 'danger' : 'warning') }} bg-opacity-10 text-{{ $alertStatus === 'normal' ? 'success' : ($alertStatus === 'high' ? 'danger' : 'warning') }}">
                                                                <i class="ph-{{ $alertStatus === 'normal' ? 'check-circle' : 'warning-circle' }} me-1"></i>
                                                                @if ($alertStatus === 'normal')
                                                                    Normal
                                                                @elseif ($alertStatus === 'high')
                                                                    High Alert
                                                                @elseif ($alertStatus === 'low')
                                                                    Low Alert
                                                                @endif
                                                            </span>
                                                        @else
                                                            <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                                                <i class="ph-bell-slash me-1"></i>
                                                                Disabled
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($sensor->reading_timestamp)
                                                            <div class="d-flex align-items-center">
                                                                <i class="ph-clock me-2 text-muted"></i>
                                                                <div>
                                                                    <div class="fw-medium">{{ $sensor->reading_timestamp->diffForHumans() }}</div>
                                                                    <small class="text-muted">{{ $sensor->reading_timestamp->format('M d, Y H:i:s') }}</small>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <span class="text-muted">
                                                                <i class="ph-clock me-1"></i>
                                                                Never
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <a href="{{ route('app.sensors.edit', $sensor) }}" 
                                                               class="btn btn-outline-primary btn-sm" 
                                                               title="Edit Sensor"
                                                               data-bs-toggle="tooltip">
                                                                <i class="ph-pencil"></i>
                                                            </a>
                                                            <a href="{{ route('app.devices.show', $sensor->device) }}" 
                                                               class="btn btn-outline-info btn-sm" 
                                                               title="View Device"
                                                               data-bs-toggle="tooltip">
                                                                <i class="ph-eye"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div class="text-muted">
                                    Showing {{ $sensors->firstItem() ?? 0 }} to {{ $sensors->lastItem() ?? 0 }} of {{ $sensors->total() }} results
                                </div>
                                <div>
                                    {{ $sensors->appends(request()->query())->links() }}
                                </div>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="ph-gauge ph-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No sensors found</h5>
                                @if(request()->hasAny(['search', 'device_id', 'sensor_type', 'status', 'alert_status']))
                                    <p class="text-muted">No sensors match your current filters. Try adjusting your search criteria.</p>
                                    <a href="{{ route('app.sensors.index') }}" class="btn btn-outline-primary">
                                        <i class="ph-arrow-clockwise me-2"></i>Clear All Filters
                                    </a>
                                @else
                                    <p class="text-muted">Sensors will appear here automatically when devices start sending data.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .table-success {
        background-color: #d1e7dd !important;
        transition: background-color 0.5s ease;
    }
    
    .live-indicator .badge {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }
    
    .sensor-updated {
        background-color: #e8f5e8 !important;
        transition: background-color 0.5s ease;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let updateInterval;
    let isUpdating = false;
    let liveUpdatesEnabled = true;
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Function to update sensor data
    async function updateSensorData() {
        if (isUpdating || !liveUpdatesEnabled) return;
        isUpdating = true;
        
        try {
            const response = await fetch('/api/sensors/live', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Authorization': 'Bearer ' + (localStorage.getItem('api_token') || '')
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.sensors) {
                updateSensorTable(data.sensors);
                updateLiveIndicator(true);
            }
        } catch (error) {
            console.error('Error fetching sensor data:', error);
            updateLiveIndicator(false);
        } finally {
            isUpdating = false;
        }
    }
    
    // Function to update the sensor table
    function updateSensorTable(sensors) {
        const tableBody = document.getElementById('sensors-table-body');
        if (!tableBody) return;
        
        sensors.forEach(sensor => {
            const row = document.querySelector(`tr[data-sensor-id="${sensor.id}"]`);
            if (row) {
                // Update sensor value
                const valueCell = row.children[4];
                if (valueCell) {
                    if (sensor.value !== null) {
                        valueCell.innerHTML = `<span class="fw-medium">${sensor.formatted_value}</span>`;
                        // Add flash effect for updated values
                        valueCell.classList.add('table-success');
                        setTimeout(() => valueCell.classList.remove('table-success'), 1000);
                    } else {
                        valueCell.innerHTML = '<span class="text-muted">No reading</span>';
                    }
                }
                
                // Update unit
                const unitCell = row.children[5];
                if (unitCell) {
                    unitCell.innerHTML = `<span class="text-muted">${sensor.unit}</span>`;
                }
                
                // Update status
                const statusCell = row.children[6];
                if (statusCell) {
                    const enabled = sensor.enabled;
                    statusCell.innerHTML = `
                        <span class="badge bg-${enabled ? 'success' : 'secondary'} bg-opacity-10 text-${enabled ? 'success' : 'secondary'}">
                            <i class="ph-${enabled ? 'check-circle' : 'x-circle'} me-1"></i>
                            ${enabled ? 'Enabled' : 'Disabled'}
                        </span>
                    `;
                }
                
                // Update alerts
                const alertCell = row.children[7];
                if (alertCell && sensor.alert_enabled) {
                    let alertClass = 'secondary';
                    let alertText = 'Disabled';
                    let alertIcon = 'bell-slash';
                    
                    if (sensor.alert_enabled) {
                        switch (sensor.alert_status) {
                            case 'normal':
                                alertClass = 'success';
                                alertText = 'Normal';
                                alertIcon = 'check-circle';
                                break;
                            case 'high':
                                alertClass = 'danger';
                                alertText = 'High Alert';
                                alertIcon = 'warning-circle';
                                break;
                            case 'low':
                                alertClass = 'warning';
                                alertText = 'Low Alert';
                                alertIcon = 'warning-circle';
                                break;
                        }
                    }
                    
                    alertCell.innerHTML = `
                        <span class="badge bg-${alertClass} bg-opacity-10 text-${alertClass}">
                            <i class="ph-${alertIcon} me-1"></i>
                            ${alertText}
                        </span>
                    `;
                }
                
                // Update last reading
                const readingCell = row.children[8];
                if (readingCell && sensor.reading_timestamp) {
                    readingCell.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="ph-clock me-2 text-muted"></i>
                            <div>
                                <div class="fw-medium">${sensor.reading_human}</div>
                                <small class="text-muted">${sensor.reading_formatted}</small>
                            </div>
                        </div>
                    `;
                } else if (readingCell) {
                    readingCell.innerHTML = `
                        <span class="text-muted">
                            <i class="ph-clock me-1"></i>
                            Never
                        </span>
                    `;
                }
                
                // Add visual feedback
                row.classList.add('sensor-updated');
                setTimeout(() => row.classList.remove('sensor-updated'), 2000);
            }
        });
    }
    
    // Function to update live indicator
    function updateLiveIndicator(isConnected) {
        const indicator = document.getElementById('live-indicator');
        if (indicator) {
            if (liveUpdatesEnabled && isConnected) {
                indicator.innerHTML = '<i class="ph-broadcast me-1"></i>Live Updates';
                indicator.className = 'badge bg-success';
            } else if (liveUpdatesEnabled && !isConnected) {
                indicator.innerHTML = '<i class="ph-broadcast me-1"></i>Connection Error';
                indicator.className = 'badge bg-danger';
            } else {
                indicator.innerHTML = '<i class="ph-pause me-1"></i>Updates Paused';
                indicator.className = 'badge bg-secondary';
            }
        }
    }
    
    // Start live updates
    function startLiveUpdates() {
        if (liveUpdatesEnabled) {
            // Initial update
            updateSensorData();
            
            // Set up interval for updates every 3 seconds
            updateInterval = setInterval(updateSensorData, 3000);
        }
    }
    
    // Stop live updates
    function stopLiveUpdates() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }
    
    // Toggle live updates
    window.toggleLiveUpdates = function() {
        const btn = document.getElementById('live-toggle-btn');
        liveUpdatesEnabled = !liveUpdatesEnabled;
        
        if (liveUpdatesEnabled) {
            btn.innerHTML = '<i class="ph-pause me-2"></i>Pause Live Updates';
            btn.className = 'btn btn-outline-info';
            startLiveUpdates();
        } else {
            btn.innerHTML = '<i class="ph-play me-2"></i>Resume Live Updates';
            btn.className = 'btn btn-outline-success';
            stopLiveUpdates();
        }
        
        updateLiveIndicator(true);
    };
    
    // Clear filters function
    window.clearFilters = function() {
        window.location.href = '{{ route("app.sensors.index") }}';
    };
    
    // Start live updates when page loads
    startLiveUpdates();
    
    // Stop updates when page is hidden/unloaded
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopLiveUpdates();
        } else if (liveUpdatesEnabled) {
            startLiveUpdates();
        }
    });
    
    window.addEventListener('beforeunload', stopLiveUpdates);
});
</script>
@endpush
