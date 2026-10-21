<?php

// Riwayat Radio (history.index): the lines of the history of every radio, newest first, and their filters. The kind of a
// line is kept in Indonesian and shown with its name in common.history_actions; what a line says is shown as written.

return [
    'title' => 'Radio History',
    'sub' => 'Every event of the radios, newest first',
    'search' => 'Search',
    'search_hint' => 'Radio ID, SN, user, description, document no.',
    'kind' => 'Kind',
    'from' => 'From date',
    'to' => 'To date',
    'reset' => 'Reset',
    'columns' => [
        'no' => 'No',
        'date' => 'Date',
        'radio' => 'Radio',
        'kind' => 'Kind',
        'description' => 'Description',
        'document' => 'Document No.',
        'by' => 'By',
    ],

    // The script of the page (historyTable).
    'list' => [
        'empty' => 'No history yet',
        'noun' => 'events',
        'load_failed' => 'The data could not be loaded.',
        'all_kinds' => 'All kinds',
        'all_dates' => 'Any date',
        'until_today' => 'Up to today',
    ],
];
