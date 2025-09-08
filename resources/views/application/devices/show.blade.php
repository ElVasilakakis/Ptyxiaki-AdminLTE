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
        <!-- LoRaWAN Device Scripts will be added later -->
        {{-- Future LoRaWAN implementation --}}
    @else
        <!-- MQTT.js -->
        <script src="https://unpkg.com/mqtt@5.3.4/dist/mqtt.min.js"></script>
        @include('application.devices.partials.mqtt-device-script')
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
                        @include('application.devices.partials.mqtt-device-content')
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
