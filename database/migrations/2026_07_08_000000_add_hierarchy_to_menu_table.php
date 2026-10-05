<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('menu', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->string('icon')->nullable()->after('route');
            $table->unsignedInteger('order')->default(0)->after('icon');
            $table->string('section')->nullable()->after('order');

            // SQL Server does not allow a cascading action on a self-referencing key.
            $parent = $table->foreign('parent_id')->references('id')->on('menu');
            if (DB::getDriverName() !== 'sqlsrv') {
                $parent->nullOnDelete();
            }
        });

        // Backfill the existing rows with route/icon/order.
        DB::table('menu')->where('id', 1)->update([
            'route' => 'dashboard.index',
            'icon' => 'dashboard',
            'order' => 1,
        ]);
        DB::table('menu')->where('id', 98)->update([
            'route' => 'user-management.user-registered.index',
            'icon' => 'users',
            'order' => 1,
        ]);
        DB::table('menu')->where('id', 99)->update([
            'route' => 'user-management.role-permission.index',
            'icon' => 'shield-lock',
            'order' => 2,
        ]);

        $now = now();

        $inboxId = DB::table('menu')->insertGetId([
            'title' => 'Inbox',
            'icon' => 'mail',
            'order' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userManagementGroupId = DB::table('menu')->insertGetId([
            'title' => 'User Management',
            'icon' => 'shield-chevron',
            'order' => 3,
            'section' => 'User',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('menu')->whereIn('id', [98, 99])->update([
            'parent_id' => $userManagementGroupId,
        ]);

        $approvalManagementGroupId = DB::table('menu')->insertGetId([
            'title' => 'Approval Management',
            'icon' => 'mail-cog',
            'order' => 4,
            'section' => 'Settings',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $approvalFlowsId = DB::table('menu')->insertGetId([
            'parent_id' => $approvalManagementGroupId,
            'title' => 'Approval Flows',
            'route' => 'approval-management.approval-flows.index',
            'icon' => 'users-group',
            'order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $approvalDelegateId = DB::table('menu')->insertGetId([
            'parent_id' => $approvalManagementGroupId,
            'title' => 'Delegate Approval',
            'route' => 'approval-management.approval-delegate.index',
            'icon' => 'user-search',
            'order' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $approvalTakeoverId = DB::table('menu')->insertGetId([
            'parent_id' => $approvalManagementGroupId,
            'title' => 'Takeover Approval',
            'route' => 'approval-management.approval-takeover.index',
            'icon' => 'users',
            'order' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Preserve current behaviour: Dashboard/Inbox/Approval Management were never
        // guarded in the blade before this change, so every existing role could see
        // them regardless of menu_role data. Grant them explicitly here so switching
        // the sidebar to guardRole()-driven rendering doesn't hide navigation for
        // any existing role. User Registered/Role & Permission grants are untouched.
        $newlyGuardedMenuIds = [
            1, // Dashboard
            $inboxId,
            $userManagementGroupId,
            $approvalManagementGroupId,
            $approvalFlowsId,
            $approvalDelegateId,
            $approvalTakeoverId,
        ];

        $roleIds = DB::table('roles')->pluck('id');

        $existingPairs = DB::table('menu_role')
            ->whereIn('menu_id', $newlyGuardedMenuIds)
            ->get(['menu_id', 'role_id'])
            ->map(fn ($row) => $row->menu_id . '-' . $row->role_id)
            ->flip();

        $menuRoleRows = [];
        foreach ($roleIds as $roleId) {
            foreach ($newlyGuardedMenuIds as $menuId) {
                if (isset($existingPairs[$menuId . '-' . $roleId])) {
                    continue;
                }

                $menuRoleRows[] = [
                    'menu_id' => $menuId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($menuRoleRows)) {
            DB::table('menu_role')->insert($menuRoleRows);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $titles = ['Inbox', 'User Management', 'Approval Management', 'Approval Flows', 'Delegate Approval', 'Takeover Approval'];
        $ids = DB::table('menu')->whereIn('title', $titles)->pluck('id');

        DB::table('menu_role')->whereIn('menu_id', $ids)->orWhere('menu_id', 1)->delete();
        DB::table('menu')->whereIn('id', $ids)->delete();

        DB::table('menu')->whereIn('id', [1, 98, 99])->update([
            'route' => null,
            'icon' => null,
            'order' => 0,
            'parent_id' => null,
        ]);

        Schema::table('menu', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'icon', 'order', 'section']);
        });
    }
};
