<!--
    Sidebar items are rendered dynamically from the `menu` table (see App\Models\UserManagement\Menu),
    guarded per item via Auth::user()->guardRole($menuId) / Menu::isVisibleFor().
-->
@include('main.sidebar.menulist')