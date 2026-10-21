<?php

// The page "Persetujuan Edit" (EditApprovalController, edit-approvals.blade.php): the requests to change a cell of a
// workbook, their status, approving and rejecting them, and the answers of its routes. What a request is about (the
// workbook, the sheet, the cell, the values, the names) is data and is never translated.
//
// "statuses", "empty" and "cleared" are what the pages show of a request (EditRequest::statusName(), oldShown(),
// newShown()); the audit log and the API keep EditRequest::LABELS and the Indonesian words.

return [
    'title' => 'Edit Approvals',
    'sub' => 'Requests to change cells of the ICT Masterlist, waiting for somebody else to approve them.',
    'tabs' => 'Status of the requests',
    'search' => 'Search the requests',
    'search_hint' => 'Cell, column, document number, value, requester, …',
    'reset_hint' => 'Reset the search and the status',
    'help' => 'Changes of cells of a SharePoint workbook, asked for on the page ICT Masterlist. The workbook <b>changes only after approval</b> here by somebody else, not by whoever asked. On approval the application reads the workbook as it is now and writes only if the cell and its row are still as they were when the change was asked for; if they have changed, the status is <b>Conflict</b> and nothing is written.',

    'columns' => [
        'no' => 'No',
        'requested' => 'Requested',
        'cell' => 'Cell',
        'value' => 'Value',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'applied' => 'Applied',
        'rejected' => 'Rejected',
        'conflict' => 'Conflict',
        'failed' => 'Failed',
    ],
    // An empty cell before the change, and a cell that the change empties.
    'empty' => '(empty)',
    'cleared' => '(emptied)',

    // What the script of the page says (eaUi, eaPage).
    'script' => [
        'you' => '(you)',
        'see_row' => 'See this row in :workbook',
        'row_key' => 'row :key',
        'direct' => 'Written directly by :name, :time',
        'approved' => 'Approved by :name, :time',
        'rejected' => 'Rejected by :name, :time',
        'reason' => 'Reason: :note',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'waiting_others' => 'Waiting for somebody else',
        'no_requests' => 'No requests',
        'view_only' => 'Your role can only look at the requests. Approving or rejecting needs Update on this menu.',
        'load_failed' => 'The requests could not be loaded.',
        'none' => 'No requests.',
        'none_search' => 'No requests match the search.',
        'none_status' => 'No requests with the status ":status".',
        'none_status_search' => 'No requests with the status ":status" match the search.',
        'no_access' => 'Your role has no permission for this menu.',
        'error' => 'Something went wrong. Try again in a moment.',
        'all' => 'All',
        'where' => ':workbook · sheet :sheet · cell :cell',
        'now' => 'Now',
        'becomes' => 'Becomes',
        'requested_by' => 'Asked for by :name, :time.',
        'approve_title' => 'Approve this change?',
        'approve_warning' => 'The workbook in SharePoint is changed straight away.',
        'approve_button' => 'Approve and write',
        'not_written' => 'Not written',
        'failed' => 'Failed',
        'reject_title' => 'Reject this change?',
        'reject_reason' => 'Reason (may be left empty)',
        'reject_button' => 'Reject',
    ],

    // The answers of the routes of the page.
    'applied' => 'The change of cell :cell has been written to SharePoint.',
    'rejected' => 'The request to change cell :cell has been rejected.',
];
