@extends('layouts.application.app')

@section('pageheader')
    <div class="page-header-content d-lg-flex">
        <div class="d-flex">
            <h4 class="page-title mb-0">
                Lands - <span class="fw-normal">View,Manage and Create Lands</span>
            </h4>

            <a href="#page_header"
                class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
                data-bs-toggle="collapse">
                <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
            </a>
        </div>

        <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
            <div class="hstack gap-3 mb-3 mb-lg-0">
                <a href="{{route('app.lands.create')}}" type="button" class="btn btn-primary">
                    Create
                    <i class="ph-plus-circle ms-2"></i>
                </a>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function toggleLandStatus(landId, isEnabled) {
    // Show loading state
    const switchElement = document.getElementById(`land_switch_${landId}`);
    const labelElement = switchElement.nextElementSibling;
    const originalText = labelElement.textContent;
    
    labelElement.textContent = 'Updating...';
    switchElement.disabled = true;
    
    // Make AJAX request to update land status
    fetch(`/app/lands/${landId}/toggle-status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            enabled: isEnabled
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update label text
            labelElement.textContent = isEnabled ? 'Enabled' : 'Disabled';
            
            // Show success notification
            showNotification('Land status updated successfully!', 'success');
        } else {
            // Revert switch state on error
            switchElement.checked = !isEnabled;
            labelElement.textContent = originalText;
            showNotification('Failed to update land status. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Revert switch state on error
        switchElement.checked = !isEnabled;
        labelElement.textContent = originalText;
        showNotification('An error occurred. Please try again.', 'error');
    })
    .finally(() => {
        switchElement.disabled = false;
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
@endpush




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
                <h6 class="mb-0">Your Lands</h6>
            </div>

            
                @if($lands->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Land Information</th>
                                    <th>Color</th>
                                    <th>Status</th>
                                    <th>Devices</th>
                                    <th>Characteristics</th>
                                    <th>Created</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                        <tbody>
                            @foreach($lands as $land)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="ph-map-pin-line ph-2x text-primary"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-body">{{ $land->land_name }}</div>
                                                <div class="text-muted fs-sm">
                                                    <i class="ph-calendar-blank me-1"></i>
                                                    Created {{ $land->created_at->diffForHumans() }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle me-2 border border-2" 
                                                 style="width: 24px; height: 24px; background-color: {{ $land->color ?? '#3498db' }};"></div>
                                            <code class="text-muted fs-sm">{{ $land->color ?? '#3498db' }}</code>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   {{ $land->enabled ? 'checked' : '' }} 
                                                   onchange="toggleLandStatus({{ $land->id }}, this.checked)"
                                                   id="land_switch_{{ $land->id }}">
                                            <label class="form-check-label text-muted fs-sm" for="land_switch_{{ $land->id }}">
                                                {{ $land->enabled ? 'Enabled' : 'Disabled' }}
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="ph-cpu me-2 text-info"></i>
                                            <span class="badge bg-info bg-opacity-10 text-info">{{ $land->devices->count() }} devices</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if($land->data && count($land->data) > 0)
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($land->data as $key => $value)
                                                    <span class="badge bg-light text-dark border">
                                                        <strong>{{ $key }}:</strong> {{ Str::limit($value, 15) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted fs-sm">
                                                <i class="ph-minus-circle me-1"></i>No characteristics
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="text-muted fs-sm">
                                            <div>{{ $land->created_at->format('M d, Y') }}</div>
                                            <div class="text-success fs-xs">{{ $land->created_at->format('H:i') }}</div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('app.lands.show', $land) }}" 
                                               class="btn btn-sm btn-outline-primary btn-icon rounded-pill" 
                                               title="View Details"
                                               data-bs-toggle="tooltip">
                                                <i class="ph-eye"></i>
                                            </a>
                                            <a href="{{ route('app.lands.edit', $land) }}" 
                                               class="btn btn-sm btn-outline-warning btn-icon rounded-pill" 
                                               title="Edit Land"
                                               data-bs-toggle="tooltip">
                                                <i class="ph-pencil"></i>
                                            </a>
                                            <form action="{{ route('app.lands.destroy', $land) }}" 
                                                  method="POST" 
                                                  class="d-inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this land? This action cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="btn btn-sm btn-outline-danger btn-icon rounded-pill" 
                                                        title="Delete Land"
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
                            <i class="ph-map-pin-line ph-3x text-muted"></i>
                        </div>
                        <h5 class="text-muted">No lands found</h5>
                        <p class="text-muted">You haven't created any lands yet. Click the "Create" button to add your first land.</p>
                        <a href="{{ route('app.lands.create') }}" class="btn btn-primary">
                            <i class="ph-plus-circle me-2"></i>
                            Create Your First Land
                        </a>
                    </div>
                @endif
      
        </div>
    </div>
@endsection
