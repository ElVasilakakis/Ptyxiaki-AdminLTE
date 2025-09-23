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

// Toggle connection fields based on connection type
function toggleConnectionFields() {
    const connectionType = document.querySelector('select[name="connection_type"]').value;
    const mqttFields = document.getElementById('mqtt-connection-fields');
    
    if (connectionType === 'mqtt') {
        mqttFields.style.display = 'block';
    } else {
        mqttFields.style.display = 'none';
    }
}

// Toggle port based on SSL/TLS selection
function toggleSSLPort() {
    const sslCheckbox = document.querySelector('input[name="use_ssl"]');
    const portInput = document.querySelector('input[name="port"]');
    
    if (sslCheckbox.checked) {
        // Use SSL port (8883 for MQTT over SSL)
        portInput.value = '8883';
    } else {
        // Use standard MQTT port
        portInput.value = '1883';
    }
}

// Add MQTT topic field
function addMqttTopic() {
    const container = document.getElementById('mqtt-topics-container');
    const topicCount = container.children.length;
    
    const newTopicDiv = document.createElement('div');
    newTopicDiv.className = 'input-group mb-2';
    newTopicDiv.innerHTML = `
        <input type="text" name="mqtt_topics[]" class="form-control" 
               placeholder="sensors/humidity">
        <button type="button" class="btn btn-outline-danger" onclick="removeMqttTopic(this)">
            <i class="ph-minus"></i>
        </button>
    `;
    
    container.appendChild(newTopicDiv);
}

// Remove MQTT topic field
function removeMqttTopic(button) {
    const container = document.getElementById('mqtt-topics-container');
    if (container.children.length > 1) {
        button.parentElement.remove();
    }
}

// Initialize form interactions
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate device ID when name or type changes
    document.querySelector('input[name="name"]').addEventListener('input', generateDeviceId);
    document.querySelector('select[name="device_type"]').addEventListener('change', generateDeviceId);
    
    // Toggle connection fields when connection type changes
    document.querySelector('select[name="connection_type"]').addEventListener('change', toggleConnectionFields);
    
    // Toggle port when SSL/TLS checkbox changes
    document.querySelector('input[name="use_ssl"]').addEventListener('change', toggleSSLPort);
    
    // Set default values
    document.querySelector('select[name="status"]').value = 'offline';
    document.querySelector('select[name="connection_type"]').value = 'webhook';
    document.querySelector('input[name="is_active"]').checked = true;
    document.querySelector('input[name="use_ssl"]').checked = false;
    document.querySelector('input[name="auto_reconnect"]').checked = true;
    
    // Initialize connection fields visibility
    toggleConnectionFields();
});
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
                                    <small class="form-text text-muted">Unique identifier for device communication</small>
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

                            <!-- Connection Settings -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Connection Settings</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Connection Type <span class="text-danger">*</span></label>
                                    <select name="connection_type" class="form-select @error('connection_type') is-invalid @enderror" required>
                                        <option value="">Select connection type</option>
                                        <option value="webhook" {{ old('connection_type', 'webhook') == 'webhook' ? 'selected' : '' }}>Webhook</option>
                                        <option value="mqtt" {{ old('connection_type') == 'mqtt' ? 'selected' : '' }}>MQTT</option>
                                    </select>
                                    @error('connection_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- MQTT Connection Fields (Hidden by default) -->
                                <div id="mqtt-connection-fields" style="display: none;">
                                    <div class="col-12 mb-3">
                                        <div class="alert alert-info">
                                            <i class="ph-info me-2"></i>
                                            Configure MQTT connection settings for this device.
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Client ID</label>
                                        <input type="text" name="client_id" class="form-control @error('client_id') is-invalid @enderror" 
                                               placeholder="MQTT client ID" value="{{ old('client_id') }}">
                                        <small class="form-text text-muted">Leave empty to auto-generate</small>
                                        @error('client_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Connection Broker</label>
                                        <select name="connection_broker" class="form-select @error('connection_broker') is-invalid @enderror">
                                            <option value="">Select broker type</option>
                                            <option value="mosquitto" {{ old('connection_broker') == 'mosquitto' ? 'selected' : '' }}>Mosquitto</option>
                                            <option value="emqx" {{ old('connection_broker') == 'emqx' ? 'selected' : '' }}>EMQX (Public servers only)</option>
                                            <!-- HiveMQ options removed from user interface but functionality preserved -->
                                            <!-- The Things Stack removed from MQTT options - it only works with webhooks -->
                                        </select>
                                        @error('connection_broker')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Port</label>
                                        <input type="number" name="port" class="form-control @error('port') is-invalid @enderror" 
                                               placeholder="1883" value="{{ old('port', 1883) }}" min="1" max="65535">
                                        <small class="form-text text-muted">Port will auto-change: 1883 (standard) / 8883 (SSL)</small>
                                        @error('port')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" 
                                               placeholder="MQTT username" value="{{ old('username') }}">
                                        @error('username')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" 
                                               placeholder="MQTT password">
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="use_ssl" class="form-check-input" 
                                                   {{ old('use_ssl') ? 'checked' : '' }} value="1" id="use_ssl">
                                            <label class="form-check-label" for="use_ssl">
                                                Use SSL/TLS
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="auto_reconnect" class="form-check-input" 
                                                   {{ old('auto_reconnect', true) ? 'checked' : '' }} value="1" id="auto_reconnect">
                                            <label class="form-check-label" for="auto_reconnect">
                                                Auto Reconnect
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Max Reconnect Attempts</label>
                                        <input type="number" name="max_reconnect_attempts" class="form-control @error('max_reconnect_attempts') is-invalid @enderror" 
                                               placeholder="3" value="{{ old('max_reconnect_attempts', 3) }}" min="1" max="100">
                                        @error('max_reconnect_attempts')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Keep Alive (seconds)</label>
                                        <input type="number" name="keepalive" class="form-control @error('keepalive') is-invalid @enderror" 
                                               placeholder="60" value="{{ old('keepalive', 60) }}" min="1" max="3600">
                                        @error('keepalive')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Timeout (seconds)</label>
                                        <input type="number" name="timeout" class="form-control @error('timeout') is-invalid @enderror" 
                                               placeholder="30" value="{{ old('timeout', 30) }}" min="1" max="300">
                                        @error('timeout')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">MQTT Host</label>
                                        <input type="text" name="mqtt_host" class="form-control @error('mqtt_host') is-invalid @enderror" 
                                               placeholder="broker.hivemq.com" value="{{ old('mqtt_host') }}">
                                        <small class="form-text text-muted">MQTT broker hostname or IP address</small>
                                        @error('mqtt_host')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">MQTT Topics</label>
                                        <div id="mqtt-topics-container">
                                            <div class="input-group mb-2">
                                                <input type="text" name="mqtt_topics[]" class="form-control" 
                                                       placeholder="sensors/temperature" value="{{ old('mqtt_topics.0') }}">
                                                <button type="button" class="btn btn-outline-success" onclick="addMqttTopic()">
                                                    <i class="ph-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Topics this device will subscribe to for sensor data</small>
                                        @error('mqtt_topics')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
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
