<?php

namespace Tests\Feature;

use App\Models\UserManagement\User;
use Modules\SharePointDocs\Services\MenuAccess;
use Tests\TestCase;

/**
 * May a user read what another page of the SharePoint module knows? The cases that need no database: nobody is not
 * allowed, and Super Admin is allowed everywhere (whether the page has a menu row or not). The rest (a menu row, Read,
 * the two apart) is in SharePointScansTest, with the database.
 */
class MenuAccessTest extends TestCase
{
    public function test_nobody_may_read_what_a_page_knows(): void
    {
        $this->assertFalse(MenuAccess::allows(null, 'documents.cof-scan.list'));
        $this->assertFalse(MenuAccess::allows(null, 'documents.ict-masterlist.rows'));
    }

    public function test_super_admin_may_read_it_whatever_the_page_is_and_without_a_menu_for_it(): void
    {
        $admin = new User();
        $admin->setRelation('roles', collect([(object) ['id' => 1]]));

        $this->assertTrue(MenuAccess::allows($admin, 'documents.cof-scan.list'));
        $this->assertTrue(MenuAccess::allows($admin, 'documents.a-page-that-has-no-menu.list'));
    }
}
