@extends('layouts.application.app')

@section('pageheader')
    <div class="page-header-content d-lg-flex">
        <div class="d-flex">
            <h4 class="page-title mb-0">
                Connector: {{ $mqttbroker->name }} - <span class="fw-normal">Connection & Device Management</span>
            </h4>

            <a href="#page_header"
                class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
                data-bs-toggle="collapse">
                <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
            </a>
        </div>

        <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
            <div class="hstack gap-3 mb-3 mb-lg-0">
                <button onclick="testBrokerConnection({{ $mqttbroker->id }})"
                        class="btn btn-success"
                        id="test-connection-btn">
                    <i class="ph-plug me-2"></i>
                    Test Connection
                </button>
                <a href="{{ route('app.mqttbrokers.edit', $mqttbroker) }}" class="btn btn-warning">
                    <i class="ph-pencil me-2"></i>
                    Edit
                </a>
            </div>
        </div>
    </div>
@endsection

@section('styles')
<style>
    .status-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 0.5rem;
    }

    .status-active {
        background-color: #10b981;
        animation: pulse 2s infinite;
    }

    .status-inactive {
        background-color: #6b7280;
    }

    .status-error {
        background-color: #ef4444;
    }

    .info-card {
        background: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        height: 100%;
    }

    .info-card h6 {
        color: #374151;
        font-weight: 600;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f3f4f6;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f3f4f6;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: 500;
        color: #6b7280;
        display: flex;
        align-items: center;
    }

    .info-value {
        font-weight: 600;
        color: #374151;
    }

    .badge-custom {
        padding: 0.375rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .pulse {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    .connection-status {
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid;
    }

    .connection-status.connected {
        background-color: #dcfce7;
        color: #166534;
        border-color: #bbf7d0;
    }

    .connection-status.disconnected {
        background-color: #fee2e2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .connection-status.error {
        background-color: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .device-card {
        background: white;
        border-radius: 0.5rem;
        padding: 1rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .device-card:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transform: translateY(-1px);
    }

    .endpoint-code {
        background-color: #f3f4f6;
        padding: 0.5rem;
        border-radius: 0.375rem;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        word-break: break-all;
    }
</style>
@endsection

@section('scripts')
<script>
function testBrokerConnection(brokerId) {
    const button = document.getElementById('test-connection-btn');
    const originalContent = button.innerHTML;

    // Show loading state
    button.innerHTML = '<i class="ph-spinner ph-spin me-2"></i>Testing...';
    button.disabled = true;

    // Make AJAX request to test connection
    fetch(`/app/mqttbrokers/${brokerId}/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`Connection test successful! ${data.message}`, 'success');

            // Update connection status display
            updateConnectionStatus('connected', data.message);

            // Temporarily change button to success state
            button.innerHTML = '<i class="ph-check me-2"></i>Connection Successful';
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-success');

            setTimeout(() => {
                button.innerHTML = originalContent;
                button.classList.remove('btn-outline-success');
                button.classList.add('btn-success');
            }, 3000);
        } else {
            showNotification(`Connection test failed: ${data.message}`, 'error');

            // Update connection status display
            updateConnectionStatus('error', data.message);

            // Temporarily change button to error state
            button.innerHTML = '<i class="ph-x me-2"></i>Connection Failed';
            button.classList.remove('btn-success');
            button.classList.add('btn-danger');

            setTimeout(() => {
                button.innerHTML = originalContent;
                button.classList.remove('btn-danger');
                button.classList.add('btn-success');
            }, 3000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Connection test failed due to network error.', 'error');

        // Reset button state
        button.innerHTML = '<i class="ph-x me-2"></i>Network Error';
        button.classList.remove('btn-success');
        button.classList.add('btn-danger');

        setTimeout(() => {
            button.innerHTML = originalContent;
            button.classList.remove('btn-danger');
            button.classList.add('btn-success');
        }, 3000);
    })
    .finally(() => {
        button.disabled = false;
    });
}

function updateConnectionStatus(status, message) {
    const statusElement = document.getElementById('connection-status');
    if (statusElement) {
        statusElement.className = `connection-status ${status}`;

        let icon = '';
        let text = '';

        switch(status) {
            case 'connected':
                icon = '<i class="ph-check-circle me-2"></i>';
                text = 'Connected - ' + message;
                break;
            case 'error':
                icon = '<i class="ph-x-circle me-2"></i>';
                text = 'Connection Error - ' + message;
                break;
            default:
                icon = '<i class="ph-minus-circle me-2"></i>';
                text = 'Disconnected';
        }

        statusElement.innerHTML = icon + text;
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

        <!-- Connection Status -->
        <div id="connection-status" class="connection-status {{ $mqttbroker->status === 'active' ? 'connected' : ($mqttbroker->status === 'error' ? 'error' : 'disconnected') }}">
            @if($mqttbroker->status === 'active')
                <i class="ph-check-circle me-2"></i>
                Connector is currently active and ready to accept connections
            @elseif($mqttbroker->status === 'error')
                <i class="ph-x-circle me-2"></i>
                Connector connection error - Please check configuration and test connection
            @else
                <i class="ph-minus-circle me-2"></i>
                Connector is currently inactive
            @endif
        </div>

        <div class="row">
            <!-- Basic Information -->
            <div class="col-lg-6 mb-4">
                <div class="info-card">
                    <h6><i class="ph-info me-2"></i>Basic Information</h6>

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-tag me-2"></i>Name
                        </span>
                        <span class="info-value">{{ $mqttbroker->name }}</span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-cpu me-2"></i>Type
                        </span>
                        <span class="badge-custom bg-info bg-opacity-10 text-info">{{ strtoupper($mqttbroker->type) }}</span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-circle me-2"></i>Status
                        </span>
                        <span class="d-flex align-items-center">
                            <span class="status-indicator status-{{ $mqttbroker->status }}"></span>
                            <span class="badge-custom bg-{{ $mqttbroker->status === 'active' ? 'success' : ($mqttbroker->status === 'error' ? 'danger' : 'secondary') }} bg-opacity-10 text-{{ $mqttbroker->status === 'active' ? 'success' : ($mqttbroker->status === 'error' ? 'danger' : 'secondary') }}">
                                {{ ucfirst($mqttbroker->status) }}
                            </span>
                        </span>
                    </div>

                    @if($mqttbroker->description)
                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-note me-2"></i>Description
                        </span>
                        <span class="info-value">{{ $mqttbroker->description }}</span>
                    </div>
                    @endif

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-calendar me-2"></i>Created
                        </span>
                        <span class="info-value">{{ $mqttbroker->created_at->format('M d, Y H:i') }}</span>
                    </div>

                    @if($mqttbroker->last_connected_at)
                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-clock me-2"></i>Last Connected
                        </span>
                        <span class="info-value text-success">{{ $mqttbroker->last_connected_at->diffForHumans() }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Connection Details -->
            <div class="col-lg-6 mb-4">
                <div class="info-card">
                    <h6><i class="ph-globe me-2"></i>Connection Details</h6>

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-globe-hemisphere-west me-2"></i>Host
                        </span>
                        <span class="info-value">{{ $mqttbroker->host }}</span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-plug me-2"></i>Port
                        </span>
                        <span class="info-value">{{ $mqttbroker->port }}</span>
                    </div>

                    @if($mqttbroker->websocket_port)
                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-browser me-2"></i>WebSocket Port
                        </span>
                        <span class="info-value">{{ $mqttbroker->websocket_port }}</span>
                    </div>
                    @endif

                    @if($mqttbroker->path)
                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-path me-2"></i>Path
                        </span>
                        <span class="info-value">{{ $mqttbroker->path }}</span>
                    </div>
                    @endif

                    {{-- <div class="info-item">
                        <span class="info-label">
                            <i class="ph-link me-2"></i>Endpoint
                        </span>
                        <div class="endpoint-code">{{ $mqttbroker->getEndpoint() }}</div>
                    </div>
                     --}}
                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-timer me-2"></i>Keep Alive
                        </span>
                        <span class="info-value">{{ $mqttbroker->keepalive }}s</span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-clock me-2"></i>Timeout
                        </span>
                        <span class="info-value">{{ $mqttbroker->timeout }}s</span>
                    </div>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="col-lg-6 mb-4">
                <div class="info-card">
                    <h6><i class="ph-shield me-2"></i>Security Settings</h6>

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-lock me-2"></i>SSL/TLS
                        </span>
                        <span class="d-flex align-items-center">
                            @if($mqttbroker->use_ssl)
                                <i class="ph-check-circle me-2 text-success"></i>
                                <span class="badge-custom bg-success bg-opacity-10 text-success">Enabled</span>
                                @if($mqttbroker->ssl_port)
                                    <span class="ms-2 text-muted">(Port: {{ $mqttbroker->ssl_port }})</span>
                                @endif
                            @else
                                <i class="ph-x-circle me-2 text-secondary"></i>
                                <span class="badge-custom bg-secondary bg-opacity-10 text-secondary">Disabled</span>
                            @endif
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-user me-2"></i>Authentication
                        </span>
                        <span class="d-flex align-items-center">
                            @if($mqttbroker->username)
                                <i class="ph-check-circle me-2 text-success"></i>
                                <span class="badge-custom bg-success bg-opacity-10 text-success">Enabled</span>
                                <span class="ms-2 text-muted">({{ $mqttbroker->username }})</span>
                            @else
                                <i class="ph-x-circle me-2 text-secondary"></i>
                                <span class="badge-custom bg-secondary bg-opacity-10 text-secondary">No Authentication</span>
                            @endif
                        </span>
                    </div>

                    @if($mqttbroker->client_id)
                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-identification-card me-2"></i>Client ID
                        </span>
                        <span class="info-value">{{ $mqttbroker->client_id }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Advanced Settings -->
            <div class="col-lg-6 mb-4">
                <div class="info-card">
                    <h6><i class="ph-gear me-2"></i>Advanced Settings</h6>

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-arrow-clockwise me-2"></i>Auto Reconnect
                        </span>
                        <span class="d-flex align-items-center">
                            @if($mqttbroker->auto_reconnect)
                                <i class="ph-check-circle me-2 text-success"></i>
                                <span class="badge-custom bg-success bg-opacity-10 text-success">Enabled</span>
                            @else
                                <i class="ph-x-circle me-2 text-secondary"></i>
                                <span class="badge-custom bg-secondary bg-opacity-10 text-secondary">Disabled</span>
                            @endif
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-repeat me-2"></i>Max Reconnect Attempts
                        </span>
                        <span class="info-value">{{ $mqttbroker->max_reconnect_attempts }}</span>
                    </div>

                    @if($mqttbroker->certificates)
                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-certificate me-2"></i>Certificates
                        </span>
                        <span class="badge-custom bg-info bg-opacity-10 text-info">Configured</span>
                    </div>
                    @endif

                    @if($mqttbroker->additional_config)
                    <div class="info-item">
                        <span class="info-label">
                            <i class="ph-code me-2"></i>Additional Config
                        </span>
                        <span class="badge-custom bg-info bg-opacity-10 text-info">Present</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Connected Devices -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="ph-cpu me-2"></i>
                            Connected Devices ({{ $mqttbroker->devices->count() }})
                        </h6>
                    </div>

                    @if($mqttbroker->devices->count() > 0)
                        <div class="card-body">
                            <div class="row">
                                @foreach($mqttbroker->devices as $device)
                                    <div class="col-lg-6 col-xl-4 mb-3">
                                        <div class="device-card">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <h6 class="mb-0">{{ $device->name }}</h6>
                                                <span class="badge bg-{{ $device->status === 'active' ? 'success' : 'secondary' }} bg-opacity-10 text-{{ $device->status === 'active' ? 'success' : 'secondary' }}">
                                                    {{ ucfirst($device->status) }}
                                                </span>
                                            </div>

                                            <div class="text-muted mb-2">
                                                <i class="ph-identification-card me-1"></i>
                                                {{ $device->device_id }}
                                            </div>

                                            @if($device->land)
                                                <div class="text-muted mb-2">
                                                    <i class="ph-map-pin me-1"></i>
                                                    {{ $device->land->name }}
                                                </div>
                                            @endif

                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="ph-calendar me-1"></i>
                                                    {{ $device->created_at->format('M d, Y') }}
                                                </small>
                                                <a href="{{ route('app.devices.show', $device) }}"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="ph-eye me-1"></i>
                                                    View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="card-body text-center py-5">
                            <div class="mb-3">
                                <i class="ph-cpu ph-3x text-muted"></i>
                            </div>
                            <h6 class="text-muted">No devices connected</h6>
                            <p class="text-muted">This connector doesn't have any devices connected to it yet.</p>
                            <a href="{{ route('app.devices.create') }}" class="btn btn-primary">
                                <i class="ph-plus-circle me-2"></i>
                                Add Device
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
