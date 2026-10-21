<?php

// The page of an Excel workbook in SharePoint (ICT Masterlist: WorkbookController, workbook.blade.php): the toolbar, the
// cards and the column "File scan" of a sheet that is checked against a folder, the dialogs that ask for a change of a
// cell or write a row, what the page's script says, and the answers of its routes. Sheet names, column titles and cells
// come from the workbook and are never translated ("File scan" is the name of the column the application adds).
//
// "workbooks": the words of the workbooks of config/sharepoint-docs.php by their key, shown like the folders' in
// scans.php (while the config still says what the Indonesian ones here say).

return [
    'not_configured_title' => ':title cannot be shown yet.',
    'not_configured' => 'The address of the workbook in SharePoint or the Microsoft application account has not been filled in in the <code>.env</code> file (see the README, section "Dokumen SharePoint").',
    'read_only' => 'This page only shows the data; changes are still made in Excel.',
    'search' => 'Search all columns',
    'search_hint' => 'Name, document number, serial number, …',
    'reset_hint' => 'Reset the search, the chosen card and rows, and the order',
    'open_excel' => 'Open in Excel',
    'show_all' => 'Show all rows',
    'cards' => 'Summary of the File scan column',
    'legend' => '<b>Found</b>: a file with the same name as in the link (upper and lower case are not told apart). <b>Different name</b>: a file has the same number, but the link is empty or names another file (an extra or a missing space, say). <b>Subfolder</b>: the link points into a subfolder, usually because the requester\'s name contains a "/". <b>Missing</b>: no file has that name or number. Click a card to see only its rows; the search does not look in this column.',
    'sheets' => 'Sheets of the workbook',

    // Asking for a change of one cell.
    'ask' => [
        'title' => 'Ask for a change of a cell',
        'current' => 'Current value',
        'new' => 'New value',
        'hint' => 'Leave it empty to empty the cell. A number column takes a number such as 1500 or 1500.5 (a dot followed by three digits separates thousands: 1.500 is 1500); a date column takes dd-mm-yyyy (15-03-2023).',
        'not_yet' => 'The change is <b>not</b> saved in SharePoint yet. Somebody else who may approve it must approve it first on the page Edit Approvals; you cannot approve your own request.',
        'send' => 'Ask for the change',
    ],

    // Writing the cells of one row straight away.
    'row' => [
        'title' => 'Edit row',
        'note' => 'Written to the workbook in SharePoint straight away, without anybody else\'s approval; recorded in the Audit log.',
        'save' => 'Save',
    ],

    // What the script of the page says (wbUi, wbBook, wbEdit, wbRow).
    'script' => [
        // The column "File scan": the words of each status, and what it means (by the code of the status).
        'statuses' => [
            'ok' => 'Found',
            'missing' => 'Missing',
            'differs' => 'Different name',
            'below' => 'Subfolder',
        ],
        'status_hints' => [
            'ok' => 'A file has the same name as in the link (upper and lower case are not told apart)',
            'missing' => 'No file with this name or number in the folder',
            'differs' => 'A file has this number, but the link in Excel is empty or names another file (an extra or a missing space, say)',
            'below' => 'The link points into a subfolder: usually because the requester\'s name contains a "/"',
        ],
        'click_file' => 'Click to see this file in :folder',
        'see_file' => ':text: see the file in :folder',
        'waiting' => 'Waiting for approval: :value',
        'waiting_badge' => 'waiting',
        'formula' => 'This cell holds a formula: change it in Excel',
        'formula_label' => 'Holds a formula',
        'ask' => 'Ask for a change of this cell',
        'edit_row' => 'Edit this row',
        'edit_row_label' => 'Edit row :line',
        'row' => 'Row',
        'no_rows' => 'No rows',
        'hint_direct' => 'Click the pencil at a row number to change that row. Changes are written to SharePoint straight away and recorded in the Audit log.',
        'hint_ask' => 'Click the pencil in a cell to ask for a change of it. The workbook changes only after somebody else approves it on the page Edit Approvals.',
        'read_only' => 'This page only shows the data; changes are still made in Excel.',
        'load_failed' => 'The workbook could not be loaded.',
        'load_failed_retry' => 'The workbook could not be loaded. Try again in a moment.',
        'none_lines' => '{1} Excel row :lines is not in this sheet, or does not match the search or the chosen card.|[0,*] Excel rows :lines are not in this sheet, or do not match the search or the chosen card.',
        'none_status' => 'No rows with the status ":status" in this sheet.',
        'none_status_search' => 'No rows with the status ":status" match the search in this sheet.',
        'none_search' => 'No rows match the search in this sheet.',
        'empty_sheet' => 'This sheet is empty.',
        'only_lines' => '{1} Only Excel row :lines.|[0,*] Only Excel rows :lines.',
        'no_read' => 'Your role has no Read permission for this menu.',
        'search' => 'Search all columns',
        'search_except' => 'Search all columns except File scan',
        'total' => 'Total rows',
        'cards_info' => 'The status is checked against folder :folder (folder data as of :time).',
        'rows' => '{1} :count row in sheet ":sheet"|[0,*] :count rows in sheet ":sheet"',
        'rows_matching' => '{1} :count matching row in sheet ":sheet"|[0,*] :count matching rows in sheet ":sheet"',
        'modified' => 'workbook last changed :time',
        'data_at' => 'SharePoint data as of :time',
        'stale' => 'SharePoint cannot be reached right now or answers too slowly. This is the last content that could be fetched (:time).',
        'scan_stale' => 'SharePoint cannot be reached right now or answers too slowly. The File scan column is checked against the last list of folder :folder that could be fetched (:time).',
        'truncated' => 'This sheet is very large: only part of it is shown.',
        'open_all' => 'Open it in Excel to see everything.',
        'where' => 'Column ":column", Excel row :line',
        'empty' => '(empty)',
        'no_update' => 'Your role has no Update permission for this menu.',
        'send_failed' => 'The request could not be sent. Try again in a moment.',
        'sent' => 'Request sent',
        'hint_scan' => 'The column of the scan check: not changed from here.',
        'hint_formula' => 'Holds a formula: change it in Excel.',
        'hint_waiting' => 'Waiting for approval: :value. Decide on it first in Edit Approvals.',
        'hint_fixed' => 'Not changed from here.',
        'row_title' => 'Edit row :line',
        'row_where' => 'Sheet :sheet · saved to SharePoint straight away',
        'nothing_changed' => 'Nothing was changed.',
    ],

    // The answers of the routes of the page.
    'asked' => 'The change of cell :cell has been asked for. The workbook changes only after it is approved.',
    'column_unknown' => 'The column that was changed is not known. Reload the page.',
    'row_same' => 'Row :line in SharePoint already holds this; nothing was written.',
    'row_saved' => '{1} Row :line saved to SharePoint (:count cell).|[0,*] Row :line saved to SharePoint (:count cells).',
];
