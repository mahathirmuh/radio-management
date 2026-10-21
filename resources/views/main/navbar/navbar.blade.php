<!-- Top bar -->
@php
    $me = Auth::user();
    $myRoles = $me->roles->pluck('name')->filter()->implode(', ');
    // The path to this page: what the page calls itself (@section('crumb'), else its title) comes last when it is deeper
    // than its entry of the sidebar. (A section is kept escaped.)
    $pageName = html_entity_decode(trim($__env->yieldContent('crumb') ?: $__env->yieldContent('page-title')), ENT_QUOTES | ENT_HTML5);
    $crumbs = \App\Services\Breadcrumbs::for(request(), $me, $pageName);
@endphp
<header class="navbar navbar-expand-md d-none d-lg-flex d-print-none sticky-top rm-topbar">
    <div class="rm-topbar-inner">
        <button id="sidebarControl" type="button" class="rm-icon-btn is-ringed" aria-controls="sidebar-menu"
            aria-pressed="false" aria-label="Lebarkan atau ciutkan menu" title="Lebarkan atau ciutkan menu">
            <i class="ti ti-layout-sidebar" aria-hidden="true"></i>
        </button>
        <nav class="rm-topbar-title rm-topbar-crumbs" aria-label="Breadcrumb">
            <ol>
                @foreach ($crumbs as $crumb)
                    <li>
                        @unless ($loop->first)
                            <i class="ti ti-chevron-right" aria-hidden="true"></i>
                        @endunless
                        @if ($crumb['url'] !== null)
                            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                        @else
                            <span @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
        <div class="rm-topbar-end">
            <span class="rm-topbar-company">{{ $me->company?->name }}</span>
            @include('main.navbar.notifications')
            <div class="dropdown">
                <button type="button" class="rm-user-btn" data-bs-toggle="dropdown" data-bs-offset="0,10" aria-expanded="false"
                    aria-label="Menu pengguna">
                    <img src="{{ asset('assets/img/avatar/1.png') }}" alt="" class="rm-avatar" width="32" height="32">
                    <span class="rm-user-text">
                        <span class="rm-user-name">{{ $me->name }}</span>
                        @if ($myRoles !== '')
                            <span class="rm-user-role">{{ $myRoles }}</span>
                        @endif
                    </span>
                </button>
                <div class="dropdown-menu dropdown-menu-end rm-menu rm-user-menu">
                    <div class="rm-menu-head">
                        <div class="rm-menu-head-name">{{ $me->name }}</div>
                        <div class="rm-menu-head-mail">{{ $me->email }}</div>
                    </div>
                    <a href="{{ route('notifications.index') }}" class="dropdown-item rm-menu-item">
                        <i class="ti ti-bell" aria-hidden="true"></i> Notifikasi
                    </a>
                    <a href="{{ route('logout') }}" class="dropdown-item rm-menu-item is-danger"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="ti ti-logout" aria-hidden="true"></i> Keluar
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
