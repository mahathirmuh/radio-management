<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar entry "ICT Masterlist" (section "Dokumen", below "COF Scan"): the Excel
 * workbook with the registers of ICT documents, shown by modules/SharePointDocs. What is given
 * to whom, and what running it again does, is in DocumentMenuSeeder.
 *   php artisan db:seed --class=IctMasterlistMenuSeeder
 */
class IctMasterlistMenuSeeder extends DocumentMenuSeeder
{
    public const ROUTE = 'documents.ict-masterlist.index';

    protected const TITLE = 'ICT Masterlist';

    protected const ICON = 'table';

    protected const ORDER = 8;

    /**
     * The entry takes place 8, where RadioMenuSeeder used to put "User Management": that one and
     * "Approval Management" move down by one, when they are still where the seeders put them.
     */
    protected function added(): void
    {
        foreach (['Approval Management' => [9, 10], 'User Management' => [8, 9]] as $title => [$from, $to]) {
            DB::table('menu')->whereNull('parent_id')->where('title', $title)->where('order', $from)->update(['order' => $to]);
        }
    }
}
