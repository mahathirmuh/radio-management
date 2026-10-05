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
                 <div class="nav-item dropdown d-none d-md-flex me-3">
                     <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1"
                         aria-label="Show notifications">
                         <!-- Download SVG icon from http://tabler-icons.io/i/bell -->
                         <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                             viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                             stroke-linecap="round" stroke-linejoin="round">
                             <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                             <path
                                 d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
                             <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
                         </svg>
                         <span id="hasNotification" class="badge bg-red d-none"></span>
                     </a>
                     <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end dropdown-menu-card">
                         <div class="card ">
                             <div class="card-header p-3">
                                 <h4 class="mb-0">Notification</h4>
                             </div>
                             <div id="notificationInfoData" class="list-group list-group-flush list-group-hoverable">
                                 <div class="list-group-item p-3 text-truncate">No notifications available</div>
                             </div>
                             <div class="card-footer border-top text-center p-3">
                                 <h6 class="text-info mb-0"><a href="#">See All
                                         Notifications</a>
                                     </h4>
                             </div>
                         </div>
                     </div>
                 </div>

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
