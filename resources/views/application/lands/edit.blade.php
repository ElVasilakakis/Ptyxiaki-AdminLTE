@extends('layouts.application.app')

@section('imports')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
      crossorigin=""/>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
        crossorigin=""></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

@endsection

@section('content')
    <div class="content">
        <div class="row">
            <!-- Map Picker Card -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Edit Land Area</h6>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Draw polygon on map to define land boundaries:</label>
                            <div id="map" style="height: 45vh; border: 1px solid #ddd; border-radius: 0.375rem;"></div>
                            <small class="form-text text-muted">Click and drag to draw the land polygon. The coordinates will be automatically saved.</small>
                        </div>
                        
                    </div>
                </div>
            </div>

            <div class="col-12 mt-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Edit Land: {{ $land->land_name }}</h6>
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

                        <form action="{{ route('app.lands.update', $land) }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <!-- Hidden inputs for geojson and location data -->
                            <input type="hidden" name="geojson" id="geojson-data" value="{{ json_encode($land->geojson) }}" required>
                            <input type="hidden" name="location" id="location-data" value="{{ json_encode($land->location) }}" required>
                            
                            <!-- Land Name and Color Row -->
                            <div class="row mb-3">
                                <!-- Land Name -->
                                <div class="col-11">
                                    <label class="form-label">Land Name:</label>
                                    <input type="text" name="land_name" class="form-control @error('land_name') is-invalid @enderror" 
                                           placeholder="Enter land name" value="{{ old('land_name', $land->land_name) }}" required>
                                    @error('land_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Color -->
                                <div class="col-1">
                                    <label class="form-label">Color:</label>
                                    <input type="color" name="color" class="form-control form-control-color @error('color') is-invalid @enderror" 
                                           value="{{ old('color', $land->color ?? '#3498db') }}">
                                    @error('color')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Extra Characteristics (Data Repeater) -->
                            <div class="mb-3">
                                <label class="form-label">Extra Characteristics:</label>
                                <div class="repeater" id="characteristics-repeater">
                                    @if($land->data && count($land->data) > 0)
                                        @foreach($land->data as $key => $value)
                                            <div class="repeater-item mb-3" style="border: 1px solid #ddd; border-radius: 0.375rem; background-color: #f8f9fa; padding: 1rem;">
                                                <div class="d-flex gap-3 align-items-center">
                                                    <div class="flex-fill" style="max-width: 33.33%;">
                                                        <input type="text" name="data[{{ $loop->index }}][key]" class="form-control" 
                                                               placeholder="Characteristic name" value="{{ old('data.'.$loop->index.'.key', $key) }}">
                                                    </div>
                                                    <div class="flex-fill" style="max-width: 50%;">
                                                        <input type="text" name="data[{{ $loop->index }}][value]" class="form-control" 
                                                               placeholder="Value" value="{{ old('data.'.$loop->index.'.value', $value) }}">
                                                    </div>
                                                    <div style="min-width: 100px;">
                                                        <button type="button" class="btn btn-danger btn-sm remove-item">
                                                            <i class="ph-trash"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="repeater-item mb-3" style="border: 1px solid #ddd; border-radius: 0.375rem; background-color: #f8f9fa; padding: 1rem;">
                                            <div class="d-flex gap-3 align-items-center">
                                                <div class="flex-fill" style="max-width: 33.33%;">
                                                    <input type="text" name="data[0][key]" class="form-control" placeholder="Characteristic name">
                                                </div>
                                                <div class="flex-fill" style="max-width: 50%;">
                                                    <input type="text" name="data[0][value]" class="form-control" placeholder="Value">
                                                </div>
                                                <div style="min-width: 100px;">
                                                    <button type="button" class="btn btn-danger btn-sm remove-item">
                                                        <i class="ph-trash"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-success btn-sm" id="add-characteristic">
                                    <i class="ph-plus"></i> Add Characteristic
                                </button>
                            </div>

                            <!-- Footer Actions -->
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" name="enabled" class="form-check-input me-2" 
                                           {{ old('enabled', $land->enabled) ? 'checked' : '' }} value="1">
                                    <span class="form-check-label">Enabled</span>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="{{ route('app.lands.index') }}" class="btn btn-secondary">
                                        <i class="ph-arrow-left me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        Update Land <i class="ph-floppy-disk ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        let map;
        let drawnItems;
        let characteristicIndex = {{ $land->data ? count($land->data) : 1 }};
        let existingGeojson = {!! json_encode($land->geojson) !!};
        let existingLocation = {!! json_encode($land->location) !!};

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Leaflet map
            const defaultCenter = existingLocation && existingLocation.center 
                ? existingLocation.center 
                : [37.9755, 23.7348];
            
            map = L.map('map').setView(defaultCenter, 10);

            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // FeatureGroup to store editable layers
            drawnItems = new L.FeatureGroup();
            map.addLayer(drawnItems);

            // Load existing polygon if available
            if (existingGeojson) {
                const layer = L.geoJSON(existingGeojson, {
                    style: {
                        color: '{{ $land->color ?? "#3498db" }}',
                        weight: 3,
                        opacity: 0.8,
                        fillOpacity: 0.3
                    }
                });
                
                layer.eachLayer(function(l) {
                    drawnItems.addLayer(l);
                });

                // Fit map to existing polygon
                if (existingLocation && existingLocation.bounds) {
                    const bounds = L.latLngBounds([
                        [existingLocation.bounds.south, existingLocation.bounds.west],
                        [existingLocation.bounds.north, existingLocation.bounds.east]
                    ]);
                    map.fitBounds(bounds);
                }
            }

            // Initialize the draw control
            const drawControl = new L.Control.Draw({
                position: 'topright',
                draw: {
                    polygon: {
                        shapeOptions: {
                            color: '{{ $land->color ?? "#3498db" }}',
                            weight: 3,
                            opacity: 0.8,
                            fillOpacity: 0.3
                        },
                        allowIntersection: false,
                        showArea: true,
                        metric: true
                    },
                    circle: false,
                    rectangle: false,
                    polyline: false,
                    marker: false,
                    circlemarker: false
                },
                edit: {
                    featureGroup: drawnItems,
                    remove: true
                }
            });
            map.addControl(drawControl);

            // Handle draw events
            map.on(L.Draw.Event.CREATED, function(event) {
                const layer = event.layer;
                
                // Clear previous polygons (only allow one)
                drawnItems.clearLayers();
                
                // Add new polygon
                drawnItems.addLayer(layer);
                
                updateHiddenInputs(layer);
            });

            map.on(L.Draw.Event.EDITED, function(event) {
                const layers = event.layers;
                layers.eachLayer(function(layer) {
                    updateHiddenInputs(layer);
                });
            });

            map.on(L.Draw.Event.DELETED, function(event) {
                // Clear hidden inputs when polygon is deleted
                document.getElementById('geojson-data').value = '';
                document.getElementById('location-data').value = '';
            });

            function updateHiddenInputs(layer) {
                const geojson = layer.toGeoJSON();
                const bounds = layer.getBounds();
                const center = bounds.getCenter();
                
                // Update hidden inputs
                document.getElementById('geojson-data').value = JSON.stringify(geojson);
                document.getElementById('location-data').value = JSON.stringify({
                    center: [center.lat, center.lng],
                    bounds: {
                        north: bounds.getNorth(),
                        south: bounds.getSouth(),
                        east: bounds.getEast(),
                        west: bounds.getWest()
                    }
                });
            }

            // Initialize repeater functionality
            initializeRepeater();
        });

        // Repeater functionality
        function initializeRepeater() {
            // Add characteristic button
            document.getElementById('add-characteristic').addEventListener('click', function() {
                const repeater = document.getElementById('characteristics-repeater');
                const newItem = createCharacteristicItem(characteristicIndex);
                repeater.appendChild(newItem);
                characteristicIndex++;
            });

            // Handle remove buttons (using event delegation)
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-item')) {
                    const item = e.target.closest('.repeater-item');
                    if (item && document.querySelectorAll('.repeater-item').length > 1) {
                        item.remove();
                        updateIndexes();
                    }
                }
            });
        }

        function createCharacteristicItem(index) {
            const div = document.createElement('div');
            div.className = 'repeater-item mb-3';
            div.style.border = '1px solid #ddd';
            div.style.borderRadius = '0.375rem';
            div.style.backgroundColor = '#f8f9fa';
            div.style.padding = '1rem';
            
            div.innerHTML = `
                <div class="d-flex gap-3 align-items-center">
                    <div class="flex-fill" style="max-width: 33.33%;">
                        <input type="text" name="data[${index}][key]" class="form-control" placeholder="Characteristic name">
                    </div>
                    <div class="flex-fill" style="max-width: 50%;">
                        <input type="text" name="data[${index}][value]" class="form-control" placeholder="Value">
                    </div>
                    <div style="min-width: 100px;">
                        <button type="button" class="btn btn-danger btn-sm remove-item">
                            <i class="ph-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            
            return div;
        }

        // Update indexes after removing items
        function updateIndexes() {
            const items = document.querySelectorAll('.repeater-item');
            items.forEach((item, index) => {
                const inputs = item.querySelectorAll('input[name*="data["]');
                inputs.forEach(input => {
                    const name = input.getAttribute('name');
                    const newName = name.replace(/data\[\d+\]/, `data[${index}]`);
                    input.setAttribute('name', newName);
                });
            });
            
            characteristicIndex = items.length;
        }
    </script>
@endsection
