@php
    $children = $menu->children;
    $hasChildren = $children->isNotEmpty();
    $routes = \Illuminate\Support\Facades\Route::getFacadeRoot();
    $hasRoute = fn ($item) => $item->route && $routes->has($item->route);
    // A page of a menu is any route of its menu key (the route name less its last part: CheckMenuPermission), so the
    // detail of a radio keeps "Register Radio" marked.
    $isOn = fn ($item) => $hasRoute($item)
        && request()->routeIs($item->route, \Illuminate\Support\Str::beforeLast($item->route, '.') . '.*');
    $menuHref = $hasRoute($menu) ? route($menu->route) : '#';
    $isActive = $isOn($menu);
    $isDescendantActive = $hasChildren && $children->contains(fn ($child) => $isOn($child));
    $isRoot = is_null($menu->parent_id);
    $count = $hasRoute($menu) && isset($menuCount) ? $menuCount($menu->route) : null;
    $showCount = $count && $count['count'] > 0;
@endphp
{{-- A leaf menu without a route would only be a dead "#" link (e.g. the base "Inbox" entry). --}}
@if ($menu->isVisibleFor($allowedMenuIds) && ($hasChildren || $menu->route))
    @if ($hasChildren)
        @if ($isRoot)
            @php
                $first = $children->first(fn ($child) => $child->isVisibleFor($allowedMenuIds) && $hasRoute($child));
            @endphp
            <li class="rm-nav-item rm-nav-group {{ $isDescendantActive ? 'is-open has-active' : '' }}" id="rm-navgroup-{{ $menu->id }}">
                <button type="button" class="rm-nav-link" data-rm-group="{{ $menu->id }}"
                    data-rm-first="{{ $first ? route($first->route) : '' }}" data-rm-tip="{{ $menu->title }}"
                    aria-expanded="{{ $isDescendantActive ? 'true' : 'false' }}" aria-controls="rm-navkids-{{ $menu->id }}">
                    <span class="rm-nav-icon"><i class="ti ti-{{ $menu->icon ?: 'folder' }}"></i></span>
                    <span class="rm-nav-label">{{ $menu->title }}</span>
                    <i class="ti ti-chevron-down rm-nav-chevron" aria-hidden="true"></i>
                </button>
                <div class="rm-nav-kids" id="rm-navkids-{{ $menu->id }}">
                    <div class="rm-nav-kids-inner">
                        @foreach ($children as $child)
                            @include('main.sidebar.menu-item', ['menu' => $child, 'allowedMenuIds' => $allowedMenuIds])
                        @endforeach
                    </div>
                </div>
            </li>
        @else
            {{-- Nested sub-menu: a heading followed by its items. --}}
            <span class="rm-nav-subhead">{{ $menu->title }}</span>
            @foreach ($children as $child)
                @include('main.sidebar.menu-item', ['menu' => $child, 'allowedMenuIds' => $allowedMenuIds])
            @endforeach
        @endif
    @else
        @if ($isRoot)
            <li class="rm-nav-item">
                <a class="rm-nav-link {{ $isActive ? 'is-active' : '' }}" href="{{ $menuHref }}" data-rm-tip="{{ $menu->title }}"
                    @if ($isActive) aria-current="page" @endif>
                    <span class="rm-nav-icon"><i class="ti ti-{{ $menu->icon ?: 'point' }}"></i></span>
                    <span class="rm-nav-label">{{ $menu->title }}</span>
                    @if ($showCount)
                        <span class="rm-badge {{ $count['color'] }} rm-nav-count" title="{{ $count['title'] }}">{{ $count['count'] }}</span>
                        <span class="rm-nav-dot {{ $count['color'] }}" aria-hidden="true"></span>
                    @endif
                </a>
            </li>
        @else
            <a class="rm-nav-link rm-nav-kid {{ $isActive ? 'is-active' : '' }}" href="{{ $menuHref }}"
                @if ($isActive) aria-current="page" @endif>
                <span class="rm-nav-label">{{ $menu->title }}</span>
                @if ($showCount)
                    <span class="rm-badge {{ $count['color'] }} rm-nav-count" title="{{ $count['title'] }}">{{ $count['count'] }}</span>
                @endif
            </a>
        @endif
    @endif
@endif
