@extends('layouts.application.app')

@section('scripts')
<script>
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

// Auto-generate device ID based on name and type
function generateDeviceId() {
    const name = document.querySelector('input[name="name"]').value;
    const type = document.querySelector('select[name="device_type"]').value;
    
    if (name && type) {
        const cleanName = name.toLowerCase().replace(/[^a-z0-9]/g, '_');
        const timestamp = Date.now().toString().slice(-4);
        const deviceId = `${type}_${cleanName}_${timestamp}`;
        document.querySelector('input[name="device_id"]').value = deviceId;
    }
}

// Validate configuration JSON
function validateConfiguration() {
    const configInput = document.querySelector('textarea[name="configuration"]');
    const configValue = configInput.value.trim();
    
    if (configValue) {
        try {
            JSON.parse(configValue);
            configInput.classList.remove('is-invalid');
            configInput.classList.add('is-valid');
            return true;
        } catch (e) {
            configInput.classList.remove('is-valid');
            configInput.classList.add('is-invalid');
            return false;
        }
    } else {
        configInput.classList.remove('is-invalid', 'is-valid');
        return true;
    }
}

let topicIndex = 1;

// Initialize form interactions
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate device ID when name or type changes
    document.querySelector('input[name="name"]').addEventListener('input', generateDeviceId);
    document.querySelector('select[name="device_type"]').addEventListener('change', generateDeviceId);
    
    // Set default values
    document.querySelector('select[name="status"]').value = 'offline';
    document.querySelector('input[name="is_active"]').checked = true;
    
    // Initialize topics repeater functionality
    initializeTopicsRepeater();
});

// Topics repeater functionality
function initializeTopicsRepeater() {
    // Add topic button
    document.getElementById('add-topic').addEventListener('click', function() {
        const repeater = document.getElementById('topics-repeater');
        const newItem = createTopicItem(topicIndex);
        repeater.appendChild(newItem);
        topicIndex++;
    });

    // Handle remove buttons (using event delegation)
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-topic')) {
            const item = e.target.closest('.repeater-item');
            if (item && document.querySelectorAll('#topics-repeater .repeater-item').length > 1) {
                item.remove();
                updateTopicIndexes();
            }
        }
    });
}

function createTopicItem(index) {
    const div = document.createElement('div');
    div.className = 'repeater-item mb-3';
    div.style.border = '1px solid #ddd';
    div.style.borderRadius = '0.375rem';
    div.style.backgroundColor = '#f8f9fa';
    div.style.padding = '1rem';
    
    div.innerHTML = `
        <div class="d-flex gap-3 align-items-center">
            <div class="flex-fill">
                <input type="text" name="topics[${index}]" class="form-control" placeholder="Enter MQTT topic (e.g., sensors/temperature)">
            </div>
            <div style="min-width: 100px;">
                <button type="button" class="btn btn-danger btn-sm remove-topic">
                    <i class="ph-trash"></i> Remove
                </button>
            </div>
        </div>
    `;
    
    return div;
}

// Update indexes after removing items
function updateTopicIndexes() {
    const items = document.querySelectorAll('#topics-repeater .repeater-item');
    items.forEach((item, index) => {
        const input = item.querySelector('input[name*="topics["]');
        if (input) {
            input.setAttribute('name', `topics[${index}]`);
        }
    });
    
    topicIndex = items.length;
}
</script>
@endsection

