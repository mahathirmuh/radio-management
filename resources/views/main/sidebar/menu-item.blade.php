@php
    $children = $menu->children;
    $hasChildren = $children->isNotEmpty();
    $menuHref = $menu->route && \Illuminate\Support\Facades\Route::has($menu->route) ? route($menu->route) : '#';
    $isActive = $menu->route && \Illuminate\Support\Facades\Route::has($menu->route) && request()->routeIs($menu->route);
    $isDescendantActive = $hasChildren && $children->contains(
        fn ($child) => $child->route && \Illuminate\Support\Facades\Route::has($child->route) && request()->routeIs($child->route)
    );
    // Top-level items live inside the sidebar <ul> and must be <li>.
    // Sub-items live inside a .dropdown-menu and must be plain .dropdown-item anchors —
    // wrapping them in <li> nests <li> inside <li>, which the browser force-closes,
    // ejecting the whole submenu (and everything after it) out of the sidebar.
    $isRoot = is_null($menu->parent_id);
@endphp
{{-- A leaf menu without a route would only be a dead "#" link (e.g. the base "Inbox" entry). --}}
@if ($menu->isVisibleFor($allowedMenuIds) && ($hasChildren || $menu->route))
    @if ($hasChildren)
        @if ($isRoot)
            <li class="nav-item dropdown {{ $isDescendantActive ? 'active-mdka-nav' : '' }}">
                <a class="nav-link dropdown-toggle {{ $isDescendantActive ? 'active show' : '' }}"
                    href="#navbar-menu-{{ $menu->id }}" data-bs-toggle="dropdown" data-bs-auto-close="false"
                    role="button" aria-expanded="false">
                    @if ($menu->icon)
                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                            <i class="ti ti-{{ $menu->icon }}"></i>
                        </span>
                    @endif
                    <span class="nav-link-title sidebar-control">
                        {{ $menu->title }}
                    </span>
                </a>
                <div class="dropdown-menu {{ $isDescendantActive ? 'show' : '' }}">
                    <div class="dropdown-menu-columns">
                        <div class="dropdown-menu-column">
                            @foreach ($children as $child)
                                @include('main.sidebar.menu-item', ['menu' => $child, 'allowedMenuIds' => $allowedMenuIds])
                            @endforeach
                        </div>
                    </div>
                </div>
            </li>
        @else
            {{-- Nested sub-menu: render a heading followed by its items, all as flat dropdown entries. --}}
            <span class="dropdown-header sidebar-control">{{ $menu->title }}</span>
            @foreach ($children as $child)
                @include('main.sidebar.menu-item', ['menu' => $child, 'allowedMenuIds' => $allowedMenuIds])
            @endforeach
        @endif
    @else
        @if ($isRoot)
            <li class="nav-item {{ $isActive ? 'active-mdka-nav' : '' }}">
                <a class="nav-link {{ $isActive ? 'active-mdka-nav' : '' }}" href="{{ $menuHref }}">
                    @if ($menu->icon)
                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                            <i class="ti ti-{{ $menu->icon }}"></i>
                        </span>
                    @endif
                    <span class="nav-link-title sidebar-control">
                        {{ $menu->title }}
                    </span>
                </a>
            </li>
        @else
            <a class="dropdown-item sidebar-item {{ $isActive ? 'active-mdka-nav' : '' }}" href="{{ $menuHref }}">
                @if ($menu->icon)
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                        <i class="ti ti-{{ $menu->icon }}"></i>
                    </span>
                @endif
                <span class="nav-link-title sidebar-control">
                    {{ $menu->title }}
                </span>
            </a>
        @endif
    @endif
@endif
