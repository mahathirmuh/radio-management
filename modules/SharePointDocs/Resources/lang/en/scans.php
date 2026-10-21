<?php

// The page of a SharePoint folder (COF Scan: FolderController, index.blade.php): the cards over the list, the toolbar,
// the table, the file viewer, what the page's script says, and the answers of its routes. Names of files and folders
// come from SharePoint and are never translated.
//
// "folders": the words of the folders of config/sharepoint-docs.php by their key. They are shown while the config still
// says what the Indonesian ones here say; a folder that is not here, or whose config has been changed, shows the
// config's own words.

return [
    'read_every' => '{1} Read from SharePoint, refreshed every minute.|[0,*] Read from SharePoint, refreshed every :minutes minutes.',
    'not_configured_title' => ':title cannot be shown yet.',
    'not_configured' => 'The address of the SharePoint folder or the Microsoft application account has not been filled in in the <code>.env</code> file (see the README, section "Dokumen SharePoint").',

    'synced' => 'Last synced',
    'linked' => 'Linked to a row',
    'linked_by' => 'Matched by :what',
    'document_number' => 'document number',
    'unmatched' => 'Not linked',
    'unmatched_hint' => 'No row, or a different name',
    'unmatched_bar' => 'Their row is missing, or its link names another file. Fix the link in Excel.',
    'review' => 'Review',

    'search' => 'Search',
    'search_split' => ':code or :label…',
    'search_name' => 'File name…',
    'status_label' => 'Status in the Masterlist',
    'all' => 'All',
    'status_linked' => 'Linked',
    'status_unmatched' => 'Not linked',
    'reset_hint' => 'Reset the search, the chosen number and the order',
    'open_sharepoint' => 'Open in SharePoint',
    'help' => 'The <i class="ti ti-eye"></i> button shows a file here; click its name to open it in SharePoint with your Microsoft account.',
    'help_register' => 'The Masterlist and Note columns come from sheet :sheet of :workbook: the row that names the file, and its note.',
    'show_all' => 'Show all files',

    'columns' => [
        'no' => 'No',
        'name' => 'Name',
        'masterlist' => 'Masterlist',
        'note' => 'Note',
        'type' => 'Type',
        'modified' => 'Modified',
        'by' => 'By',
        'size' => 'Size',
        'view' => 'View',
    ],

    'viewer' => [
        'previous' => 'Previous file',
        'next' => 'Next file',
        'new_tab' => 'Open in a new tab',
        'loading' => 'Loading the file…',
    ],

    // What the script of the page says (spUi, spDocs, spViewer).
    'script' => [
        'open_folder' => 'Open the folder in SharePoint',
        'folder' => 'Folder',
        'row' => 'Row :line',
        'row_hint' => 'The row in the Masterlist that names this file',
        'differs' => 'Different name · row :line',
        'differs_hint' => 'A row has this number, but its link is empty or names another file (or this file is a duplicate of a file that is named already)',
        'unlinked' => 'No row',
        'unlinked_hint' => 'No row in the Masterlist for this file',
        'and_more' => '{1} (and :count other row)|[0,*] (and :count other rows)',
        'click_row' => 'Click to see the row in :workbook',
        'see_row' => ':text: see the row in :workbook',
        'view_file' => 'View the file',
        'not_here' => 'Cannot be shown here: open it in SharePoint',
        'open_sharepoint' => 'Open in SharePoint',
        'no_documents' => 'No documents',
        'load_failed' => 'The list could not be loaded.',
        'load_failed_retry' => 'The list could not be loaded. Try again in a moment.',
        'no_read' => 'Your role has no Read permission for this menu.',
        'only_code' => 'Only the files with number :code.',
        'none_linked' => 'There are no files linked to a row.',
        'none_linked_among' => 'There are no files linked to a row among those searched for.',
        'none_unmatched' => 'There are no unlinked files.',
        'none_unmatched_among' => 'There are no unlinked files among those searched for.',
        'none_code' => 'No files with number :code.',
        'none_code_search' => 'No files with number :code match the search.',
        'none_search' => 'No documents match the search.',
        'empty_folder' => 'This folder is empty.',
        'synced_hint' => '{1} :day · :count file in the folder|[0,*] :day · :count files in the folder',
        'unmatched_text' => '{1} :count file does not match a row in :workbook.|[0,*] :count files do not match a row in :workbook.',
        'folders' => '{1} :count folder|[0,*] :count folders',
        'files' => '{1} :count file|[0,*] :count files',
        'data_at' => 'SharePoint data as of :time',
        'book_at' => ':workbook as of :time',
        'stale' => 'SharePoint cannot be reached right now. This is the last list that could be fetched (:time).',
        'truncated' => 'This folder holds a great many files: only some of them are shown. Open it in SharePoint to see them all.',
        'register_stale' => 'SharePoint cannot be reached right now or answers too slowly. The Masterlist and Note columns use the last content of :workbook that could be fetched (:time).',
        'frame' => 'Content of the file',
        'loading' => 'Loading the file…',
        'still_loading' => 'Still loading… If the file does not appear, open it with the "new tab" or "SharePoint" button at the top right.',
    ],

    // The answers of the routes of the page.
    'folder_unknown' => 'Unknown folder.',
    'file_unknown' => 'Unknown file.',
    'file_gone' => 'The file was not found in SharePoint. It may have been moved or deleted; reload the list.',

    'folders' => [
        'cof-scan' => [
            'subtitle' => 'Scans of the IT Equipment Checkout Form (COF) in the ICT SharePoint',
            'code' => 'COF No.',
            'label' => 'Name',
        ],
    ],
];
