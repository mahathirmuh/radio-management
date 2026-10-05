<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent seeder that adds the "Approval Inbox" navigation item under the
 * "Approval Management" group and grants it to every role (menu visibility is
 * driven by menu_role / guardRole()).
 *
 * Safe to run repeatedly on an existing database — it never wipes the menu
 * table and skips rows that already exist.
 *
 * NOT USED in MTI Radio Management: the approval module does not work in this application and
 * its menus are kept out of the sidebar (HideApprovalMenusSeeder). Running this seeder would
 * show "Approval Inbox" to every role again.
 */
class ApprovalInboxMenuSeeder extends Seeder
{
    public function run(): void
    {
        $route = 'approval-management.approval-inbox.index';
        $now = now();

        // Locate the Approval Management group (create it if somehow missing).
        $group = DB::table('menu')
            ->whereNull('parent_id')
            ->where('title', 'Approval Management')
            ->first();

        $groupId = $group->id ?? DB::table('menu')->insertGetId([
            'parent_id'  => null,
            'title'      => 'Approval Management',
            'route'      => null,
            'icon'       => 'mail-cog',
            'order'      => 4,
            'section'    => 'Settings',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Insert the Inbox item once.
        $menuId = DB::table('menu')->where('route', $route)->value('id');

        if (! $menuId) {
            $menuId = DB::table('menu')->insertGetId([
                'parent_id'  => $groupId,
                'title'      => 'Approval Inbox',
                'route'      => $route,
                'icon'       => 'inbox',
                'order'      => 0, // show first within the group
                'section'    => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Grant the menu (and its parent group) to every role, skipping existing pairs.
        $menuIds = [$groupId, $menuId];
        $roleIds = DB::table('roles')->pluck('id');

        $existingPairs = DB::table('menu_role')
            ->whereIn('menu_id', $menuIds)
            ->get(['menu_id', 'role_id'])
            ->map(fn ($row) => $row->menu_id . '-' . $row->role_id)
            ->flip();

        $rows = [];
        foreach ($roleIds as $roleId) {
            foreach ($menuIds as $mId) {
                if (isset($existingPairs[$mId . '-' . $roleId])) {
                    continue;
                }
                $rows[] = [
                    'menu_id'    => $mId,
                    'role_id'    => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table('menu_role')->insert($rows);
        }
    }
}
