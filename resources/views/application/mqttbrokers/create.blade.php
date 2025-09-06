@extends('layouts.application.app')

@section('scripts')
<script>
function testConnectionFromForm() {
    const button = event.target;
    const originalContent = button.innerHTML;
    
    // Get form values
    const host = document.querySelector('input[name="host"]').value;
    const port = document.querySelector('input[name="port"]').value;
    const useSSL = document.querySelector('input[name="use_ssl"]').checked;
    const sslPort = document.querySelector('input[name="ssl_port"]').value;
    const username = document.querySelector('input[name="username"]').value;
    const password = document.querySelector('input[name="password"]').value;
    const timeout = document.querySelector('input[name="timeout"]').value;
    
    // Validate required fields
    if (!host || !port) {
        showNotification('Please fill in the Host and Port fields before testing connection.', 'error');
        return;
    }
    
    // Show loading state
    button.innerHTML = '<i class="ph-spinner ph-spin me-2"></i>Testing...';
    button.disabled = true;
    
    // Prepare test data
    const testData = {
        host: host,
        port: parseInt(port),
        use_ssl: useSSL,
        ssl_port: sslPort ? parseInt(sslPort) : null,
        username: username || null,
        password: password || null,
        timeout: timeout ? parseInt(timeout) : 30
    };
    
    // Make AJAX request to test connection
    fetch('/app/mqttbrokers/test-connection-form', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(testData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`Connection test successful! ${data.message}`, 'success');
            
            // Temporarily change button to success state
            button.innerHTML = '<i class="ph-check me-2"></i>Connection Successful';
            button.classList.remove('btn-outline-success');
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = originalContent;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-success');
            }, 3000);
        } else {
            showNotification(`Connection test failed: ${data.message}`, 'error');
            
            // Temporarily change button to error state
            button.innerHTML = '<i class="ph-x me-2"></i>Connection Failed';
            button.classList.remove('btn-outline-success');
            button.classList.add('btn-danger');
            
            setTimeout(() => {
                button.innerHTML = originalContent;
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline-success');
            }, 3000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Connection test failed due to network error.', 'error');
        
        // Reset button state
        button.innerHTML = '<i class="ph-x me-2"></i>Test Failed';
        button.classList.remove('btn-outline-success');
        button.classList.add('btn-danger');
        
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.classList.remove('btn-danger');
            button.classList.add('btn-outline-success');
        }, 3000);
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
</script>
@endsection

@section('content')
    <div class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Add New MQTT Broker</h6>
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

                        <form action="{{ route('app.mqttbrokers.store') }}" method="POST">
                            @csrf
                            
                            <!-- Basic Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Basic Information</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Broker Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                           placeholder="Enter broker name" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Broker Type <span class="text-danger">*</span></label>
                                    <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                        <option value="">Select broker type</option>
                                        <option value="mosquitto" {{ old('type') == 'mosquitto' ? 'selected' : '' }}>Mosquitto</option>
                                        <option value="emqx" {{ old('type') == 'emqx' ? 'selected' : '' }}>EMQX</option>
                                        <option value="lorawan" {{ old('type') == 'lorawan' ? 'selected' : '' }}>LoRaWAN</option>
                                    </select>
                                    @error('type')
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
                                
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Host <span class="text-danger">*</span></label>
                                    <input type="text" name="host" class="form-control @error('host') is-invalid @enderror" 
                                           placeholder="broker.example.com" value="{{ old('host') }}" required>
                                    @error('host')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Port <span class="text-danger">*</span></label>
                                    <input type="number" name="port" class="form-control @error('port') is-invalid @enderror" 
                                           placeholder="1883" value="{{ old('port', 1883) }}" min="1" max="65535" required>
                                    @error('port')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">WebSocket Port</label>
                                    <input type="number" name="websocket_port" class="form-control @error('websocket_port') is-invalid @enderror" 
                                           placeholder="8083" value="{{ old('websocket_port') }}" min="1" max="65535">
                                    @error('websocket_port')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                            </div>

                            <!-- Authentication -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Authentication</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" 
                                           placeholder="Enter username" value="{{ old('username') }}">
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" 
                                           placeholder="Enter password">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- SSL Settings -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">SSL Settings</h6>
                                    <hr class="my-2">
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
                                    <label class="form-label">SSL Port</label>
                                    <input type="number" name="ssl_port" class="form-control @error('ssl_port') is-invalid @enderror" 
                                           placeholder="8883" value="{{ old('ssl_port') }}" min="1" max="65535">
                                    @error('ssl_port')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Connection Options -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Connection Options</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Keep Alive (seconds) <span class="text-danger">*</span></label>
                                    <input type="number" name="keepalive" class="form-control @error('keepalive') is-invalid @enderror" 
                                           placeholder="60" value="{{ old('keepalive', 60) }}" min="1" max="3600" required>
                                    @error('keepalive')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Timeout (seconds) <span class="text-danger">*</span></label>
                                    <input type="number" name="timeout" class="form-control @error('timeout') is-invalid @enderror" 
                                           placeholder="30" value="{{ old('timeout', 30) }}" min="1" max="300" required>
                                    @error('timeout')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Max Reconnect Attempts <span class="text-danger">*</span></label>
                                    <input type="number" name="max_reconnect_attempts" class="form-control @error('max_reconnect_attempts') is-invalid @enderror" 
                                           placeholder="5" value="{{ old('max_reconnect_attempts', 5) }}" min="1" max="100" required>
                                    @error('max_reconnect_attempts')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="auto_reconnect" class="form-check-input" 
                                               {{ old('auto_reconnect', true) ? 'checked' : '' }} value="1" id="auto_reconnect">
                                        <label class="form-check-label" for="auto_reconnect">
                                            Auto Reconnect
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                              rows="3" placeholder="Enter broker description">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-success" onclick="testConnectionFromForm()">
                                    <i class="ph-plug me-2"></i>Test Connection
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    Save MQTT Broker <i class="ph-floppy-disk ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
