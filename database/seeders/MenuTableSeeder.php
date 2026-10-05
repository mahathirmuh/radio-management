<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MenuTableSeeder extends Seeder
{
    /**
     * Seed the menu table with the full navigation hierarchy.
     *
     * Roots are inserted before their children so the self-referencing
     * `parent_id` foreign key resolves within the single insert.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('menu')->delete();

        $now = '2024-03-19 07:48:52';

        \DB::table('menu')->insert(array(
            // --- Root menus ---
            array(
                'id' => 1,
                'parent_id' => null,
                'title' => 'Dashboard',
                'route' => 'dashboard.index',
                'icon' => 'dashboard',
                'order' => 1,
                'section' => null,
                'created_at' => $now,
                'updated_at' => null,
            ),
            array(
                'id' => 100,
                'parent_id' => null,
                'title' => 'Inbox',
                'route' => null,
                'icon' => 'mail',
                'order' => 2,
                'section' => null,
                'created_at' => $now,
                'updated_at' => null,
            ),
            array(
                'id' => 101,
                'parent_id' => null,
                'title' => 'User Management',
                'route' => null,
                'icon' => 'shield-chevron',
                'order' => 3,
                'section' => 'User',
                'created_at' => $now,
                'updated_at' => null,
            ),
            array(
                'id' => 102,
                'parent_id' => null,
                'title' => 'Approval Management',
                'route' => null,
                'icon' => 'mail-cog',
                'order' => 4,
                'section' => 'Settings',
                'created_at' => $now,
                'updated_at' => null,
            ),

            // --- User Management children ---
            array(
                'id' => 98,
                'parent_id' => 101,
                'title' => 'User Registered',
                'route' => 'user-management.user-registered.index',
                'icon' => null,
                'order' => 1,
                'section' => null,
                'created_at' => $now,
                'updated_at' => null,
            ),
            array(
                'id' => 99,
                'parent_id' => 101,
                'title' => 'Role & Permission',
                'route' => 'user-management.role-permission.index',
                'icon' => null,
                'order' => 2,
                'section' => null,
                'created_at' => $now,
                'updated_at' => null,
            ),

            // --- Approval Management children ---
            array(
                'id' => 106,
                'parent_id' => 102,
                'title' => 'Approval Inbox',
                'route' => 'approval-management.approval-inbox.index',
                'icon' => 'inbox',
                'order' => 0,
                'section' => null,
                'created_at' => $now,
                'updated_at' => null,
            ),
            array(
                'id' => 103,
                'parent_id' => 102,
                'title' => 'Approval Flows',
                'route' => 'approval-management.approval-flows.index',
                'icon' => 'users-group',
                'order' => 1,
                'section' => null,
                'created_at' => $now,
                'updated_at' => null,
            ),
            array(
                'id' => 104,
                'parent_id' => 102,
                'title' => 'Delegate Approval',
                'route' => 'approval-management.approval-delegate.index',
                'icon' => 'user-search',
                'order' => 2,
                'section' => null,
                'created_at' => $now,
                'updated_at' => null,
            ),
            array(
                'id' => 105,
                'parent_id' => 102,
                'title' => 'Takeover Approval',
                'route' => 'approval-management.approval-takeover.index',
                'icon' => 'users',
                'order' => 3,
                'section' => null,
                'created_at' => $now,
                'updated_at' => null,
            ),
        ));
    }
}
