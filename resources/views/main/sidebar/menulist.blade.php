@php
    $allowedMenuIds = Auth::user()->roles->flatMap->menus->pluck('id')->unique()->all();
    $rootMenus = \App\Models\UserManagement\Menu::whereNull('parent_id')->with('children')->orderBy('order')->get();
    $lastSection = null;
@endphp
@foreach ($rootMenus as $menu)
    @if ($menu->section && $menu->section !== $lastSection && $menu->isVisibleFor($allowedMenuIds))
        <li class="nav-item menu-section-header">
            <span class="subheader text-wrap sidebar-control">{{ $menu->section }}</span>
        </li>
        @php($lastSection = $menu->section)
    @endif
    @include('main.sidebar.menu-item', ['menu' => $menu, 'allowedMenuIds' => $allowedMenuIds])
@endforeach
