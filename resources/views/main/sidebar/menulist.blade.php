@php
    $allowedMenuIds = Auth::user()->roles->flatMap->menus->pluck('id')->unique()->all();
    $rootMenus = \App\Models\UserManagement\Menu::whereNull('parent_id')->with('children')->orderBy('order')->get();
    $lastSection = null;
    // The number next to an entry: what waits for somebody on that page. Asked only for an entry that is shown.
    $menuCount = function (?string $route): ?array {
        if ($route === 'documents.edit-approvals.index' && \Modules\SharePointDocs\Services\CellEdits::ready()) {
            $count = \Modules\SharePointDocs\Models\EditRequest::where('status', \Modules\SharePointDocs\Models\EditRequest::PENDING)->count();

            return ['count' => $count, 'color' => 'blue', 'title' => "{$count} pengajuan menunggu"];
        }
        if ($route === 'radio-management.attention.index') {
            $count = \Modules\RadioManagement\Services\AttentionInbox::openCount();

            return ['count' => $count, 'color' => 'red', 'title' => "{$count} item perlu perhatian"];
        }

        return null;
    };
@endphp
@foreach ($rootMenus as $menu)
    @if ($menu->section && $menu->section !== $lastSection && $menu->isVisibleFor($allowedMenuIds))
        <li class="rm-nav-section" role="presentation"><span>{{ $menu->section }}</span></li>
        @php($lastSection = $menu->section)
    @endif
    @include('main.sidebar.menu-item', ['menu' => $menu, 'allowedMenuIds' => $allowedMenuIds, 'menuCount' => $menuCount])
@endforeach
