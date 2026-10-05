<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the HO / Site / Employee roles that came with the base-app seed and are
 * not used by Radio Management. Super Admin, Admin Radio and Viewer Radio stay.
 *
 * A fresh install never has these rows (RolesTableSeeder no longer creates them),
 * so there this migration does nothing.
 */
return new class extends Migration
{
    private const ROLES = [
        'Admin HO - MCG',
        "Employee's",
        'Admin Site - MCG',
        'Admin Site - DNS',
        'Admin Site - BSI',
        'Admin Site - WETAR',
        'Admin Site - MBM',
        'Admin Site - MMS',
        'Admin Site - MTI',
        'Admin Site - PANI',
        'Admin Site - SCM',
        'Admin Site - SM',
        'Admin Site - MTI Beijing',
    ];

    public function up(): void
    {
        $ids = DB::table('roles')->whereIn('name', self::ROLES)->where('id', '!=', 1)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        // Never strand a user: someone holding one of these roles has to be moved first.
        $assigned = DB::table('role_user')->whereIn('role_id', $ids)->count();
        if ($assigned > 0) {
            throw new RuntimeException(
                "{$assigned} user assignment(s) still point at the base roles being removed. "
                . 'Give those users another role in User Management, then run the migration again.'
            );
        }

        DB::transaction(function () use ($ids) {
            DB::table('menu_role_permission')->whereIn('role_id', $ids)->delete();
            DB::table('menu_role')->whereIn('role_id', $ids)->delete();
            DB::table('permission_role')->whereIn('role_id', $ids)->delete();
            // Approval steps may name a role as approver (nullable, NO ACTION on SQL Server).
            foreach (['approval_steps', 'approval_step_instances'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'role_id')) {
                    DB::table($table)->whereIn('role_id', $ids)->update(['role_id' => null]);
                }
            }
            DB::table('roles')->whereIn('id', $ids)->delete();
        });
    }

    public function down(): void
    {
        // The removed rows were seed data. To get them back, run the base app's RolesTableSeeder.
    }
};
