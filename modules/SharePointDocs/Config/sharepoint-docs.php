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
|
| A workbook can also be changed from here (see "edit" of a workbook): a change is asked for, approved
| by somebody else, and only then written to SharePoint, with the same identity. That needs the application
| permission to WRITE to the site of the workbook: Sites.Selected with the role "write" for that site (best),
| or Sites.ReadWrite.All, with admin consent. Without it every approval ends as "Gagal", and nothing is changed.
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
    // preview_time_limit, instead. A listing that is read to check a sheet of the workbook against
    // it gets what is left of ScanLinks' 27 seconds, at most this.)
    'time_limit' => 20,

    // The "Lihat" button shows a file inside the application (PDF and images only). Larger
    // files than this many megabytes are opened in SharePoint instead.
    'preview_max_mb' => 10,

    // Seconds showing one file may take in all, the transfer of the file included. Keep it
    // below PHP's max_execution_time (30 by default), and in step with preview_max_mb: the
    // largest file has to arrive within it (10 MB in 20 seconds needs about 4 Mbit/s).
    // Fetching a workbook to read it has the same limit; the reading that follows gets what is
    // left of it, between 5 and 15 seconds, so for a workbook keep this value plus 5 below
    // max_execution_time. (The 25 given here and 5 are the 30 that PHP allows by default: where
    // that is the limit and the server counts real time, as Windows does, raise max_execution_time
    // or lower this value.) A workbook that is read to show its rows beside the files of a folder
    // gets what is left of ScanLinks' 27 seconds, at most this, less 5 seconds for the reading.
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
            // The sheets whose rows each point to a scan, a file in one of the folders above (by the title of the
            // sheet in the workbook; letter case and blanks at its ends do not matter). The page of the workbook then
            // has, on that sheet, a column "File scan" and cards that count the rows by whether their file is in the
            // folder; the page of the folder has, for every file, the row of the sheet that names it and that row's
            // note. Both pages show it only to who may open the other one (its menu, with Read). The titles are those
            // of the sheet's own columns: "link" (the address of the file; the name of the file in it is compared
            // with the names in the folder), "code" (the document number, "ICT/COF/123", which the files carry as
            // "ICTCOF123"; optional), "note" (optional). A setting needs its "folder" (a key of "folders" above) and its
            // "link"; one without them is left out, and so is one whose folder or workbook has no address (nothing is
            // said then). A title that the sheet does not have is told for "link" only. The time that reading the second
            // source may take is what is left of 27 seconds, but no more than time_limit (a folder) or preview_time_limit
            // less 5 seconds for the reading (a workbook): see ScanLinks.
            'scans' => [
                'COF' => ['folder' => 'cof-scan', 'link' => 'Column4', 'code' => 'COF No', 'note' => 'Note'],
            ],
            // Changing a cell from the page (the menu says who may ask: Update on this page; who may approve:
            // Update on the page "Persetujuan Edit"). Nothing is written until somebody else has approved.
            'edit' => [
                'enabled' => (bool) env('SHAREPOINT_ICT_MASTERLIST_EDIT', true),
                // The column that tells a row, by sheet title: a change is written only if that cell of the row is still
                // what it was when the change was asked for (a row inserted above it in Excel moves everything). A sheet
                // that is not named here is told by its first column.
                'anchors' => [
                    'COF' => 'COF No',
                ],
            ],
        ],
    ],
];
