@extends('layouts.application.app')

@section('content')
    <div class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Sensors Management</h6>
                    </div>

                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        @if ($sensors->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
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
                                            <tr data-sensor-id="{{ $sensor->id }}">
                                                <td>
                                                    <span class="badge bg-info">{{ ucfirst($sensor->sensor_type) }}</span>
                                                </td>
                                                <td>{{ $sensor->sensor_name ?: 'Unnamed' }}</td>
                                                <td>{{ $sensor->device->name }}</td>
                                                <td>{{ $sensor->device->land->land_name }}</td>
                                                <td>
                                                    @if ($sensor->value !== null)
                                                        @if (is_numeric($sensor->value))
                                                            {{ number_format((float) $sensor->value, 2) }}
                                                        @else
                                                            {{ is_array($sensor->value) || is_object($sensor->value) ? json_encode($sensor->value) : $sensor->value }}
                                                        @endif
                                                    @else
                                                        <span class="text-muted">No reading</span>
                                                    @endif
                                                </td>
                                                <td>{{ $sensor->unit ?: '-' }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $sensor->enabled ? 'success' : 'secondary' }}">
                                                        {{ $sensor->enabled ? 'Enabled' : 'Disabled' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if ($sensor->alert_enabled)
                                                        @php
                                                            $alertStatus = $sensor->getAlertStatus();
                                                        @endphp
                                                        <span class="badge bg-{{ $alertStatus === 'normal' ? 'success' : ($alertStatus === 'high' ? 'danger' : 'warning') }}">
                                                            @if ($alertStatus === 'normal')
                                                                Normal
                                                            @elseif ($alertStatus === 'high')
                                                                High Alert
                                                            @elseif ($alertStatus === 'low')
                                                                Low Alert
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary">Disabled</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($sensor->reading_timestamp)
                                                        <span title="{{ $sensor->reading_timestamp->format('M d, Y H:i:s') }}">
                                                            {{ $sensor->reading_timestamp->diffForHumans() }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">Never</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <a href="{{ route('app.sensors.edit', $sensor) }}" 
                                                           class="btn btn-outline-primary btn-sm">
                                                            <i class="ph-pencil me-1"></i>Edit
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="ph-gauge ph-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No sensors found</h5>
                                <p class="text-muted">Sensors will appear here automatically when devices start sending data.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let updateInterval;
    let isUpdating = false;
    
    // Add live update indicator
    const cardHeader = document.querySelector('.card-header h6');
    const liveIndicator = document.createElement('span');
    liveIndicator.innerHTML = '<i class="ph-broadcast text-success me-1"></i>Live Updates';
    liveIndicator.className = 'badge bg-success-subtle text-success ms-2';
    liveIndicator.id = 'live-indicator';
    cardHeader.appendChild(liveIndicator);
    
    // Function to update sensor data
    async function updateSensorData() {
        if (isUpdating) return;
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
                        valueCell.innerHTML = sensor.formatted_value;
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
                    unitCell.textContent = sensor.unit;
                }
                
                // Update status
                const statusCell = row.children[6];
                if (statusCell) {
                    const badge = statusCell.querySelector('.badge');
                    if (badge) {
                        badge.className = `badge bg-${sensor.enabled ? 'success' : 'secondary'}`;
                        badge.textContent = sensor.enabled ? 'Enabled' : 'Disabled';
                    }
                }
                
                // Update alerts
                const alertCell = row.children[7];
                if (alertCell && sensor.alert_enabled) {
                    const badge = alertCell.querySelector('.badge');
                    if (badge) {
                        let alertClass = 'secondary';
                        let alertText = 'Disabled';
                        
                        if (sensor.alert_enabled) {
                            switch (sensor.alert_status) {
                                case 'normal':
                                    alertClass = 'success';
                                    alertText = 'Normal';
                                    break;
                                case 'high':
                                    alertClass = 'danger';
                                    alertText = 'High Alert';
                                    break;
                                case 'low':
                                    alertClass = 'warning';
                                    alertText = 'Low Alert';
                                    break;
                            }
                        }
                        
                        badge.className = `badge bg-${alertClass}`;
                        badge.textContent = alertText;
                    }
                }
                
                // Update last reading
                const readingCell = row.children[8];
                if (readingCell) {
                    const span = readingCell.querySelector('span');
                    if (span && sensor.reading_timestamp) {
                        span.textContent = sensor.reading_human;
                        span.title = sensor.reading_formatted;
                    } else if (span) {
                        span.innerHTML = '<span class="text-muted">Never</span>';
                    }
                }
            }
        });
    }
    
    // Function to update live indicator
    function updateLiveIndicator(isConnected) {
        const indicator = document.getElementById('live-indicator');
        if (indicator) {
            if (isConnected) {
                indicator.innerHTML = '<i class="ph-broadcast text-success me-1"></i>Live Updates';
                indicator.className = 'badge bg-success-subtle text-success ms-2';
            } else {
                indicator.innerHTML = '<i class="ph-broadcast text-danger me-1"></i>Connection Error';
                indicator.className = 'badge bg-danger-subtle text-danger ms-2';
            }
        }
    }
    
    // Start live updates
    function startLiveUpdates() {
        // Initial update
        updateSensorData();
        
        // Set up interval for updates every 3 seconds
        updateInterval = setInterval(updateSensorData, 3000);
    }
    
    // Stop live updates
    function stopLiveUpdates() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }
    
    // Start live updates when page loads
    startLiveUpdates();
    
    // Stop updates when page is hidden/unloaded
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopLiveUpdates();
        } else {
            startLiveUpdates();
        }
    });
    
    window.addEventListener('beforeunload', stopLiveUpdates);
});
</script>
@endpush
