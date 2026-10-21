<?php

// Import Excel (import.index): the form, the report of the last import as its script draws it (radioImport.words), and
// what the server says back (RadioImportController, RadioImports, ImportProblem, RadioImportService, and the API that runs
// the same import). The Excel format itself (sheets, columns) is the old register's and is not translated, nor are the
// reasons a radio is marked "Perlu dicek": the import keeps them with the radio.

return [
    'title' => 'Import the radio register from Excel',
    'sub' => 'Reads the sheet <b>Form</b> (and <b>to Konawe</b>) of the old register file, for example <span class="radio-mono">Radio Register 07-08-25.xlsx</span>.',
    'rules' => [
        'rows' => 'One row with an SN or a User Name = one radio. Rows with only No + Radio ID (empty slots) are skipped.',
        'tidy' => 'Spelling, spaces and the Status column are tidied up automatically into <b>Location</b> + <b>Condition</b>.',
        'conflicts' => 'Conflicting data (a duplicate SN or ID, a status against the notes, etc.) is <b>not guessed</b>: the radio is imported all the same and marked <b>Needs checking</b>.',
        'original' => 'The original contents of every row are kept and can be seen on the radio\'s detail page.',
    ],
    'file' => 'Excel file (.xlsx)',
    'pick_file' => 'Choose a file',
    'konawe' => 'Also import the sheet "to Konawe" (radios moved to another site)',
    'fresh' => 'Delete every radio and all history before the import',
    'existing' => '{1} The database holds <b>:count</b> radio now. Importing again is only possible by deleting the old data (the master data is kept).|[0,*] The database holds <b>:count</b> radios now. Importing again is only possible by deleting the old data (the master data is kept).',
    'no_delete' => 'Your role has no <b>Delete</b> permission on the Register Radio menu, so this account cannot import again.',
    'start' => 'Start the import',
    'none' => 'No import yet.',

    // What the script of the page writes (radioImport.words).
    'page' => [
        'no_file' => 'No file chosen',
        'pick_first' => 'Choose an Excel file (.xlsx) first.',
        'confirm_title' => 'Start the import?',
        'confirm_fresh' => '<b>Every radio and all history will be deleted</b> and replaced by the contents of this file.',
        'confirm_add' => 'The data of the file will be added to the database.',
        'confirm' => 'Import',
        'importing' => 'Importing…',
        'wait' => 'This can take about a minute.',
        'done' => '{1} :count radio imported, :flagged marked "Needs checking".|[0,*] :count radios imported, :flagged marked "Needs checking".',
        'last' => 'Last import',
        'seconds' => ':seconds s',
        'imported' => 'Radios imported',
        'active' => 'Active',
        'archived' => 'Archived',
        'flagged' => 'Needs checking',
        'summary' => 'Empty ID slots skipped: <b>:slots</b> · New master data: types :types, companies :companies, departments :departments, templates :templates',
        'skipped' => '<b>Rows skipped</b> (filled in, but without an SN or User Name):',
        'skipped_row' => ':sheet row :row: :reason',
        'flags' => 'Radios that need checking',
        'open_register' => 'Open in Register Radio',
        'row' => 'Row',
        'sn' => 'SN',
        'reason' => 'Reason',
    ],

    // What the server says back.
    'messages' => [
        'not_allowed' => 'Not allowed',
        'replace_refused' => 'Deleting the old data needs the Delete permission on the Register Radio menu.',
        'busy_text' => 'A radio is being saved or another import is still running. Wait a moment, then try again.',
        'done' => 'Import finished.',
        'file_required' => 'Choose an Excel file (.xlsx) first.',
        'file_type' => 'The file must be an .xlsx file.',
        'file_size' => 'The file can be 20 MB at most.',
        'refused' => 'Import cancelled',
        'failed' => 'Import failed',
        'database' => 'A database error happened during the import; nothing was changed. The details are in the application log.',
        'error' => 'Something went wrong during the import: :error',
        'not_empty' => 'The register already holds radios. Tick "Delete every radio and all history before the import" to import from Excel again.',
        'open_tickets' => '{1} :count service ticket is still open. An import that deletes the old data can only run once that radio is back (menu Service & Repair).|[0,*] :count service tickets are still open. An import that deletes the old data can only run once all those radios are back (menu Service & Repair).',
        'open_opnames' => '{1} :count stock opname session is still open. Send it to the register first (menu Stock Opname); then an import that deletes the old data can run.|[0,*] :count stock opname sessions are still open. Send them to the register first (menu Stock Opname); then an import that deletes the old data can run.',
        'no_sheet' => 'There is no sheet ":sheet" in this file.',
        'no_column' => 'There is no column ":column" in the sheet :sheet.',
    ],
];
