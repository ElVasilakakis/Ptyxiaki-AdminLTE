@extends('layouts.application.app')

@section('title', 'How the System Works')

@section('content')
<div class="content">
    <div class="page-header page-header-light shadow">
        <div class="page-header-content d-lg-flex">
            <div class="d-flex">
                <h4 class="page-title mb-0">
                    How the System Works
                </h4>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Understanding the IoT Monitoring System</h5>
                        <p class="text-muted mb-0">Learn how everything works together and the proper setup order</p>
                    </div>
                    <div class="card-body">
                        
                        <!-- System Overview -->
                        <div class="alert alert-primary mb-4">
                            <div class="d-flex align-items-center">
                                <i class="ph-gear fs-3 me-3"></i>
                                <div>
                                    <h6 class="fw-semibold mb-1">What is this System?</h6>
                                    <p class="mb-0">This is an IoT monitoring platform that collects sensor data from your devices (temperature, humidity, GPS, etc.) and displays it in an easy-to-understand dashboard. It supports both LoRaWAN and MQTT devices.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Registration Order -->
                        <div class="mb-5">
                            <h6 class="fw-semibold text-danger mb-3">
                                <i class="ph-list-numbers me-2"></i>Important: Registration Order
                            </h6>
                            
                            <div class="alert alert-warning">
                                <i class="ph-warning me-2"></i>
                                <strong>Follow this order exactly!</strong> Each step depends on the previous one.
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    
                                    <!-- Step 1: Lands -->
                                    <div class="card border-primary mb-3">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0">
                                                <span class="badge bg-white text-primary me-2">1</span>
                                                Create Lands First
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex align-items-start">
                                                <i class="ph-map-pin-line text-primary fs-4 me-3 mt-1"></i>
                                                <div>
                                                    <p class="mb-2"><strong>What are Lands?</strong> Lands represent physical locations or areas where your devices are installed (e.g., "Farm Field A", "Greenhouse 1", "Office Building").</p>
                                                    <p class="mb-2"><strong>Why first?</strong> Every device must belong to a land, so you need to create lands before adding devices.</p>
                                                    <p class="mb-2">Go to <a href="{{ route('app.lands.create') }}" class="fw-semibold">Lands → Add New</a></p>
                                                    <p class="mb-0 text-success">✓ Create at least one land before proceeding</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 2: Connectors -->
                                    <div class="card border-success mb-3">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="mb-0">
                                                <span class="badge bg-white text-success me-2">2</span>
                                                Set Up Connectors(if using MQTT devices)
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex align-items-start">
                                                <i class="ph-computer-tower text-success fs-4 me-3 mt-1"></i>
                                                <div>
                                                    <p class="mb-2"><strong>What are Connectors?</strong> These are servers that handle message communication between your MQTT devices and this system.</p>
                                                    <p class="mb-2"><strong>When needed?</strong> Only if you have MQTT devices. Skip this if you only use LoRaWAN devices.</p>
                                                    <p class="mb-2">Go to <a href="{{ route('app.mqttbrokers.create') }}" class="fw-semibold">Connectors → Add New</a></p>
                                                    <p class="mb-0 text-success">✓ Set up brokers for your MQTT devices</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 3: Devices -->
                                    <div class="card border-warning mb-3">
                                        <div class="card-header bg-warning text-white">
                                            <h6 class="mb-0">
                                                <span class="badge bg-white text-warning me-2">3</span>
                                                Add Your Devices
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex align-items-start">
                                                <i class="ph-cpu text-warning fs-4 me-3 mt-1"></i>
                                                <div>
                                                    <p class="mb-2"><strong>What are Devices?</strong> These represent your physical IoT devices (sensors, weather stations, etc.) that collect and send data.</p>
                                                    <p class="mb-2"><strong>Requirements:</strong> You must have created a land first. For MQTT devices, you also need an Connector.</p>
                                                    <p class="mb-2">Go to <a href="{{ route('app.devices.create') }}" class="fw-semibold">Devices → Add New</a></p>
                                                    <p class="mb-0 text-success">✓ Register all your physical devices</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 4: Sensors -->
                                    <div class="card border-info mb-3">
                                        <div class="card-header bg-info text-white">
                                            <h6 class="mb-0">
                                                <span class="badge bg-white text-info me-2">4</span>
                                                Sensors are Created Automatically
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex align-items-start">
                                                <i class="ph-thermometer-hot text-info fs-4 me-3 mt-1"></i>
                                                <div>
                                                    <p class="mb-2"><strong>What are Sensors?</strong> Individual measurements from your devices (temperature, humidity, GPS coordinates, etc.).</p>
                                                    <p class="mb-2"><strong>Automatic Creation:</strong> When your devices send data, the system automatically creates sensors for each type of measurement.</p>
                                                    <p class="mb-2">View them at <a href="{{ route('app.sensors.index') }}" class="fw-semibold">Sensors</a></p>
                                                    <p class="mb-0 text-success">✓ No manual setup needed - just wait for data!</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- How Data Flows -->
                        <div class="mb-5">
                            <h6 class="fw-semibold text-secondary mb-3">
                                <i class="ph-flow-arrow me-2"></i>How Data Flows Through the System
                            </h6>
                            
                            <div class="alert alert-light">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">LoRaWAN Devices:</h6>
                                        <ol class="mb-0">
                                            <li>Device sends data to The Things Network</li>
                                            <li>TTN forwards data via webhook to this system</li>
                                            <li>System processes and stores the data</li>
                                            <li>Data appears in dashboard and sensors page</li>
                                        </ol>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-semibold mb-2">MQTT Devices:</h6>
                                        <ol class="mb-0">
                                            <li>Device connects to Connector</li>
                                            <li>Device publishes sensor data to onnector</li>
                                            <li>System receives data from connector</li>
                                            <li>Data appears in dashboard and sensors page</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Components -->
                        <div class="mb-5">
                            <h6 class="fw-semibold text-secondary mb-3">
                                <i class="ph-puzzle-piece me-2"></i>System Components Explained
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-light mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="ph-house me-2"></i>Dashboard</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-0">Main overview showing all your devices, recent sensor readings, and system status. This is your control center.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="card border-light mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="ph-map-pin-line me-2"></i>Lands</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-0">Physical locations where devices are installed. Helps organize devices by location and provides geographical context.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-light mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="ph-cpu me-2"></i>Devices</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-0">Your physical IoT devices. Each device can have multiple sensors and belongs to a specific land.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="card border-light mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="ph-thermometer-hot me-2"></i>Sensors</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-0">Individual measurements from devices. Each sensor tracks one type of data (temperature, humidity, etc.).</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Start Guide -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-success mb-3">
                                <i class="ph-rocket-launch me-2"></i>Quick Start Guide
                            </h6>
                            
                            <div class="alert alert-success">
                                <h6 class="fw-semibold mb-2">New to the system? Follow these steps:</h6>
                                <ol class="mb-2">
                                    <li><strong>Create a Land:</strong> <a href="{{ route('app.lands.create') }}">Add your first location</a></li>
                                    <li><strong>Set up connectivity:</strong> 
                                        <ul class="mt-1">
                                            <li>For MQTT devices: <a href="{{ route('app.mqttbrokers.create') }}">Add Connector</a></li>
                                            <li>For LoRaWAN devices: Set up The Things Network account</li>
                                        </ul>
                                    </li>
                                    <li><strong>Add your devices:</strong> <a href="{{ route('app.devices.create') }}">Register your IoT devices</a></li>
                                    <li><strong>Configure data flow:</strong> Set up webhooks or MQTT topics</li>
                                    <li><strong>Monitor data:</strong> Check <a href="{{ route('app.dashboard') }}">Dashboard</a> for incoming sensor readings</li>
                                </ol>
                                <p class="mb-0">Need detailed instructions? Check our specific guides:</p>
                                <div class="d-flex gap-2 mt-2">
                                    <a href="{{ route('app.lorawan-guide') }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ph-broadcast me-1"></i>LoRaWAN Guide
                                    </a>
                                    <a href="{{ route('app.mqtt-guide') }}" class="btn btn-sm btn-outline-success">
                                        <i class="ph-wifi-high me-1"></i>MQTT Guide
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Common Questions -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-info mb-3">
                                <i class="ph-question me-2"></i>Common Questions
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-light mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">Why do I need to create lands first?</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-0">Every device must belong to a physical location (land). The system uses this to organize your devices geographically and provide location context for your sensor data.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="card border-light mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">What's the difference between LoRaWAN and MQTT?</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-0"><strong>LoRaWAN:</strong> Long-range, low-power wireless. Great for outdoor sensors. <strong>MQTT:</strong> Internet-based messaging. Requires WiFi/cellular connection.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-light mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">How are sensors created?</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-0">Sensors are created automatically when your devices send data. Each type of measurement (temperature, humidity, etc.) becomes a separate sensor.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="card border-light mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">Can I mix LoRaWAN and MQTT devices?</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-0">Yes! The system supports both types of devices. You can have LoRaWAN sensors in remote locations and MQTT devices where WiFi is available.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Next Steps -->
                        <div class="text-center">
                            <div class="alert alert-primary">
                                <h6 class="fw-semibold mb-2">Ready to Get Started?</h6>
                                <p class="mb-3">Choose your path based on your device type:</p>
                                <div class="d-flex justify-content-center gap-3 flex-wrap">
                                    <a href="{{ route('app.lands.create') }}" class="btn btn-primary">
                                        <i class="ph-map-pin-line me-1"></i>Create First Land
                                    </a>
                                    <a href="{{ route('app.lorawan-guide') }}" class="btn btn-outline-primary">
                                        <i class="ph-broadcast me-1"></i>LoRaWAN Setup
                                    </a>
                                    <a href="{{ route('app.mqtt-guide') }}" class="btn btn-outline-success">
                                        <i class="ph-wifi-high me-1"></i>MQTT Setup
                                    </a>
                                    <a href="{{ route('app.dashboard') }}" class="btn btn-outline-info">
                                        <i class="ph-house me-1"></i>View Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
