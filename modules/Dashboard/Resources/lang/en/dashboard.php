<?php

// The radio dashboard (Dashboard::index): its title, the tiles, the conditions as a pie, the next free Radio IDs, what
// needs action, the bars per company and per type, and the recent activity. The names of the placements, conditions,
// checks and kinds of history lines come from the RadioManagement module.

return [
    'title' => 'Welcome to Radio Management System',
    'radios' => '{1} :count radio|[2,*] :count radios',
    // The group of the radios without a company or a type.
    'no_name' => '(none)',

    'empty' => [
        'title' => 'No radio data yet',
        'text' => 'Import the old register from Excel, or add radios one by one in Register Radio.',
    ],

    'kpi' => [
        'active' => 'Active radios',
        'archived' => 'Archived',
    ],

    'conditions' => [
        'title' => 'Condition of active radios',
        'sub' => '{1} :count radio, by the last condition recorded|[2,*] :count radios, by the last condition recorded',
        'chart' => 'Condition of active radios: :parts',
        'show' => 'Show radios: :condition',
    ],

    'next_ids' => [
        'title' => 'Next free Radio IDs',
        'sub' => 'IDs whose MDC Code contains F are skipped automatically',
    ],

    'attention' => [
        'title' => 'Needs action',
        'sub' => 'Results of the automatic checks in the "Checks" column of Register Radio',
        'open' => 'Open inbox',
        'none' => 'No problems found.',
        'show' => 'Show',
    ],

    'by_company' => [
        'title' => 'Active radios per user company',
        'sub' => 'Number of units, most first',
    ],

    'by_type' => [
        'title' => 'Active radios per type',
        'sub' => 'MotoTrbo XiR · number of units',
    ],

    'activity' => [
        'title' => 'Recent activity',
        'sub' => 'The latest history by event date (import entries left out)',
        'all' => 'All history',
        'none' => 'No activity yet.',
        'date' => 'Date',
        'kind' => 'Kind',
        'radio' => 'Radio',
        'description' => 'Description',
        'by' => 'By',
    ],
];
