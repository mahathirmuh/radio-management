<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // The base seeders start with DELETE and insert fixed ids: they are for an empty database.
        // Run again on a database in use, they would wipe its menus, roles and permissions.
        // (`permissions` is filled by the base seed only; `menu` also gets rows from a migration.)
        if (DB::table('permissions')->exists()) {
            $this->command?->warn('Base data is already there and is left alone. Only the radio master data and menus are refreshed.');
            $this->call(RadioMasterSeeder::class);
            $this->call(RadioMenuSeeder::class);
            // (Added when missing: a database whose migrations ran before it had the radio menus.)
            $this->call(AttentionMenuSeeder::class);
            $this->call(ServiceMenuSeeder::class);
            $this->call(OpnameMenuSeeder::class);
            $this->call(SnipeItMenuSeeder::class);

            return;
        }

        // $this->call(ActivityLogsTableSeeder::class);
        // $this->call(AppsTableSeeder::class);
        $this->callWithIdentity(SiteTableSeeder::class, 'site');
        $this->callWithIdentity(RefIndustryCategoriesTableSeeder::class, 'ref_industry_categories');
        $this->callWithIdentity(CompaniesTableSeeder::class, 'companies');
        $this->callWithIdentity(ContractorTableSeeder::class, 'contractor');
        // $this->call(ErrorLogsTableSeeder::class);
        // $this->call(FailedJobsTableSeeder::class);
        // $this->call(JobsTableSeeder::class);
        // $this->call(MigrationsTableSeeder::class);
        // $this->call(NotificationsTableSeeder::class);
        // $this->call(PasswordResetTokensTableSeeder::class);
        // $this->call(PasswordResetsTableSeeder::class);
        // $this->call(PersonalAccessTokensTableSeeder::class);
        $this->callWithIdentity(MenuTableSeeder::class, 'menu');
        $this->callWithIdentity(RolesTableSeeder::class, 'roles');
        $this->callWithIdentity(PermissionsTableSeeder::class, 'permissions');
        $this->call(PermissionRoleTableSeeder::class);
        $this->call(MenuRoleTableSeeder::class);
        // No accounts are seeded. UsersTableSeeder / RoleUserTableSeeder come from the base
        // application: five accounts of other people, four of them Super Admin, with password
        // hashes from that application. The first account is created with
        // `php artisan user:register <email>`; the others in User Management.
        // $this->callWithIdentity(UsersTableSeeder::class, 'users');
        // $this->callWithIdentity(RoleUserTableSeeder::class, 'role_user');
        // $this->call(AppsUserTableSeeder::class);

        // Radio Management
        $this->call(RadioMasterSeeder::class);
        $this->call(RadioMenuSeeder::class);
        // The signatures of the people at ICT (a page of the group "Master Data Radio", which the seeder above makes).
        $this->call(ESignMenuSeeder::class);
        // SharePoint documents (the menu table was emptied above, after the migrations that add them).
        $this->call(CofScanMenuSeeder::class);
        $this->call(IctMasterlistMenuSeeder::class);
        // Changing the SharePoint workbook: the page where changes are approved, and who may ask for and approve them.
        $this->call(EditApprovalsMenuSeeder::class);
        $this->call(DocumentEditAccessSeeder::class);
        // The audit trail of sign-ins and changes (section "Settings", in the place of "Approval Management").
        $this->call(AuditLogMenuSeeder::class);
        // The clients and tokens of the API (below "Audit Log").
        $this->call(ApiTokensMenuSeeder::class);
        // What is in Snipe-IT, read only (the group "Snipe-IT", right after the section "Dokumen").
        $this->call(SnipeItMenuSeeder::class);
        // The base approval module does not work here: its menus are seeded above but not shown.
        // (Only on a new install: on a database in use the owner's later choice is left alone.)
        $this->call(HideApprovalMenusSeeder::class);
        // The pages of the section "Radio" that came with the design refresh go right below Register Radio and move
        // everything below them down by one: last, so that the places the seeders above go by are still where they look.
        $this->call(AttentionMenuSeeder::class);
        $this->call(ServiceMenuSeeder::class);
        $this->call(OpnameMenuSeeder::class);
    }

    /**
     * The base seeders insert explicit primary keys. SQL Server only accepts that
     * while IDENTITY_INSERT is ON for the table (one table per session at a time).
     */
    private function callWithIdentity(string $seeder, string $table): void
    {
        $sqlsrv = DB::getDriverName() === 'sqlsrv';

        if ($sqlsrv) {
            DB::unprepared("SET IDENTITY_INSERT [{$table}] ON");
        }

        try {
            $this->call($seeder);
        } finally {
            if ($sqlsrv) {
                DB::unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            }
        }
    }
}
