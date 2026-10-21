<?php

// Why a page cannot show what the other source knows (ScanLinks): the column "File scan" of a sheet of the workbook (the
// files of the folder), and the columns Masterlist and Catatan of the page of the folder (the rows of the sheet). Said
// on the page, which is shown as it always is; :why is GraphException's reason.

return [
    'column_failed' => 'The :column column cannot be shown yet. :why',
    'column_too_many' => 'The :column column cannot be shown yet: folder :folder holds too many files to be read in full, so the status of its files cannot be counted.',
    'column_no_link' => 'Column ":link" was not found in sheet :sheet, so the status of the scans cannot be counted.',
    'register_failed' => 'The Masterlist and Note columns cannot be shown yet. :why',
    'register_no_sheet' => 'Sheet :sheet is not in workbook :workbook, so the Masterlist and Note columns cannot be shown.',
    'register_too_long' => 'Sheet :sheet is too long to be read in full, so the Masterlist and Note columns cannot be counted.',
    'register_no_link' => 'Column ":link" was not found in sheet :sheet, so the Masterlist and Note columns cannot be shown.',
];
