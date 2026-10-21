<?php

// The page "Servis & Perbaikan" (service.index): the board and the list of the open tickets, the drawer of a ticket, its
// dialogs (a new ticket, the next step, a note), and what the server answers (ServiceTicketController, ServiceTickets).
// A ticket keeps its step by a code (reported, sent, ...). What the app writes down of it (the activity of the ticket, the
// radio's history, the audit trail) and the Excel export stay in Indonesian: 'events' only says how the page shows the
// activity in its language.

return [
    'title' => 'Service & Repair',
    'not_ready' => 'The service ticket tables are not in the database yet. Run "php artisan migrate" first.',

    // By the code kept (ServiceTicket::STATUSES).
    'statuses' => [
        'reported' => 'Reported',
        'sent' => 'Sent for service',
        'in_repair' => 'In repair',
        'ready' => 'Ready for pickup',
        'returned' => 'Returned',
    ],
    // The same steps in the stepper of the drawer: short words.
    'steps' => [
        'reported' => 'Reported',
        'sent' => 'Sent',
        'in_repair' => 'In repair',
        'ready' => 'Ready',
        'returned' => 'Returned',
    ],
    // The next step: the drawer's button, the title of the dialog it opens, and its button.
    'next' => [
        'sent' => ['button' => 'Send for service', 'title' => 'Send for service', 'save' => 'Send'],
        'in_repair' => ['button' => 'Mark as in repair', 'title' => 'Repair started', 'save' => 'Save'],
        'ready' => ['button' => 'Mark as ready for pickup', 'title' => 'Ready for pickup', 'save' => 'Save'],
        'returned' => ['button' => 'Mark as returned', 'title' => 'Radio returned', 'save' => 'Save'],
    ],

    'toolbar' => [
        'view' => 'View',
        'board' => 'Board',
        'list' => 'List',
        'search' => 'Radio ID, SN or issue',
        'search_label' => 'Search Radio ID, SN or issue',
        'center' => 'Service center',
        'clear' => 'Clear filters',
        'export' => 'Export',
        'new' => 'New ticket',
    ],

    'columns' => [
        'ticket' => 'Ticket',
        'radio' => 'Radio',
        'issue' => 'Issue',
        'reported_by' => 'Reported by',
        'center' => 'Service center',
        'status' => 'Status',
        'days' => 'Duration',
    ],

    'drawer' => [
        'title' => 'Service ticket',
        'add_note' => 'Add a note',
    ],

    'new' => [
        'title' => 'New service ticket',
        'sub' => 'Record a radio with a problem; sending it to a service center is recorded in the next step.',
        'radio' => 'Radio',
        'radio_hint' => 'Radio ID, SN or user name',
        'issue' => 'Issue',
        'issue_hint' => 'E.g. screen cracked after a fall',
        'reporter' => 'Reported by',
        'reporter_hint' => 'Name or unit · department',
        'reporter_help' => 'Filled in from whoever has the radio; can be changed.',
        'save' => 'Create ticket',
    ],

    'move' => [
        'title' => 'Send for service',
        'center' => 'Service center',
        'center_hint' => 'Name of the service center',
        'sent_at' => 'Date sent',
        'ba' => 'BA No.',
        'returned_at' => 'Date returned',
        'expected' => 'Expected back',
        'quote' => 'Quote (Rp)',
        'placement' => 'Radio location afterwards',
        'condition' => 'Condition when returned',
        'cost' => 'Cost (Rp)',
        'sent_note' => 'The radio\'s location in the register becomes <b>In service</b>, and its history records this shipment.',
        'returned_note' => 'The radio\'s location and condition in the register are changed, and its history records its return.',
    ],

    'note' => [
        'title' => 'Add a note',
        'note' => 'Note',
        'note_hint' => 'E.g. the service center is waiting for spare parts',
        'save' => 'Save note',
    ],

    // What the script of the page writes (serviceBoard.words).
    'board' => [
        // The four numbers above the board.
        'stats' => [
            'open' => 'Open tickets',
            'turnaround' => 'Average time in service',
            'overdue' => 'Overdue',
            'cost' => 'Cost this quarter',
        ],
        'open_hint' => ':reported reported, :away at a service center',
        'turnaround_hint' => 'Last 90 days',
        'overdue_hint' => 'More than :days days in service',
        'closed_hint' => '{1} :count ticket closed|[0,*] :count tickets closed',
        // A short amount ("Rp 14.2M"): the number with one decimal, and its unit.
        'money' => 'Rp :amount:unit',
        'million' => 'M',
        'billion' => 'B',
        'decimal' => '.',
        'days' => '{1} :count day|[0,*] :count days',

        'all_centers' => 'All service centers',
        'hide' => 'Hide',
        'more' => ':count more',
        'no_match' => 'No matches',
        'no_tickets' => 'No tickets',
        'not_sent' => 'Not sent yet',
        'list_no_match' => 'No tickets match.',
        'list_none' => 'No open tickets.',
        'foot_no_match' => 'No tickets match',
        'foot_none' => 'No open tickets',
        'foot_matches' => '{1} :count ticket matches|[0,*] :count tickets match',
        'foot_all' => '{1} :count open ticket|[0,*] All :count open tickets',

        'not_found' => 'That ticket was not found.',
        'not_in_register' => 'Not in the register',
        'deleted' => 'Deleted from the register',
        'radio_state' => 'Location: :placement · Condition: :condition',
        'archived' => '(archived)',
        'issue' => 'Issue',
        'reported' => 'Reported',
        'center' => 'Service center',
        'sent' => 'Sent',
        'expected' => 'Expected back',
        'quote' => 'Quote',
        'cost' => 'Cost',
        'returned' => 'Returned',
        'radio_status' => 'Radio status',
        'open_radio' => 'Open the radio',
        'note' => 'Note',
        'activity' => 'Activity',
        'no_activity' => 'No activity yet.',

        'no_id' => 'Radio without an ID',
        'no_sn' => 'no SN',
        'still_open' => 'Ticket :number is still open',
        'no_radio' => 'No active radio matches.',
        'change' => 'Change',
        'center_needed' => 'Fill in the service center it goes to.',
        'today' => 'Today',
        'unknown' => 'Not known yet',
    ],

    // How the page shows a step in the activity of a ticket; kept as the first form of each (ServiceTickets::move).
    'events' => [
        'reported' => 'Reported: :issue',
        'sent' => 'Sent to :center',
        'sent_ba' => 'Sent to :center · BA :ba',
        'in_repair' => 'Repair started',
        'in_repair_quote' => 'Repair started · quote :quote',
        'ready' => 'Ready for pickup',
        'ready_cost' => 'Ready for pickup · cost :cost',
        'returned' => 'Returned: :placement · :condition',
    ],

    // The names of the fields in the messages of the server.
    'fields' => [
        'radio_id' => 'Radio',
        'issue' => 'Issue',
        'reported_by' => 'Reporter',
        'status' => 'Status',
        'service_center' => 'Service center',
        'sent_at' => 'Date sent',
        'document_no' => 'BA No.',
        'expected_at' => 'Expected back',
        'quote' => 'Quote',
        'cost' => 'Cost',
        'returned_at' => 'Date returned',
        'placement' => 'Radio location',
        'condition' => 'Radio condition',
        'note' => 'Note',
    ],
    // What a field says when it is not right (Laravel's custom messages: by rule, a size rule by type).
    'validation' => [
        'required' => ':attribute is required.',
        'in' => ':attribute is not a known choice.',
        'integer' => ':attribute must be a whole number.',
        'date_format' => ':attribute must be a valid date.',
        'min' => ['numeric' => ':attribute cannot be less than :min.'],
        'max' => ['numeric' => ':attribute is too large.', 'string' => ':attribute can be at most :max characters.'],
    ],
    // ... and what one field says for one rule.
    'rules' => [
        'radio_id.required' => 'Choose the radio that needs service.',
        'issue.required' => 'Write what is wrong with the radio.',
        'note.required' => 'Write the note.',
        'sent_at.before_or_equal' => 'The date sent cannot be after today.',
        'sent_at.after_or_equal' => 'The date sent cannot be before the ticket was made.',
        'returned_at.before_or_equal' => 'The date returned cannot be after today.',
        'returned_at.after_or_equal' => 'The date returned cannot be before the date sent.',
        'expected_at.after_or_equal' => 'Expected back cannot be before the date sent.',
    ],

    'messages' => [
        'created' => 'Ticket :number created.',
        'moved' => 'Ticket :number: :status.',
        'note_added' => 'Note added to :number.',
        'invalid' => 'Invalid input',
        'busy' => 'The register is busy',
        'busy_text' => 'Another radio is being saved, or an Excel import is running. Try again in a moment.',
    ],
    // Why a write is refused (ServiceTickets).
    'refusals' => [
        'not_in_register' => 'That radio is not in the register.',
        'archived' => 'An archived radio cannot go for service. Restore it first.',
        'open' => 'This radio still has an open service ticket: :number.',
        'finished' => 'This ticket is finished: the radio is back.',
        'moved' => 'Someone else has moved this ticket on. Reload the page.',
    ],
];
