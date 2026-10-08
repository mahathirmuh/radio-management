<?php

/*
| SharePoint folders shown as pages of this application.
|
| Each entry becomes a page under /documents/<key> (route names documents.<key>.index / .list)
| and needs a row in the `menu` table to appear in the sidebar (see CofScanMenuSeeder); without
| that row the page is closed to everyone but Super Admin.
| The folder is read through Microsoft Graph with the application's own identity
| (services.azure: tenant, client id, client secret), which needs the application permission
| Sites.Read.All with admin consent.
*/
return [
    // How long a folder listing, or a workbook that was read, is kept before SharePoint is asked again
    // (minutes); also how long a workbook that could not be read is not downloaded again.
    'cache_minutes' => (int) env('SHAREPOINT_DOCS_CACHE_MINUTES', 10),

    // Seconds one request to Microsoft may take (the sign-in, a page of a listing).
    'timeout' => 10,

    // Seconds all requests for one listing may take together (sign-in, finding the folder,
    // every page of the listing). Past that the page says SharePoint cannot be reached.
    // (A listing that is read again while a file is being shown runs on that file's time,
    // preview_time_limit, instead.)
    'time_limit' => 20,

    // The "Lihat" button shows a file inside the application (PDF and images only). Larger
    // files than this many megabytes are opened in SharePoint instead.
    'preview_max_mb' => 10,

    // Seconds showing one file may take in all, the transfer of the file included. Keep it
    // below PHP's max_execution_time (30 by default), and in step with preview_max_mb: the
    // largest file has to arrive within it (10 MB in 20 seconds needs about 4 Mbit/s).
    // Fetching a workbook to read it has the same limit; the reading that follows gets what is
    // left of it, between 5 and 15 seconds, so for a workbook keep this value plus 5 below
    // max_execution_time.
    'preview_time_limit' => 25,

    // Megabytes of an Excel workbook that are read into the application (see "workbooks"), and
    // how many cells that hold a value it may have, over all its sheets: a workbook with more is
    // not shown at all. (A cell that only has a format, or lies beyond column 80, does not count.)
    'workbook_max_mb' => 15,
    'workbook_max_cells' => 200000,

    'folders' => [
        'cof-scan' => [
            'title' => 'COF Scan',
            'subtitle' => 'Scan IT Equipment Checkout Form (COF) di SharePoint ICT',
            // The folder's address as it stands in the browser's address bar while the folder
            // is open (…/Forms/AllItems.aspx?id=…). Not a link made with "Copy link" / "Share":
            // such a link gives access to whoever holds it, so it would not be shown on the page.
            'url' => env('SHAREPOINT_COF_SCAN_URL'),
            // File names look like "ICTCOF123 - Nama Penerima.pdf": shown as two columns.
            'name_split' => ['separator' => ' - ', 'code' => 'No. COF', 'label' => 'Nama'],
        ],
    ],

    /*
    | Excel workbooks (.xlsx) shown as pages: one tab per sheet, each a table. A key must not
    | also be used under "folders". Pages are /documents/<key> (route names documents.<key>.index
    | / .rows) and need a `menu` row like the folders (see IctMasterlistMenuSeeder).
    */
    'workbooks' => [
        'ict-masterlist' => [
            'title' => 'ICT Masterlist',
            'subtitle' => 'Register dokumen ICT (SRF, COF, serah terima, dan lainnya) dari workbook Excel di SharePoint ICT',
            // The workbook's address as it stands in the browser's address bar while the workbook
            // is open in Excel for the web (…/_layouts/15/Doc.aspx?sourcedoc=…). It is only used
            // to find the workbook and is never shown on the page.
            'url' => env('SHAREPOINT_ICT_MASTERLIST_URL'),
            // The sheets to show, by name and in this order (this is also how a sheet that is
            // hidden in Excel can be shown). null: every sheet that is visible in Excel.
            'sheets' => null,
            // The registers in this workbook are numbered ahead of use. true: a row that holds
            // nothing yet but that number is left out (a row in which at most one cell was
            // typed in; cells filled by a formula do not count). false: every row is shown.
            'skip_unused_rows' => true,
        ],
    ],
];
