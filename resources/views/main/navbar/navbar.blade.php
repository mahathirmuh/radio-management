 <!-- Navbar -->
 <header class="navbar navbar-expand-md bg-white d-none d-lg-flex d-print-none sticky-top border-bottom">
     <div class="container-xl">
         <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu"
             aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation">
             <span class="navbar-toggler-icon"></span>
         </button>
         <div class="d-flex align-item-center gap-2">
             <button id="sidebarControl" class="btn bg-secondary-lt p-1 d-none d-md-flex align-items-center">
                 <i class="ti ti-layout-sidebar text-dark h2 mb-0">
                 </i>
             </button>
             <div class="collapse navbar-collapse" id="navbar-menu">
                 <div class="fw-bolder mdka-text-brand-500 h3 mb-0">

                     @yield('page-title')

                 </div>
             </div>
         </div>
         <div class="navbar-nav flex-row order-md-last">
             <div class="d-none d-md-flex">
                 <p class="mt-3 text-primary me-2" style="font-size: 13px">
                     <strong>{{ Auth::user()->company->name }}</strong>
                 </p>
                 @include('main.navbar.notifications')

                 <div class="nav-item dropdown">
                     <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown"
                         aria-label="Open user menu">
                         <span class="avatar avatar-sm rounded-circle position-relative"
                             style="background-image: url({{ asset('assets/img/avatar/1.png') }})">
                             <span
                                 class="status-dot status-dot-animated bg-green position-absolute bottom-0 end-0 d-block"></span>
                         </span>
                         <div class="d-none d-xl-block ps-2">
                             <div>{{ Auth::user()->name }}</div>
                             <div class="mt-1 small text-muted">{{ Auth::user()->email }}</div>
                         </div>
                     </a>
                     <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                         <a href="{{ route('logout') }}" class="dropdown-item"
                             onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                             <i class="icon-logout"></i> {{ __('Logout') }}
                         </a>
                         <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                             @csrf
                         </form>
                     </div>
                 </div>
             </div>
         </div>
     </div>
 </header>
