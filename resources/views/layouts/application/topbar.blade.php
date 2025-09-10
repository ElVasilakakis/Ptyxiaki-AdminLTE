<div class="navbar navbar-dark navbar-expand-lg navbar-static border-bottom border-bottom-white border-opacity-10">
    <div class="container-fluid">
        <div class="d-flex d-lg-none me-2">
            <button type="button" class="navbar-toggler sidebar-mobile-main-toggle rounded">
                <i class="ph-list"></i>
            </button>
        </div>

        <div class="navbar-brand">
            <a href="index.html" class="d-inline-flex align-items-center">
                <img src="{{ asset('assets/images/logo_icon.svg') }}" alt="">
                <img src="{{ asset('assets/images/logo_text_light.svg') }}" class="d-none d-sm-inline-block h-16px ms-3" alt="">
            </a>
        </div>

        <div class="d-lg-none ms-2">
            <button class="navbar-toggler collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-mobile" aria-expanded="false">
                <i class="ph-squares-four"></i>
            </button>
        </div>

        <div class="navbar-collapse order-2 order-lg-1 collapse" id="navbar-mobile" style="">
            <ul class="navbar-nav mt-2 mt-lg-0">
                <li class="nav-item">
                    <a href="{{ route('app.documentation') }}" class="navbar-nav-link rounded">
                        <i class="ph-book-open me-2"></i>How to Register Devices
                    </a>
                </li>
            </ul>
        </div>

        <ul class="nav gap-sm-2 order-1 order-lg-2 ms-auto">
            <li class="nav-item dropdown">
                <a href="#" class="navbar-nav-link navbar-nav-link-icon rounded" data-bs-toggle="dropdown" id="notificationDropdown">
                    <i class="ph-bell"></i>
                    <span class="badge bg-yellow text-black position-absolute top-0 end-0 translate-middle-top zindex-1 rounded-pill mt-1 me-1" id="notificationBadge" style="display: none;">0</span>
                </a>
                
                <div class="dropdown-menu dropdown-menu-end" style="width: 350px; max-height: 400px; overflow-y: auto;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Notifications</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="markAllReadBtn" style="display: none;">
                            Mark all read
                        </button>
                    </div>
                    <div class="dropdown-divider"></div>
                    
                    <div id="notificationsList">
                        <div class="dropdown-item-text text-center py-3" id="noNotifications">
                            <i class="ph-bell-slash text-muted mb-2 d-block" style="font-size: 2rem;"></i>
                            <span class="text-muted">No notifications</span>
                        </div>
                    </div>
                    
                    <div class="dropdown-divider" id="notificationsDivider" style="display: none;"></div>
                    <div class="dropdown-item text-center" id="notificationsFooter" style="display: none;">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearReadBtn">
                            Clear read notifications
                        </button>
                    </div>
                </div>
            </li>
            <li class="nav-item">
                <a href="#" class="navbar-nav-link navbar-nav-link-icon rounded">
                    <i class="ph-chats"></i>
                </a>
            </li>
            <li class="nav-item nav-item-dropdown-lg dropdown">
                <a href="#" class="navbar-nav-link align-items-center rounded p-1" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="status-indicator-container">
                        <img src="{{ asset('assets/images/demo/users/face11.jpg') }}" class="w-32px h-32px rounded" alt="">
                        <span class="status-indicator bg-success"></span>
                    </div>
                    <span class="d-none d-lg-inline-block mx-lg-2">{{ Auth::user()->name }}</span>
                </a>

                <div class="dropdown-menu dropdown-menu-end">
                    <div class="dropdown-header">
                        <div class="fw-semibold">{{ Auth::user()->name }}</div>
                        <div class="text-muted">{{ Auth::user()->email }}</div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="ph-user me-2"></i>
                        My Profile
                    </a>
                    <a href="#" class="dropdown-item">
                        <i class="ph-gear me-2"></i>
                        Account Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <form action="{{ route('auth.logout') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="ph-sign-out me-2"></i>
                            Logout
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
</div>
