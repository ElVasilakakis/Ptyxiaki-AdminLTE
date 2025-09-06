<div class="navbar navbar-sm navbar-footer border-top">
    <div class="container-fluid">
        <span>&copy; {{ date('Y') }} <a href="{{ url('/') }}">{{env('APP_FULL_NAME')}}</a></span>

        <ul class="nav">
            <li class="nav-item">
                <a href="#" class="navbar-nav-link navbar-nav-link-icon rounded">
                    <div class="d-flex align-items-center mx-md-1">
                        <i class="ph-lifebuoy"></i>
                        <span class="d-none d-md-inline-block ms-2">Support</span>
                    </div>
                </a>
            </li>
            <li class="nav-item ms-md-1">
                <a href="#" class="navbar-nav-link navbar-nav-link-icon rounded">
                    <div class="d-flex align-items-center mx-md-1">
                        <i class="ph-file-text"></i>
                        <span class="d-none d-md-inline-block ms-2">Docs</span>
                    </div>
                </a>
            </li>
            <li class="nav-item ms-md-1">
                <a href="{{ route('app.devices.index') }}" class="navbar-nav-link navbar-nav-link-icon text-primary bg-primary bg-opacity-10 fw-semibold rounded">
                    <div class="d-flex align-items-center mx-md-1">
                        <i class="ph-cpu"></i>
                        <span class="d-none d-md-inline-block ms-2">Devices</span>
                    </div>
                </a>
            </li>
        </ul>
    </div>
</div>
