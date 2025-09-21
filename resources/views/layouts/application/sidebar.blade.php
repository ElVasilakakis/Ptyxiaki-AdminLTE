<div class="sidebar sidebar-dark sidebar-main sidebar-expand-lg">
    <!-- Sidebar content -->
    <div class="sidebar-content">

        <!-- Sidebar header -->
        <div class="sidebar-section">
            <div class="sidebar-section-body d-flex justify-content-center">
                <h5 class="sidebar-resize-hide flex-grow-1 my-auto">Navigation</h5>

                <div>
                    <button type="button"
                        class="btn btn-flat-white btn-icon btn-sm rounded-pill border-transparent sidebar-control sidebar-main-resize d-none d-lg-inline-flex">
                        <i class="ph-arrows-left-right"></i>
                    </button>

                    <button type="button"
                        class="btn btn-flat-white btn-icon btn-sm rounded-pill border-transparent sidebar-mobile-main-toggle d-lg-none">
                        <i class="ph-x"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- /sidebar header -->

        <div class="sidebar-section">
            <ul class="nav nav-sidebar" data-nav-type="accordion">

                <li class="nav-item-header pt-0">
                    <div class="text-uppercase fs-sm lh-sm opacity-50 sidebar-resize-hide">Main</div>
                    <i class="ph-dots-three sidebar-resize-show"></i>
                </li>
                <li class="nav-item">
                    <a href="{{ route('app.dashboard') }}" class="nav-link {{ request()->routeIs('app.dashboard') ? 'active' : '' }}">
                        <i class="ph-house"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-item-header">
                    <div class="text-uppercase fs-sm lh-sm opacity-50 sidebar-resize-hide">Application</div>
                    <i class="ph-dots-three sidebar-resize-show"></i>
                </li>
                <li class="nav-item">
                    <a href="{{ route('app.lands.index') }}" class="nav-link {{ request()->routeIs('app.lands.*') ? 'active' : '' }}">
                        <i class="ph-map-pin-line"></i>
                        <span>Lands</span>
                        {{-- <span class="badge bg-primary align-self-center rounded-pill ms-auto">5</span> --}}
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('app.devices.index') }}" class="nav-link {{ request()->routeIs('app.devices.*') ? 'active' : '' }}">
                        <i class="ph-cpu"></i>
                        <span>Devices</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('app.sensors.index') }}" class="nav-link {{ request()->routeIs('app.sensors.*') ? 'active' : '' }}">
                        <i class="ph-thermometer-hot"></i>
                        <span>Sensors</span>
                    </a>
                </li>

                <li class="nav-item-header">
                    <div class="text-uppercase fs-sm lh-sm opacity-50 sidebar-resize-hide">Network</div>
                    <i class="ph-dots-three sidebar-resize-show"></i>
                </li>
                <li class="nav-item-header">
                    <div class="text-uppercase fs-sm lh-sm opacity-50 sidebar-resize-hide">Documentation</div>
                    <i class="ph-dots-three sidebar-resize-show"></i>
                </li>
                <li class="nav-item">
                    <a href="{{route('app.documentation')}}" class="nav-link {{ request()->routeIs('app.documentation') ? 'active' : '' }}">
                        <i class="ph-file-code"></i>
                        <span>How To</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{route('app.lorawan-guide')}}" class="nav-link {{ request()->routeIs('app.lorawan-guide') ? 'active' : '' }}">
                        <i class="ph-broadcast"></i>
                        <span>How to The Things Stack LoRaWAN</span>
                    </a>
                </li>

            </ul>
        </div>

    </div>
    <!-- /sidebar content -->
</div>
