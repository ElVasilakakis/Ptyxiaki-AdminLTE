@extends('layouts.application.app')

@section('content')
    <div class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Sensors Management</h6>
                    </div>

                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        @if ($sensors->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Sensor Type</th>
                                            <th>Name</th>
                                            <th>Device</th>
                                            <th>Land</th>
                                            <th>Current Value</th>
                                            <th>Unit</th>
                                            <th>Status</th>
                                            <th>Alerts</th>
                                            <th>Last Reading</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($sensors as $sensor)
                                            <tr>
                                                <td>
                                                    <span class="badge bg-info">{{ ucfirst($sensor->sensor_type) }}</span>
                                                </td>
                                                <td>{{ $sensor->sensor_name ?: 'Unnamed' }}</td>
                                                <td>{{ $sensor->device->name }}</td>
                                                <td>{{ $sensor->device->land->land_name }}</td>
                                                <td>
                                                    @if ($sensor->value !== null)
                                                        @if (is_numeric($sensor->value))
                                                            {{ number_format((float) $sensor->value, 2) }}
                                                        @else
                                                            {{ is_array($sensor->value) || is_object($sensor->value) ? json_encode($sensor->value) : $sensor->value }}
                                                        @endif
                                                    @else
                                                        <span class="text-muted">No reading</span>
                                                    @endif
                                                </td>
                                                <td>{{ $sensor->unit ?: '-' }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $sensor->enabled ? 'success' : 'secondary' }}">
                                                        {{ $sensor->enabled ? 'Enabled' : 'Disabled' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if ($sensor->alert_enabled)
                                                        @php
                                                            $alertStatus = $sensor->getAlertStatus();
                                                        @endphp
                                                        <span class="badge bg-{{ $alertStatus === 'normal' ? 'success' : ($alertStatus === 'high' ? 'danger' : 'warning') }}">
                                                            @if ($alertStatus === 'normal')
                                                                Normal
                                                            @elseif ($alertStatus === 'high')
                                                                High Alert
                                                            @elseif ($alertStatus === 'low')
                                                                Low Alert
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary">Disabled</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($sensor->reading_timestamp)
                                                        <span title="{{ $sensor->reading_timestamp->format('M d, Y H:i:s') }}">
                                                            {{ $sensor->reading_timestamp->diffForHumans() }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">Never</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <a href="{{ route('app.sensors.edit', $sensor) }}" 
                                                           class="btn btn-outline-primary btn-sm">
                                                            <i class="ph-pencil me-1"></i>Edit
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="ph-gauge ph-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No sensors found</h5>
                                <p class="text-muted">Sensors will appear here automatically when devices start sending data.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
