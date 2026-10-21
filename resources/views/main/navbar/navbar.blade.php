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
            aria-pressed="false" aria-label="{{ __('ui.topbar.toggle_sidebar') }}" title="{{ __('ui.topbar.toggle_sidebar') }}">
            <i class="ti ti-layout-sidebar" aria-hidden="true"></i>
        </button>
        <nav class="rm-topbar-title rm-topbar-crumbs" aria-label="{{ __('ui.topbar.breadcrumb') }}">
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
                    {{-- The language of the screens (App\Services\Language): the one in use is ticked. --}}
                    <form method="POST" action="{{ route('language.switch') }}" class="rm-menu-group" id="languageMenu">
                        @csrf
                        <div class="rm-menu-label" id="languageMenuLabel">{{ __('ui.language.title') }}</div>
                        @foreach (\App\Services\Language::NAMES as $code => $name)
                            <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}"
                                class="dropdown-item rm-menu-item{{ app()->getLocale() === $code ? ' is-on' : '' }}"
                                aria-describedby="languageMenuLabel" aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}">
                                <i class="ti ti-check rm-menu-tick" aria-hidden="true"></i> {{ $name }}
                            </button>
                        @endforeach
                    </form>
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
