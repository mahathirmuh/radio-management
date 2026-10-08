<?php

namespace Database\Seeders;

/**
 * Adds the sidebar entry "COF Scan" (section "Dokumen"): the SharePoint folder with the scanned
 * IT Equipment Checkout Forms, shown by modules/SharePointDocs. What is given to whom, and what
 * running it again does, is in DocumentMenuSeeder.
 *   php artisan db:seed --class=CofScanMenuSeeder
 */
class CofScanMenuSeeder extends DocumentMenuSeeder
{
    public const ROUTE = 'documents.cof-scan.index';

    protected const TITLE = 'COF Scan';

    protected const ICON = 'files';

    protected const ORDER = 7;
}
