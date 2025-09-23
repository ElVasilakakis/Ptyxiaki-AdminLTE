@extends('layouts.application.app')

@section('content')
    <div class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Edit Sensor: {{ $sensor->sensor_type }}</h6>
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

                        <form action="{{ route('app.sensors.update', $sensor) }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <!-- Basic Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Basic Information</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sensor Type</label>
                                    <input type="text" class="form-control" value="{{ $sensor->sensor_type }}" readonly>
                                    <small class="form-text text-muted">Sensor type cannot be changed</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Device</label>
                                    <input type="text" class="form-control" value="{{ $sensor->device->name }}" readonly>
                                    <small class="form-text text-muted">Device assignment cannot be changed</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sensor Name</label>
                                    <input type="text" name="sensor_name" class="form-control @error('sensor_name') is-invalid @enderror" 
                                           placeholder="Enter sensor name" value="{{ old('sensor_name', $sensor->sensor_name) }}">
                                    <small class="form-text text-muted">Optional friendly name for the sensor</small>
                                    @error('sensor_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" name="unit" class="form-control @error('unit') is-invalid @enderror" 
                                           placeholder="e.g., °C, %, ppm" value="{{ old('unit', $sensor->unit) }}">
                                    <small class="form-text text-muted">Unit of measurement</small>
                                    @error('unit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                              rows="3" placeholder="Enter sensor description">{{ old('description', $sensor->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Current Reading -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Current Reading</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Current Value</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" 
                                               value="{{ $sensor->getFormattedValue() }}" readonly>
                                        <span class="input-group-text">
                                            <i class="ph-clock"></i>
                                        </span>
                                    </div>
                                    <small class="form-text text-muted">
                                        @if ($sensor->reading_timestamp)
                                            Last updated: {{ $sensor->reading_timestamp->format('M d, Y H:i:s') }} 
                                            ({{ $sensor->reading_timestamp->diffForHumans() }})
                                        @else
                                            No readings yet
                                        @endif
                                    </small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="enabled" class="form-check-input" 
                                               {{ old('enabled', $sensor->enabled) ? 'checked' : '' }} value="1" id="enabled">
                                        <label class="form-check-label" for="enabled">
                                            Sensor Enabled
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Disabled sensors will not process new readings</small>
                                </div>
                            </div>

                            <!-- Alert Settings -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold">Alert Settings</h6>
                                    <hr class="my-2">
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="alert_enabled" class="form-check-input" 
                                               {{ old('alert_enabled', $sensor->alert_enabled) ? 'checked' : '' }} value="1" id="alert_enabled">
                                        <label class="form-check-label" for="alert_enabled">
                                            Enable Alerts
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Enable threshold-based alerts for this sensor</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Minimum Threshold</label>
                                    <input type="number" name="min_threshold" step="0.01"
                                           class="form-control @error('min_threshold') is-invalid @enderror"
                                           placeholder="Enter minimum value"
                                           value="{{ old('min_threshold', $sensor->min_threshold) }}">
                                    <small class="form-text text-muted">Alert when value goes below this threshold</small>
                                    @error('min_threshold')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Maximum Threshold</label>
                                    <input type="number" name="max_threshold" step="0.01" 
                                           class="form-control @error('max_threshold') is-invalid @enderror" 
                                           placeholder="Enter maximum value" 
                                           value="{{ old('max_threshold', $sensor->max_threshold) }}">
                                    <small class="form-text text-muted">Alert when value goes above this threshold</small>
                                    @error('max_threshold')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                @if ($sensor->alert_enabled && is_numeric($sensor->value))
                                    <div class="col-12 mb-3">
                                        <div class="alert alert-{{ $sensor->getAlertStatus() === 'normal' ? 'success' : ($sensor->getAlertStatus() === 'high' ? 'danger' : 'warning') }}">
                                            <strong>Current Alert Status:</strong>
                                            @if ($sensor->getAlertStatus() === 'normal')
                                                <i class="ph-check-circle me-1"></i>Normal - Value is within thresholds
                                            @elseif ($sensor->getAlertStatus() === 'high')
                                                <i class="ph-warning-circle me-1"></i>High Alert - Value is above maximum threshold
                                            @elseif ($sensor->getAlertStatus() === 'low')
                                                <i class="ph-warning-circle me-1"></i>Low Alert - Value is below minimum threshold
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Footer Actions -->
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('app.sensors.index') }}" class="btn btn-outline-secondary">
                                    <i class="ph-arrow-left me-2"></i>Back to Sensors
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    Update Sensor <i class="ph-floppy-disk ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
