@extends('layouts.application.app')

@section('pageheader')
    <div class="page-header-content d-lg-flex">
        <div class="d-flex">
            <h4 class="page-title mb-0">
                DEVICES - <span class="fw-normal">View, Manage and Create Devices</span>
            </h4>

            <a href="#page_header"
                class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
                data-bs-toggle="collapse">
                <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
            </a>
        </div>

        <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
            <div class="hstack gap-3 mb-3 mb-lg-0">
                <a href="{{ route('app.devices.create') }}" class="btn btn-primary">
                    Create
                    <i class="ph-plus-circle ms-2"></i>
                </a>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
function toggleDeviceStatus(deviceId, isActive) {
    // Show loading state
    const switchElement = document.getElementById(`device_switch_${deviceId}`);
    const labelElement = switchElement.nextElementSibling;
    const originalText = labelElement.textContent;

    labelElement.textContent = 'Updating...';
    switchElement.disabled = true;

    // Make AJAX request to update device status
    fetch(`/app/devices/${deviceId}/toggle-status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            is_active: isActive
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update label text
            labelElement.textContent = isActive ? 'Active' : 'Inactive';

            // Show success notification
            showNotification('Device status updated successfully!', 'success');
        } else {
            // Revert switch state on error
            switchElement.checked = !isActive;
            labelElement.textContent = originalText;
            showNotification('Failed to update device status. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Revert switch state on error
        switchElement.checked = !isActive;
        labelElement.textContent = originalText;
        showNotification('An error occurred. Please try again.', 'error');
    })
    .finally(() => {
        switchElement.disabled = false;
    });
}

function updateDeviceStatus(deviceId, status) {
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;

    // Show loading state
    button.innerHTML = '<i class="ph-spinner ph-spin"></i>';
    button.disabled = true;

    // Make AJAX request to update device status
    fetch(`/app/devices/${deviceId}/update-status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`Device status updated to ${status}!`, 'success');

            // Update the status badge in the table
            const statusBadge = document.querySelector(`#device_status_${deviceId}`);
            if (statusBadge) {
                statusBadge.className = `badge bg-${getStatusColor(status)} bg-opacity-10 text-${getStatusColor(status)}`;
                statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }

            // Temporarily change button to success state
            button.innerHTML = '<i class="ph-check"></i>';
            setTimeout(() => {
                button.innerHTML = originalContent;
            }, 2000);
        } else {
            showNotification(`Failed to update device status: ${data.message}`, 'error');
            button.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while updating device status.', 'error');
        button.innerHTML = originalContent;
    })
    .finally(() => {
        button.disabled = false;
    });
}

