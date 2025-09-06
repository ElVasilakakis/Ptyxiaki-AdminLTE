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

// Topics Repeater Functionality
let topicIndex = {{ $device->topics && is_array($device->topics) ? count($device->topics) : 1 }};

function addTopicField() {
    const repeater = document.getElementById('topics-repeater');
    const newItem = document.createElement('div');
    newItem.className = 'repeater-item mb-3';
    newItem.style.cssText = 'border: 1px solid #ddd; border-radius: 0.375rem; background-color: #f8f9fa; padding: 1rem;';
    
    newItem.innerHTML = `
        <div class="d-flex gap-3 align-items-center">
            <div class="flex-fill">
                <input type="text" name="topics[${topicIndex}]" class="form-control" placeholder="Enter MQTT topic (e.g., sensors/temperature)">
            </div>
            <div style="min-width: 100px;">
                <button type="button" class="btn btn-danger btn-sm remove-topic">
                    <i class="ph-trash"></i> Remove
                </button>
            </div>
        </div>
    `;
    
    repeater.appendChild(newItem);
    topicIndex++;
    
    // Add event listener to the new remove button
    newItem.querySelector('.remove-topic').addEventListener('click', function() {
        removeTopicField(this);
    });
}

function removeTopicField(button) {
    const repeaterItems = document.querySelectorAll('.repeater-item');
    if (repeaterItems.length > 1) {
        button.closest('.repeater-item').remove();
        reindexTopics();
    } else {
        showNotification('At least one topic is required', 'error');
    }
}

function reindexTopics() {
    const topicInputs = document.querySelectorAll('input[name^="topics["]');
    topicInputs.forEach((input, index) => {
        input.name = `topics[${index}]`;
    });
    topicIndex = topicInputs.length;
}

// Initialize form interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add topic button
    document.getElementById('add-topic').addEventListener('click', addTopicField);
    
    // Remove topic buttons
    document.querySelectorAll('.remove-topic').forEach(button => {
        button.addEventListener('click', function() {
            removeTopicField(this);
        });
    });
});
</script>
@endsection

@section('content')
    <div class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Edit Device: {{ $device->name }}</h6>
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

                        <form action="{{ route('app.devices.update', $device) }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <!-- Basic Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Basic Information</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Device Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                           placeholder="Enter device name" value="{{ old('name', $device->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Device ID <span class="text-danger">*</span></label>
                                    <input type="text" name="device_id" class="form-control @error('device_id') is-invalid @enderror" 
                                           placeholder="Unique device identifier" value="{{ old('device_id', $device->device_id) }}" required>
                                    <small class="form-text text-muted">Unique identifier for MQTT communication</small>
                                    @error('device_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Device Type <span class="text-danger">*</span></label>
                                    <select name="device_type" class="form-select @error('device_type') is-invalid @enderror" required>
                                        <option value="">Select device type</option>
                                        <option value="sensor" {{ old('device_type', $device->device_type) == 'sensor' ? 'selected' : '' }}>Sensor</option>
                                        <option value="actuator" {{ old('device_type', $device->device_type) == 'actuator' ? 'selected' : '' }}>Actuator</option>
                                        <option value="gateway" {{ old('device_type', $device->device_type) == 'gateway' ? 'selected' : '' }}>Gateway</option>
                                        <option value="controller" {{ old('device_type', $device->device_type) == 'controller' ? 'selected' : '' }}>Controller</option>
                                    </select>
                                    @error('device_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                        <option value="offline" {{ old('status', $device->status) == 'offline' ? 'selected' : '' }}>Offline</option>
                                        <option value="online" {{ old('status', $device->status) == 'online' ? 'selected' : '' }}>Online</option>
                                        <option value="maintenance" {{ old('status', $device->status) == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                        <option value="error" {{ old('status', $device->status) == 'error' ? 'selected' : '' }}>Error</option>
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
                                            <option value="{{ $broker->id }}" {{ old('mqtt_broker_id', $device->mqtt_broker_id) == $broker->id ? 'selected' : '' }}>
                                                {{ $broker->name }} ({{ $broker->host }}:{{ $broker->port }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('mqtt_broker_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Land <span class="text-danger">*</span></label>
                                    <select name="land_id" class="form-select @error('land_id') is-invalid @enderror" required>
                                        <option value="">Select land</option>
                                        @foreach($lands as $land)
                                            <option value="{{ $land->id }}" {{ old('land_id', $device->land_id) == $land->id ? 'selected' : '' }}>
                                                {{ $land->land_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('land_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- MQTT Topics (Repeater) -->
                                <div class="col-12 mb-3">
                                    <label class="form-label">MQTT Topics</label>
                                    <div class="repeater" id="topics-repeater">
                                        @if($device->topics && is_array($device->topics) && count($device->topics) > 0)
                                            @foreach($device->topics as $index => $topic)
                                                <div class="repeater-item mb-3" style="border: 1px solid #ddd; border-radius: 0.375rem; background-color: #f8f9fa; padding: 1rem;">
                                                    <div class="d-flex gap-3 align-items-center">
                                                        <div class="flex-fill">
                                                            <input type="text" name="topics[{{ $index }}]" class="form-control" placeholder="Enter MQTT topic (e.g., sensors/temperature)" value="{{ old('topics.'.$index, $topic) }}">
                                                        </div>
                                                        <div style="min-width: 100px;">
                                                            <button type="button" class="btn btn-danger btn-sm remove-topic">
                                                                <i class="ph-trash"></i> Remove
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @else
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
                                        @endif
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
                                           value="{{ old('installed_at', $device->installed_at ? $device->installed_at->format('Y-m-d') : '') }}">
                                    @error('installed_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="form-check mt-4">
                                        <input type="checkbox" name="is_active" class="form-check-input" 
                                               {{ old('is_active', $device->is_active) ? 'checked' : '' }} value="1" id="is_active">
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
                                              rows="3" placeholder="Enter device description">{{ old('description', $device->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Device Status Information -->
                            @if($device->last_seen_at || $device->created_at)
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="fw-semibold">Device Status Information</h6>
                                        <hr class="my-2">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Last Seen</h6>
                                                @if($device->last_seen_at)
                                                    <p class="card-text">
                                                        <i class="ph-clock me-2 text-success"></i>
                                                        {{ $device->last_seen_at->format('M d, Y H:i') }}
                                                        <small class="text-muted d-block">{{ $device->last_seen_at->diffForHumans() }}</small>
                                                    </p>
                                                @else
                                                    <p class="card-text text-muted">
                                                        <i class="ph-clock me-2"></i>
                                                        Never seen
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Device Created</h6>
                                                <p class="card-text">
                                                    <i class="ph-calendar me-2 text-info"></i>
                                                    {{ $device->created_at->format('M d, Y H:i') }}
                                                    <small class="text-muted d-block">{{ $device->created_at->diffForHumans() }}</small>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    @if($device->sensors->count() > 0)
                                        <div class="col-12 mb-3">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6 class="card-title">Connected Sensors</h6>
                                                    <p class="card-text">
                                                        <i class="ph-thermometer me-2 text-warning"></i>
                                                        {{ $device->sensors->count() }} sensor(s) connected to this device
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <!-- Footer Actions -->
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('app.devices.index') }}" class="btn btn-outline-secondary">
                                    <i class="ph-arrow-left me-2"></i>Back to Devices
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    Update Device <i class="ph-floppy-disk ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
