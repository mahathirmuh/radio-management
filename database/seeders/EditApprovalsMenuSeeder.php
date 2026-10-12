<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar entry "Persetujuan Edit" (section "Dokumen", below "ICT Masterlist"): the requests to change a
 * cell of a SharePoint workbook, and their approval, shown by modules/SharePointDocs. Whoever has Update on this
 * menu may approve (and turn down) a request; Read lists them. What is given to whom is in DocumentMenuSeeder and
 * DocumentEditAccessSeeder.
 *   php artisan db:seed --class=EditApprovalsMenuSeeder
 */
class EditApprovalsMenuSeeder extends DocumentMenuSeeder
{
    public const ROUTE = 'documents.edit-approvals.index';

    protected const TITLE = 'Persetujuan Edit';

    protected const ICON = 'checklist';

    protected const ORDER = 9;

    /**
     * The entry takes place 9, where ICT Masterlist's seeder left "User Management": that one and "Approval
     * Management" move down by one, when they are still where the seeders put them.
     */
    protected function added(): void
    {
        foreach (['Approval Management' => [10, 11], 'User Management' => [9, 10]] as $title => [$from, $to]) {
            DB::table('menu')->whereNull('parent_id')->where('title', $title)->where('order', $from)->update(['order' => $to]);
        }
    }
}