function getStatusColor(status) {
    switch(status) {
        case 'online': return 'success';
        case 'offline': return 'secondary';
        case 'maintenance': return 'warning';
        case 'error': return 'danger';
        default: return 'secondary';
    }
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    // Add to page
    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endsection

@section('content')
    <div class="content">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Your Devices</h6>
            </div>

            @if($devices->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Device Information</th>
                                <th>Connection Details</th>
                                <th>Status</th>
                                <th>Sensors</th>
                                <th>Last Activity</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($devices as $device)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                @switch($device->device_type)
                                                    @case('sensor')
                                                        <i class="ph-thermometer ph-2x text-info"></i>
                                                        @break
                                                    @case('actuator')
                                                        <i class="ph-gear ph-2x text-warning"></i>
                                                        @break
                                                    @case('gateway')
                                                        <i class="ph-router ph-2x text-success"></i>
                                                        @break
                                                    @case('controller')
                                                        <i class="ph-cpu ph-2x text-primary"></i>
                                                        @break
                                                    @default
                                                        <i class="ph-device-mobile ph-2x text-secondary"></i>
                                                @endswitch
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-body">{{ $device->name }}</div>
                                                <div class="text-muted fs-sm">
                                                    <i class="ph-identification-card me-1"></i>
                                                    {{ $device->device_id }}
                                                </div>
                                                <div class="text-muted fs-sm mt-1">
                                                    <span class="badge bg-info bg-opacity-10 text-info">{{ ucfirst($device->device_type) }}</span>
                                                </div>
                                                @if($device->description)
                                                    <div class="text-muted fs-sm mt-1">{{ Str::limit($device->description, 50) }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-body fw-semibold">{{ ucfirst($device->connection_type) }} Connection</div>
                                        <div class="text-muted fs-sm">
                                            @if($device->connection_type === 'webhook')
                                                <i class="ph-webhook me-1"></i>
                                                Webhook Protocol
                                            @else
                                                <i class="ph-broadcast me-1"></i>
                                                MQTT Protocol
                                            @endif
                                        </div>
                                        <div class="text-muted fs-sm">
                                            <i class="ph-map-pin me-1"></i>
                                            {{ $device->land->land_name }}
                                        </div>
                                        <div class="text-muted fs-sm mt-1">
                                            <span class="badge bg-success bg-opacity-10 text-success">{{ ucfirst($device->connection_type) }}</span>
                                            @if($device->connection_broker)
                                                <span class="badge bg-info bg-opacity-10 text-info ms-1">{{ ucfirst(str_replace('_', ' ', $device->connection_broker)) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mb-2">
                                            @php
                                                $statusColor = match($device->status) {
                                                    'online' => 'success',
                                                    'offline' => 'secondary',
                                                    'maintenance' => 'warning',
                                                    'error' => 'danger',
                                                    default => 'secondary'
                                                };
                                            @endphp
                                            <span id="device_status_{{ $device->id }}" class="badge bg-{{ $statusColor }} bg-opacity-10 text-{{ $statusColor }}">
                                                {{ ucfirst($device->status) }}
                                            </span>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                   {{ $device->is_active ? 'checked' : '' }}
                                                   onchange="toggleDeviceStatus({{ $device->id }}, this.checked)"
                                                   id="device_switch_{{ $device->id }}">
                                            <label class="form-check-label text-muted fs-sm" for="device_switch_{{ $device->id }}">
                                                {{ $device->is_active ? 'Active' : 'Inactive' }}
                                            </label>
                                        </div>
                                        <div class="mt-2">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success btn-sm"
                                                        onclick="updateDeviceStatus({{ $device->id }}, 'online')"
                                                        title="Set Online" data-bs-toggle="tooltip">
                                                    <i class="ph-check-circle"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                                        onclick="updateDeviceStatus({{ $device->id }}, 'offline')"
                                                        title="Set Offline" data-bs-toggle="tooltip">
                                                    <i class="ph-x-circle"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning btn-sm"
                                                        onclick="updateDeviceStatus({{ $device->id }}, 'maintenance')"
                                                        title="Set Maintenance" data-bs-toggle="tooltip">
                                                    <i class="ph-wrench"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="ph-thermometer me-2 text-info"></i>
                                            <span class="badge bg-info bg-opacity-10 text-info">{{ $device->sensors->count() }} sensors</span>
                                        </div>
                                        @if($device->sensors->count() > 0)
                                            <div class="text-muted fs-xs mt-1">
                                                Active monitoring
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($device->last_seen_at)
                                            <div class="text-muted fs-sm">
                                                <div>{{ $device->last_seen_at->format('M d, Y') }}</div>
                                                <div class="text-success fs-xs">{{ $device->last_seen_at->format('H:i') }}</div>
                                                <div class="text-muted fs-xs">{{ $device->last_seen_at->diffForHumans() }}</div>
                                            </div>
                                        @else
                                            <div class="text-muted fs-sm">
                                                <i class="ph-clock me-1"></i>
                                                Never seen
                                            </div>
                                        @endif
                                        <div class="text-muted fs-xs mt-1">
                                            Created {{ $device->created_at->format('M d, Y') }}
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('app.devices.show', $device) }}"
                                               class="btn btn-sm btn-outline-primary btn-icon rounded-pill"
                                               title="View Details"
                                               data-bs-toggle="tooltip">
                                                <i class="ph-eye"></i>
                                            </a>
                                            <a href="{{ route('app.devices.edit', $device) }}"
                                               class="btn btn-sm btn-outline-warning btn-icon rounded-pill"
                                               title="Edit Device"
                                               data-bs-toggle="tooltip">
                                                <i class="ph-pencil"></i>
                                            </a>
                                            <form action="{{ route('app.devices.destroy', $device) }}"
                                                  method="POST"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this device? This action cannot be undone and will affect all connected sensors.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger btn-icon rounded-pill"
                                                        title="Delete Device"
                                                        data-bs-toggle="tooltip">
                                                    <i class="ph-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="ph-device-mobile ph-3x text-muted"></i>
                    </div>
                    <h5 class="text-muted">No devices found</h5>
                    <p class="text-muted">You haven't created any devices yet. Click the "Create" button to add your first device.</p>
                    <a href="{{ route('app.devices.create') }}" class="btn btn-primary">
                        <i class="ph-plus-circle me-2"></i>
                        Create Your First Device
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection
