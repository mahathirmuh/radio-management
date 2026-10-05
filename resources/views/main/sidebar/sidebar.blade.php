<!-- Sidebar -->
<aside class="navbar navbar-vertical navbar-expand-lg mdka-bg-gray-50" data-bs-theme="light">
    <div class="container-fluid overflow-x-hidden">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <h1 class="navbar-brand navbar-brand-autodark">
            <div class="d-flex flex-column gap-2 align-items-center">
                <a href="/" class="link-underline link-underline-opacity-0">
                    <img src="{{ asset('assets/img/branding/mcg-logo.png') }}" height="32" alt="Merdeka"
                        class="navbar-brand-image">
                    <img src="{{ asset('assets/img/branding/mbm-logo.png') }}" height="32" alt="Merdeka"
                        class="navbar-brand-image">

                    <img src="{{ asset('assets/img/branding/favicon.ico') }}" height="20" alt="Merdeka"
                        class="navbar-brand-image-small d-none">
                    <img src="{{ asset('assets/img/branding/mbm-logo-small.png') }}" height="24" alt="Merdeka"
                        class="navbar-brand-image-small d-none">
                </a>
            </div>
        </h1>

        <div class="navbar-nav flex-row d-lg-none">
            <div class="nav-item d-none d-lg-flex me-3">
                <div class="btn-list">
                    <!-- button -->
                </div>
            </div>
            <div class="d-none d-lg-flex">
                @include('main.navbar.component.notification')
            </div>
        </div>
        <div class="collapse navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav py-lg-3">
                @include('main.sidebar.menu')
                {{-- The top navbar with the user menu is hidden below 992px: keep a way to sign out. --}}
                <li class="nav-item d-lg-none">
                    <a class="nav-link" href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form-sidebar').submit();">
                        <span class="nav-link-icon"><i class="ti ti-logout"></i></span>
                        <span class="nav-link-title">Logout ({{ Auth::user()->name }})</span>
                    </a>
                    <form id="logout-form-sidebar" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </li>
            </ul>
        </div>
        <img class="sidebar-background" src="{{ asset('assets/img/branding/sidebar-background.png') }}" alt=""
            style="user-select: none; pointer-events: none;">
    </div>
</aside>

@push('javascript')
    <script>
        // Check localStorage for saved sidebar state on page load
        var savedState = localStorage.getItem('sidebarState');
        if (savedState === 'collapsed') {
            collapseSidebar();
        } else {
            expandSidebar();
        }

        $('#sidebarControl').on('click', function() {
            var navbar = $('.navbar-expand-lg');

            if (navbar.hasClass('collapsed')) {
                expandSidebar();
                localStorage.setItem('sidebarState', 'expanded');
            } else {
                collapseSidebar();
                localStorage.setItem('sidebarState', 'collapsed');
            }
        });

        function expandSidebar() {
            var navbar = $('.navbar-expand-lg');
            var navbarMd = $('.navbar-expand-md');
            var pageWrapper = $('.page-wrapper');

            navbar.removeClass('collapsed');
            navbar.css('width', ''); // Reset width
            navbar.css('overflow-y', 'auto'); // Scroll only when the menu actually overflows

            $('.navbar-nav').css('gap', '');
            $('.sidebar-control').removeClass('d-none');
            $('.navbar-brand-image').removeClass('d-none');
            $('.navbar-brand-image-small').addClass('d-none');

            $('.item-dropdown .item-sidebar').addClass('dropdown');
            $('.sidebar-item').css('padding-left',
                'calc(calc(calc(var(--tblr-page-padding) * 2)/ 2) + 1.75rem)');
            $('.nav-link-icon').css('padding-left', '0');
            $('.nav-link-icon').css('color', 'var(--tblr-icon-color)');
            $('.nav-link-icon').css('transform', 'scale(1)');

            // Only apply margin when screen width is large (lg)
            if (window.innerWidth >= 992) { // Check if screen width is not mobile
                navbarMd.css('margin-left', '15rem');
                pageWrapper.css('margin-left', '15rem');
            }
        }

        function collapseSidebar() {
            var navbar = $('.navbar-expand-lg');
            var navbarMd = $('.navbar-expand-md');
            var pageWrapper = $('.page-wrapper');

            navbar.addClass('collapsed');
            navbar.css('width', '80px'); // Shrink navbar width
            navbar.css('overflow-y', 'auto');

            $('.navbar-nav').css('gap', '0.5rem');
            $('.sidebar-control').addClass('d-none');
            $('.navbar-brand-image').addClass('d-none');
            $('.navbar-brand-image-small').removeClass('d-none');

            $('.item-dropdown .item-sidebar').removeClass('dropdown');
            $('.sidebar-item').css('padding-left', '15px');
            $('.nav-link-icon').css('padding-left', '10px');
            $('.nav-link-icon').css('color', 'var(--tblr-primary) !important');
            $('.nav-link-icon').css('transform', 'scale(1.2)');

            // Only apply margin when screen width is large (lg)
            if (window.innerWidth >= 992) { // Check if screen width is not mobile
                navbarMd.css('margin-left', '80px');
                pageWrapper.css('margin-left', '80px');
            }
        }
    </script>
@endpush
