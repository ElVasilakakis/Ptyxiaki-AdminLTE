@extends('layouts.application.app')

@section('pageheader')
    <div class="page-header-content d-lg-flex">
        <div class="d-flex">
            <h4 class="page-title mb-0">
                Connectors - <span class="fw-normal">View,Manage and Create Brokers</span>
            </h4>

            <a href="#page_header"
                class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
                data-bs-toggle="collapse">
                <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
            </a>
        </div>

        <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
            <div class="hstack gap-3 mb-3 mb-lg-0">
                <a href="{{ route('app.mqttbrokers.create') }}" class="btn btn-primary">
                    Create
                    <i class="ph-plus-circle ms-2"></i>
                </a>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
function toggleBrokerStatus(brokerId, isActive) {
    // Show loading state
    const switchElement = document.getElementById(`broker_switch_${brokerId}`);
    const labelElement = switchElement.nextElementSibling;
    const originalText = labelElement.textContent;
    
    labelElement.textContent = 'Updating...';
    switchElement.disabled = true;
    
    // Make AJAX request to update broker status
    fetch(`/app/mqttbrokers/${brokerId}/toggle-status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            status: isActive ? 'active' : 'inactive'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update label text
            labelElement.textContent = isActive ? 'Active' : 'Inactive';
            
            // Show success notification
            showNotification('Broker status updated successfully!', 'success');
        } else {
            // Revert switch state on error
            switchElement.checked = !isActive;
            labelElement.textContent = originalText;
            showNotification('Failed to update broker status. Please try again.', 'error');
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

function testBrokerConnection(brokerId) {
    const button = event.target.closest('button');
    const originalIcon = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="ph-spinner ph-spin"></i>';
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
            
            // Temporarily change button to success state
            button.innerHTML = '<i class="ph-check"></i>';
            button.classList.remove('btn-outline-success');
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = originalIcon;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-success');
            }, 2000);
        } else {
            showNotification(`Connection test failed: ${data.message}`, 'error');
            
            // Temporarily change button to error state
            button.innerHTML = '<i class="ph-x"></i>';
            button.classList.remove('btn-outline-success');
            button.classList.add('btn-danger');
            
            setTimeout(() => {
                button.innerHTML = originalIcon;
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline-success');
            }, 2000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Connection test failed due to network error.', 'error');
        
        // Reset button state
        button.innerHTML = '<i class="ph-x"></i>';
        button.classList.remove('btn-outline-success');
        button.classList.add('btn-danger');
        
        setTimeout(() => {
            button.innerHTML = originalIcon;
            button.classList.remove('btn-danger');
            button.classList.add('btn-outline-success');
        }, 2000);
    })
    .finally(() => {
        button.disabled = false;
    });
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
                <h6 class="mb-0">Your Connectors</h6>
            </div>

                @if($mqttBrokers->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Broker Information</th>
                                    <th>Connection Details</th>
                                    <th>Status</th>
                                    <th>Security</th>
                                    <th>Devices</th>
                                    <th>Created</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mqttBrokers as $broker)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <i class="ph-computer-tower ph-2x text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-body">{{ $broker->name }}</div>
                                                    <div class="text-muted fs-sm">
                                                        <i class="ph-tag me-1"></i>
                                                        <span class="badge bg-info bg-opacity-10 text-info">{{ strtoupper($broker->type) }}</span>
                                                    </div>
                                                    @if($broker->description)
                                                        <div class="text-muted fs-sm mt-1">{{ Str::limit($broker->description, 60) }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-body fw-semibold">{{ $broker->host }}:{{ $broker->port }}</div>
                                            @if($broker->client_id)
                                                <div class="text-muted fs-sm">
                                                    <i class="ph-identification-card me-1"></i>
                                                    {{ $broker->client_id }}
                                                </div>
                                            @endif
                                            @if($broker->websocket_port)
                                                <div class="text-muted fs-sm">
                                                    <i class="ph-globe me-1"></i>
                                                    WebSocket: {{ $broker->websocket_port }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                       {{ $broker->status === 'active' ? 'checked' : '' }} 
                                                       onchange="toggleBrokerStatus({{ $broker->id }}, this.checked)"
                                                       id="broker_switch_{{ $broker->id }}">
                                                <label class="form-check-label text-muted fs-sm" for="broker_switch_{{ $broker->id }}">
                                                    {{ $broker->status === 'active' ? 'Active' : 'Inactive' }}
                                                </label>
                                            </div>
                                            @if($broker->last_connected_at)
                                                <div class="text-success fs-xs mt-1">
                                                    <i class="ph-clock me-1"></i>
                                                    {{ $broker->last_connected_at->diffForHumans() }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($broker->use_ssl)
                                                <div class="d-flex align-items-center mb-1">
                                                    <i class="ph-lock me-2 text-success"></i>
                                                    <span class="badge bg-success bg-opacity-10 text-success">SSL Enabled</span>
                                                </div>
                                                @if($broker->ssl_port)
                                                    <div class="text-muted fs-sm">Port: {{ $broker->ssl_port }}</div>
                                                @endif
                                            @else
                                                <div class="d-flex align-items-center">
                                                    <i class="ph-lock-open me-2 text-secondary"></i>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">No SSL</span>
                                                </div>
                                            @endif
                                            @if($broker->username)
                                                <div class="text-muted fs-sm mt-1">
                                                    <i class="ph-user me-1"></i>
                                                    Auth: Yes
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="ph-cpu me-2 text-info"></i>
                                                <span class="badge bg-info bg-opacity-10 text-info">{{ $broker->devices->count() }} devices</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-muted fs-sm">
                                                <div>{{ $broker->created_at->format('M d, Y') }}</div>
                                                <div class="text-success fs-xs">{{ $broker->created_at->format('H:i') }}</div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <button onclick="testBrokerConnection({{ $broker->id }})" 
                                                        class="btn btn-sm btn-outline-success btn-icon rounded-pill" 
                                                        title="Test Connection"
                                                        data-bs-toggle="tooltip">
                                                    <i class="ph-plug"></i>
                                                </button>
                                                <a href="{{ route('app.mqttbrokers.show', $broker) }}" 
                                                   class="btn btn-sm btn-outline-primary btn-icon rounded-pill" 
                                                   title="View Details"
                                                   data-bs-toggle="tooltip">
                                                    <i class="ph-eye"></i>
                                                </a>
                                                <a href="{{ route('app.mqttbrokers.edit', $broker) }}" 
                                                   class="btn btn-sm btn-outline-warning btn-icon rounded-pill" 
                                                   title="Edit Broker"
                                                   data-bs-toggle="tooltip">
                                                    <i class="ph-pencil"></i>
                                                </a>
                                                <form action="{{ route('app.mqttbrokers.destroy', $broker) }}" 
                                                      method="POST" 
                                                      class="d-inline"
                                                      onsubmit="return confirm('Are you sure you want to delete this Connector? This action cannot be undone and will affect all connected devices.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-outline-danger btn-icon rounded-pill" 
                                                            title="Delete Broker"
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
                            <i class="ph-computer-tower ph-3x text-muted"></i>
                        </div>
                        <h5 class="text-muted">No Connectors found</h5>
                        <p class="text-muted">You haven't created any Connectors yet. Click the "Create" button to add your first broker.</p>
                        <a href="{{ route('app.mqttbrokers.create') }}" class="btn btn-primary">
                            <i class="ph-plus-circle me-2"></i>
                            Create Your First Connector
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
