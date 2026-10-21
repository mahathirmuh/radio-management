<?php

// What CellEdits says about changing a cell of a workbook: why a request or a row is refused (in the language of whoever
// asks: the page's, or the API client's), why an approved change was not written (stored with the request in
// Indonesian, as everything the application writes down; the answer and the notification say it in their own
// language), and the notifications to the approvers and to whoever asked (in the language of each of them).

return [
    // Refusals.
    'not_ready' => 'The table of the requests has not been created in the database yet. Run "php artisan migrate" first.',
    'disabled' => 'The data of this workbook cannot be changed from here yet.',
    'stale' => 'SharePoint cannot be reached right now, so what is shown may be old. Try again in a moment.',
    'no_sheet' => 'Sheet not found. Reload the page.',
    'no_row' => 'That row is no longer in this sheet. Reload the page.',
    'column_fixed' => 'This column cannot be changed.',
    'formula' => 'This cell holds a formula. Change it in Excel.',
    'same' => 'The new value is the same as the current one.',
    'waiting' => 'A request for cell :cell is already waiting for a decision (asked for by :name).',
    'own' => 'You cannot approve your own request. Ask somebody else to approve it.',
    'taken' => 'This request has been decided already or is being processed by somebody else. Reload the list.',
    'decided' => 'This request has been decided already or is being processed. Reload the list.',
    'not_direct' => 'You may not change this workbook directly. Ask for a change per cell.',
    'column_fixed_named' => 'Column ":column" cannot be changed. Reload the page.',
    'in_column' => 'Column ":column": :message',
    'column_formula' => 'Column ":column" holds a formula. Change it in Excel.',
    'nothing_changed' => 'Nothing was changed.',
    'cell_waiting' => 'Cell :cell has a request waiting for a decision (asked for by :name). Decide on it first on the page Edit Approvals.',
    'row_changed' => 'Row :line has changed in SharePoint since the page was loaded (cell :cell). Nothing was written; reload the page and try again.',
    'row_not_kept' => 'The changed workbook could not be kept for a moment on the application server. Nothing was written to SharePoint; try again.',
    'row_unsupported' => 'This row cannot be written to the workbook: the form of the sheet or of a cell in this file is not supported yet. Change it in Excel.',
    'row_saved_meanwhile' => 'Somebody else saved the workbook in SharePoint after it was read. Nothing was written; reload the page and try again.',

    // What is typed as the new value.
    'bad_characters' => 'The text holds characters that cannot be kept in Excel.',
    'too_long' => 'The text is too long (at most :max characters).',
    'number' => 'This column holds numbers: type a number only, at most 15 digits (such as 1500 or 1500.5; a dot followed by three digits separates thousands), or leave it empty.',
    'date' => 'This column holds dates: type dd-mm-yyyy (such as 15-03-2023), or dd-mm-yyyy hh:mm when there is a time.',

    // Why an approved change was not written, or that it was there already.
    'app_error' => 'Something went wrong in the application while writing to SharePoint. Nothing was written; try again, or contact the admin.',
    'already' => 'This cell holds that value in SharePoint already, so nothing was written.',
    'not_kept' => 'The changed workbook could not be kept for a moment on the application server. Nothing was written to SharePoint; try approving again.',
    'cell_unsupported' => 'This cell cannot be written to the workbook: the form of the sheet or of the cell in this file is not supported yet.',
    'sheet_gone' => 'Sheet ":sheet" is no longer in the workbook (it may have been renamed). Nothing was written.',
    'unreadable' => 'The content of the workbook in SharePoint cannot be read as an Excel workbook. Nothing was written; try again, or contact the admin.',
    'now_formula' => 'Cell :cell holds a formula now and is not written over. Nothing was written.',
    'cell_kind' => 'Cell :cell is not a kind of cell that can be written from here. Change it in Excel.',
    'row_moved' => 'Row :line has changed or moved in SharePoint since the request (the column that tells the row is no longer the same). Nothing was written; ask again.',
    'cell_changed' => 'Cell :cell has changed in SharePoint since the request (now: ":now"). Nothing was written; ask again.',
    'date1904' => 'This workbook counts dates from the year 1904; numbers and dates cannot be written from here yet. Change it in Excel.',

    // The notifications.
    'notify' => [
        'requested_title' => 'A change is waiting for approval',
        'requested_body' => ':name asks to change :place: :old → :new.',
        'applied_title' => 'Your change has been applied',
        'applied_body' => ':place: :new has been written to the workbook (approved by :name).',
        'rejected_title' => 'Your request has been rejected',
        'rejected_body' => ':place: rejected by :name.',
        'rejected_body_note' => ':place: rejected by :name: :note.',
        'conflict_title' => 'Your change was not applied',
        'failed_title' => 'Your change could not be applied yet',
        'failure_body' => ':place: :failure',
    ],
];
