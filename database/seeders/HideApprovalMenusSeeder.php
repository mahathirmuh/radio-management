<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Takes the base app's "Approval Management" branch out of the sidebar.
 *
 * The approval module came with the base application and does not work in this one, so its
 * menus only lead to dead pages. The menu rows are kept and only the role grants are removed:
 * a branch without a grant is not shown, its pages answer 403 to everyone but Super Admin,
 * and ticking the menus again on the Role & Permission page brings them back.
 *
 * Idempotent. Run for an existing database by the migration
 * 2026_10_03_120000_hide_approval_menus, and for a new one by DatabaseSeeder.
 */
class HideApprovalMenusSeeder extends Seeder
{
    private const ROUTES = 'approval-management.%';

    public function run(): void
    {
        $menus = DB::table('menu')->where('route', 'like', self::ROUTES)->get(['id', 'parent_id']);
        if ($menus->isEmpty()) {
            return;
        }

        // The group above them goes too, but only when it holds nothing else.
        $groups = $menus->pluck('parent_id')->filter()->unique()->filter(
            fn ($groupId) => DB::table('menu')
                ->where('parent_id', $groupId)
                ->where(fn ($query) => $query->whereNull('route')->orWhere('route', 'not like', self::ROUTES))
                ->doesntExist()
        );
        $ids = $menus->pluck('id')->merge($groups)->map(fn ($id) => (int) $id)->unique()->values()->all();

        DB::transaction(function () use ($ids) {
            DB::table('menu_role_permission')->whereIn('menu_id', $ids)->delete();
            DB::table('menu_role')->whereIn('menu_id', $ids)->delete();
        });
    }
}
