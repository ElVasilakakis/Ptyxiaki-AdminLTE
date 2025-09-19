@extends('layouts.application.app')

@section('styles')
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <style>
        #map {
            height: 400px;
            width: 100%;
            border-radius: 0.375rem;
            z-index: 1;
        }

        .leaflet-container {
            height: 400px !important;
            width: 100% !important;
        }

        .mqtt-status {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            display: inline-block;
        }

        .mqtt-status.disconnected {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .mqtt-status.connected {
            background-color: #dcfce7;
            color: #16a34a;
        }

        .mqtt-status.connecting {
            background-color: #fef3c7;
            color: #d97706;
        }

        .location-status {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .location-status.inside {
            background-color: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .location-status.outside {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .location-status.unknown {
            background-color: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }

        .sensor-table {
            background: white;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .sensor-row {
            transition: background-color 0.3s ease;
        }

        .sensor-row:hover {
            background-color: #f8fafc;
        }

        .sensor-status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .sensor-status-online {
            background-color: #10b981;
            animation: pulse 2s infinite;
        }

        .sensor-status-offline {
            background-color: #6b7280;
        }

        .alert-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .alert-badge.normal {
            background-color: #dcfce7;
            color: #166534;
        }

        .alert-badge.high {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .alert-badge.low {
            background-color: #fef3c7;
            color: #d97706;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .last-update {
            font-size: 0.875rem;
            color: #6b7280;
        }
    </style>
@endsection

@section('scripts')
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    @if($device->mqttBroker->type === 'lorawan')
        <!-- LoRaWAN Device Script -->
        @include('application.devices.partials.lorawan-device-script')
    @else
        <!-- Webhook Device Script - No real-time connection needed -->
        <script>
            // Basic map functionality for webhook devices
            let map;
            let deviceMarker;
            let distanceMode = false;
            let distanceMarker;
            let distanceLine;

            document.addEventListener('DOMContentLoaded', function() {
                initializeMap();
                // Refresh sensor data every 30 seconds
                setInterval(refreshSensorData, 30000);
            });

            function initializeMap() {
                // Initialize map with default location
                map = L.map('map').setView([38.2466, 21.7346], 10);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                // Add device marker if location data exists
                @if($device->sensors->whereIn('sensor_type', ['latitude', 'longitude'])->count() >= 2)
                    @php
                        $latSensor = $device->sensors->where('sensor_type', 'latitude')->first();
                        $lngSensor = $device->sensors->where('sensor_type', 'longitude')->first();
                    @endphp
                    @if($latSensor && $lngSensor && $latSensor->value && $lngSensor->value)
                        const deviceLat = {{ $latSensor->value }};
                        const deviceLng = {{ $lngSensor->value }};

                        deviceMarker = L.marker([deviceLat, deviceLng])
                            .addTo(map)
                            .bindPopup('<strong>{{ $device->name }}</strong><br>{{ $device->device_id }}');

                        map.setView([deviceLat, deviceLng], 15);
                    @endif
                @endif
            }

            function locateDevice() {
                if (deviceMarker) {
                    map.setView(deviceMarker.getLatLng(), 15);
                    deviceMarker.openPopup();
                }
            }

            function toggleDistanceMode() {
                distanceMode = !distanceMode;
                const btn = document.getElementById('distance-btn');

                if (distanceMode) {
                    btn.classList.add('active');
                    btn.innerHTML = '<i class="ph-x me-1"></i>Cancel Distance';
                    map.on('click', measureDistance);
                } else {
                    btn.classList.remove('active');
                    btn.innerHTML = '<i class="ph-ruler me-1"></i>Measure Distance';
                    map.off('click', measureDistance);

                    if (distanceMarker) {
                        map.removeLayer(distanceMarker);
                        distanceMarker = null;
                    }
                    if (distanceLine) {
                        map.removeLayer(distanceLine);
                        distanceLine = null;
                    }
                }
            }

            function measureDistance(e) {
                if (!deviceMarker) return;

                const clickedPoint = e.latlng;
                const devicePoint = deviceMarker.getLatLng();
                const distance = clickedPoint.distanceTo(devicePoint);

                if (distanceMarker) map.removeLayer(distanceMarker);
                if (distanceLine) map.removeLayer(distanceLine);

                distanceMarker = L.marker(clickedPoint)
                    .addTo(map)
                    .bindPopup(`Distance to device: ${(distance / 1000).toFixed(2)} km`);

                distanceLine = L.polyline([devicePoint, clickedPoint], {color: 'red'})
                    .addTo(map);

                distanceMarker.openPopup();
            }

            function refreshSensorData() {
                // Webhook devices don't have real-time updates
                // Data is updated when webhooks are received
                console.log('Webhook device - data updates via HTTP POST');
            }
        </script>
    @endif
@endsection

@section('content')
    <div class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    @if($device->mqttBroker->type === 'lorawan')
                        @include('application.devices.partials.lorawan-device-content')
                    @else
                        @include('application.devices.partials.webhook-device-content')
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