@section('content')
    <div class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Add New Device</h6>
                    </div>

                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('app.devices.store') }}" method="POST">
                            @csrf
                            
                            <!-- Basic Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Basic Information</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Device Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                           placeholder="Enter device name" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Device ID <span class="text-danger">*</span></label>
                                    <input type="text" name="device_id" class="form-control @error('device_id') is-invalid @enderror" 
                                           placeholder="Unique device identifier" value="{{ old('device_id') }}" required>
                                    <small class="form-text text-muted">Unique identifier for MQTT communication</small>
                                    @error('device_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Device Type <span class="text-danger">*</span></label>
                                    <select name="device_type" class="form-select @error('device_type') is-invalid @enderror" required>
                                        <option value="">Select device type</option>
                                        <option value="sensor" {{ old('device_type') == 'sensor' ? 'selected' : '' }}>Sensor</option>
                                        <option value="actuator" {{ old('device_type') == 'actuator' ? 'selected' : '' }}>Actuator</option>
                                        <option value="gateway" {{ old('device_type') == 'gateway' ? 'selected' : '' }}>Gateway</option>
                                        <option value="controller" {{ old('device_type') == 'controller' ? 'selected' : '' }}>Controller</option>
                                    </select>
                                    @error('device_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                        <option value="offline" {{ old('status', 'offline') == 'offline' ? 'selected' : '' }}>Offline</option>
                                        <option value="online" {{ old('status') == 'online' ? 'selected' : '' }}>Online</option>
                                        <option value="maintenance" {{ old('status') == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                        <option value="error" {{ old('status') == 'error' ? 'selected' : '' }}>Error</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Connection Settings -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Connection Settings</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">MQTT Broker <span class="text-danger">*</span></label>
                                    <select name="mqtt_broker_id" class="form-select @error('mqtt_broker_id') is-invalid @enderror" required>
                                        <option value="">Select MQTT broker</option>
                                        @foreach($mqttBrokers as $broker)
                                            <option value="{{ $broker->id }}" {{ old('mqtt_broker_id') == $broker->id ? 'selected' : '' }}>
                                                {{ $broker->name }} ({{ $broker->host }}:{{ $broker->port }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('mqtt_broker_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if($mqttBrokers->count() == 0)
                                        <small class="form-text text-warning">
                                            <i class="ph-warning me-1"></i>
                                            No active MQTT brokers found. <a href="{{ route('app.mqttbrokers.create') }}">Create one first</a>.
                                        </small>
                                    @endif
                                </div>

                            </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Land <span class="text-danger">*</span></label>
                                    <select name="land_id" class="form-select @error('land_id') is-invalid @enderror" required>
                                        <option value="">Select land</option>
                                        @foreach($lands as $land)
                                            <option value="{{ $land->id }}" {{ old('land_id') == $land->id ? 'selected' : '' }}>
                                                {{ $land->land_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('land_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if($lands->count() == 0)
                                        <small class="form-text text-warning">
                                            <i class="ph-warning me-1"></i>
                                            No active lands found. <a href="{{ route('app.lands.create') }}">Create one first</a>.
                                        </small>
                                    @endif
                                </div>

                                <!-- MQTT Topics (Repeater) -->
                                <div class="col-12 mb-3">
                                    <label class="form-label">MQTT Topics</label>
                                    <div class="repeater" id="topics-repeater">
                                        <div class="repeater-item mb-3" style="border: 1px solid #ddd; border-radius: 0.375rem; background-color: #f8f9fa; padding: 1rem;">
                                            <div class="d-flex gap-3 align-items-center">
                                                <div class="flex-fill">
                                                    <input type="text" name="topics[0]" class="form-control" placeholder="Enter MQTT topic (e.g., sensors/temperature)">
                                                </div>
                                                <div style="min-width: 100px;">
                                                    <button type="button" class="btn btn-danger btn-sm remove-topic">
                                                        <i class="ph-trash"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-success btn-sm" id="add-topic">
                                        <i class="ph-plus"></i> Add Topic
                                    </button>
                                    <small class="form-text text-muted d-block mt-2">Add MQTT topics this device subscribes to or publishes on</small>
                                    @error('topics')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Device Settings -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Device Settings</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Installation Date</label>
                                    <input type="date" name="installed_at" class="form-control @error('installed_at') is-invalid @enderror" 
                                           value="{{ old('installed_at') }}">
                                    @error('installed_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="form-check mt-4">
                                        <input type="checkbox" name="is_active" class="form-check-input" 
                                               {{ old('is_active', true) ? 'checked' : '' }} value="1" id="is_active">
                                        <label class="form-check-label" for="is_active">
                                            Device is Active
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                              rows="3" placeholder="Enter device description">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('app.devices.index') }}" class="btn btn-outline-secondary">
                                    <i class="ph-arrow-left me-2"></i>Back to Devices
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    Save Device <i class="ph-floppy-disk ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
