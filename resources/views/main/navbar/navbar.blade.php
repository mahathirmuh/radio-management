<!-- Top bar -->
@php
    $me = Auth::user();
    $myRoles = $me->roles->pluck('name')->filter()->implode(', ');
@endphp
<header class="navbar navbar-expand-md d-none d-lg-flex d-print-none sticky-top rm-topbar">
    <div class="rm-topbar-inner">
        <button id="sidebarControl" type="button" class="rm-icon-btn is-ringed" aria-controls="sidebar-menu"
            aria-pressed="false" aria-label="{{ __('ui.topbar.toggle_sidebar') }}" title="{{ __('ui.topbar.toggle_sidebar') }}">
            <i class="ti ti-layout-sidebar" aria-hidden="true"></i>
        </button>
        {{-- The heading of the page (main.index); the path to it is under the bar. --}}
        <h1 class="rm-topbar-title">{!! $heading !!}</h1>
        <div class="rm-topbar-end">
            <span class="rm-topbar-company">{{ $me->company?->name }}</span>
            {{-- The language of the screens (App\Services\Language), as on the login page: ID | EN, the one in use marked. --}}
            <form method="POST" action="{{ route('language.switch') }}" class="rm-seg rm-topbar-lang" id="languageSwitch"
                aria-label="{{ __('ui.language.title') }}">
                @csrf
                @foreach (\App\Services\Language::NAMES as $code => $name)
                    <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" title="{{ $name }}" aria-label="{{ $name }}"
                        aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}" @class(['is-on' => app()->getLocale() === $code])>{{ strtoupper($code) }}</button>
                @endforeach
            </form>
            @include('main.navbar.notifications')
            <div class="dropdown">
                <button type="button" class="rm-user-btn" data-bs-toggle="dropdown" data-bs-offset="0,10" aria-expanded="false"
                    aria-label="{{ __('ui.topbar.user_menu') }}">
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
                        <i class="ti ti-bell" aria-hidden="true"></i> {{ __('ui.topbar.notifications') }}
                    </a>

                    <a href="{{ route('logout') }}" class="dropdown-item rm-menu-item is-danger"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="ti ti-logout" aria-hidden="true"></i> {{ __('ui.topbar.sign_out') }}
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
