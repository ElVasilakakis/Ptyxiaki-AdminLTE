<div class="card-header d-flex justify-content-between align-items-center">
    <h6 class="mb-0">LoRaWAN Device: {{ $device->name }}</h6>
    <div class="d-flex align-items-center gap-2">
        <div class="badge bg-info">LoRaWAN Device</div>
        <div id="location-status" class="location-status unknown">Location Status Unknown</div>
    </div>
</div>

<div class="card-body">
    <!-- LoRaWAN Implementation Coming Soon -->
    <div class="text-center py-5">
        <div class="mb-4">
            <i class="ph-broadcast text-primary" style="font-size: 4rem;"></i>
        </div>
        <h5 class="mb-3">LoRaWAN Device Management</h5>
        <p class="text-muted mb-4">
            This section will be implemented for LoRaWAN devices.<br>
            Features will include device status monitoring, sensor data visualization, and network management.
        </p>
        
        <!-- Basic Device Information for now -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Device Information</h6>
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
                                <td><strong>Network Server:</strong></td>
                                <td>{{ $device->mqttBroker->name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Land:</strong></td>
                                <td>{{ $device->land->land_name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Last Seen:</strong></td>
                                <td>{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}</td>
                            </tr>
                        </table>
                    </div>
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
