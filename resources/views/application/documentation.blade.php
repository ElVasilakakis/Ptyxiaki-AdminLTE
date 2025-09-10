@extends('layouts.application.app')

@section('title', 'Device Registration Guide')

@section('content')
<div class="content">
    <div class="page-header page-header-light shadow">
        <div class="page-header-content d-lg-flex">
            <div class="d-flex">
                <h4 class="page-title mb-0">
                    Device Registration Guide
                </h4>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">How to Register Your Devices</h5>
                        <p class="text-muted mb-0">Simple step-by-step guide for registering both LoRaWAN and MQTT devices</p>
                    </div>
                    <div class="card-body">
                        <!-- LoRaWAN Device Registration -->
                        <div class="mb-5">
                            <h6 class="fw-semibold text-primary mb-3">
                                <i class="ph-broadcast me-2"></i>LoRaWAN Device Registration
                            </h6>
                            
                            <div class="alert alert-info">
                                <i class="ph-info me-2"></i>
                                <strong>What is LoRaWAN?</strong> LoRaWAN is a wireless technology for long-range, low-power devices like sensors that can send data over several kilometers.
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-3">Step 1: Get Your Device Information</h6>
                                    <p>You'll need these details from your LoRaWAN device:</p>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <span class="badge bg-primary me-2">DevEUI</span>
                                            <span class="text-muted">Device unique identifier (16 characters)</span>
                                        </li>
                                        <li class="mb-2">
                                            <span class="badge bg-success me-2">AppEUI</span>
                                            <span class="text-muted">Application identifier (16 characters)</span>
                                        </li>
                                        <li class="mb-2">
                                            <span class="badge bg-warning me-2">AppKey</span>
                                            <span class="text-muted">Application key (32 characters)</span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-3">Step 2: Register in The Things Stack</h6>
                                    <ol>
                                        <li>Go to <a href="https://console.thethingsnetwork.org" target="_blank">The Things Network Console</a></li>
                                        <li>Create an account or login</li>
                                        <li>Create a new application</li>
                                        <li>Add your device using the DevEUI, AppEUI, and AppKey</li>
                                        <li>Copy the MQTT connection details</li>
                                    </ol>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold mb-3">Step 3: Add Device to This System</h6>
                                    <ol>
                                        <li>Go to <a href="{{ route('app.devices.create') }}">Add New Device</a></li>
                                        <li>Fill in the device information:
                                            <ul class="mt-2">
                                                <li><strong>Name:</strong> Give your device a friendly name</li>
                                                <li><strong>Description:</strong> Optional description</li>
                                                <li><strong>Land:</strong> Select which land/area this device belongs to</li>
                                                <li><strong>Device Type:</strong> Select "LoRaWAN"</li>
                                                <li><strong>Device ID:</strong> Enter your DevEUI</li>
                                                <li><strong>Application ID:</strong> Enter your AppEUI</li>
                                            </ul>
                                        </li>
                                        <li>Click "Create Device"</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5">

                        <!-- MQTT Device Registration -->
                        <div class="mb-5">
                            <h6 class="fw-semibold text-success mb-3">
                                <i class="ph-wifi-high me-2"></i>MQTT Device Registration
                            </h6>
                            
                            <div class="alert alert-success">
                                <i class="ph-info me-2"></i>
                                <strong>What is MQTT?</strong> MQTT is a messaging protocol that allows devices to send data over WiFi or internet connection to a central server (broker).
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-3">Step 1: Set Up MQTT Broker</h6>
                                    <p>First, you need an MQTT broker. You can:</p>
                                    <ul>
                                        <li><strong>Use a cloud service:</strong> HiveMQ, AWS IoT, or Google Cloud IoT</li>
                                        <li><strong>Self-hosted:</strong> Install Mosquitto on your server</li>
                                        <li><strong>Local testing:</strong> Use test.mosquitto.org (not for production)</li>
                                    </ul>
                                    
                                    <div class="mt-3">
                                        <p class="fw-semibold">You'll need:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <span class="badge bg-primary me-2">Host</span>
                                                <span class="text-muted">Broker server address</span>
                                            </li>
                                            <li class="mb-2">
                                                <span class="badge bg-success me-2">Port</span>
                                                <span class="text-muted">Usually 1883 or 8883 (secure)</span>
                                            </li>
                                            <li class="mb-2">
                                                <span class="badge bg-warning me-2">Username</span>
                                                <span class="text-muted">If authentication is required</span>
                                            </li>
                                            <li class="mb-2">
                                                <span class="badge bg-danger me-2">Password</span>
                                                <span class="text-muted">If authentication is required</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-3">Step 2: Add MQTT Broker</h6>
                                    <ol>
                                        <li>Go to <a href="{{ route('app.mqttbrokers.create') }}">Add MQTT Broker</a></li>
                                        <li>Fill in the broker details:
                                            <ul class="mt-2">
                                                <li><strong>Name:</strong> Friendly name for your broker</li>
                                                <li><strong>Host:</strong> Your broker's address</li>
                                                <li><strong>Port:</strong> Connection port (1883 for standard, 8883 for SSL)</li>
                                                <li><strong>Username/Password:</strong> If required by your broker</li>
                                                <li><strong>Path:</strong> Optional path prefix</li>
                                            </ul>
                                        </li>
                                        <li>Click "Create Broker"</li>
                                    </ol>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6 class="fw-semibold mb-3">Step 3: Add MQTT Device</h6>
                                    <ol>
                                        <li>Go to <a href="{{ route('app.devices.create') }}">Add New Device</a></li>
                                        <li>Fill in the device information:
                                            <ul class="mt-2">
                                                <li><strong>Name:</strong> Give your device a friendly name</li>
                                                <li><strong>Description:</strong> Optional description</li>
                                                <li><strong>Land:</strong> Select which land/area this device belongs to</li>
                                                <li><strong>Device Type:</strong> Select "MQTT"</li>
                                                <li><strong>MQTT Broker:</strong> Select the broker you created</li>
                                                <li><strong>Topic:</strong> The MQTT topic your device publishes to</li>
                                            </ul>
                                        </li>
                                        <li>Click "Create Device"</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5">

                        <!-- Common Information -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-info mb-3">
                                <i class="ph-lightbulb me-2"></i>Important Tips
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="alert alert-warning">
                                        <h6 class="fw-semibold mb-2">Security Best Practices</h6>
                                        <ul class="mb-0">
                                            <li>Always use secure connections (SSL/TLS) in production</li>
                                            <li>Use strong, unique passwords</li>
                                            <li>Regularly update device firmware</li>
                                            <li>Monitor device activity for unusual behavior</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <h6 class="fw-semibold mb-2">Troubleshooting</h6>
                                        <ul class="mb-0">
                                            <li>Check device power and connectivity</li>
                                            <li>Verify credentials are correct</li>
                                            <li>Ensure firewall allows the required ports</li>
                                            <li>Check system logs for error messages</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <div class="alert alert-light">
                                <i class="ph-question me-2"></i>
                                <strong>Need Help?</strong> If you're having trouble with device registration, check the system logs or contact your administrator.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
