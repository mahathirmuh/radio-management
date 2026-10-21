<?php

// The pages of "Master Data Radio" (master.index, MasterData, MasterDataController): the four small tables (radio types,
// companies, departments, channel templates), the names of their fields, and what the page and its answers say. What
// the tables hold is data and is never translated; the audit trail keeps the Indonesian names of the tables and fields.

return [
    'tables' => [
        'radio-type' => [
            'title' => 'Radio Types',
            'add' => 'Add radio type',
            'edit' => 'Edit radio type',
            'search' => 'Search radio types…',
            'delete' => 'Delete this radio type?',
            'created' => 'Radio type added.',
            'saved' => 'Radio type saved.',
            'deleted' => 'Radio type deleted.',
            'fields' => [
                'name' => 'Type name',
                'category' => 'Category',
                'power' => 'Power',
                'is_active' => 'Active',
            ],
        ],
        'company' => [
            'title' => 'Companies',
            'add' => 'Add company',
            'edit' => 'Edit company',
            'search' => 'Search companies…',
            'delete' => 'Delete this company?',
            'created' => 'Company added.',
            'saved' => 'Company saved.',
            'deleted' => 'Company deleted.',
            'fields' => [
                'code' => 'Code',
                'name' => 'Company name',
                'is_active' => 'Active',
            ],
        ],
        'department' => [
            'title' => 'Departments',
            'add' => 'Add department',
            'edit' => 'Edit department',
            'search' => 'Search departments…',
            'delete' => 'Delete this department?',
            'created' => 'Department added.',
            'saved' => 'Department saved.',
            'deleted' => 'Department deleted.',
            'fields' => [
                'name' => 'Department name',
                'is_active' => 'Active',
            ],
        ],
        'channel-template' => [
            'title' => 'Channel Templates',
            'add' => 'Add channel template',
            'edit' => 'Edit channel template',
            'search' => 'Search channel templates…',
            'delete' => 'Delete this channel template?',
            'created' => 'Channel template added.',
            'saved' => 'Channel template saved.',
            'deleted' => 'Channel template deleted.',
            'fields' => [
                'name' => 'Template name',
                'channels' => 'Channels',
                'effective_date' => 'Effective from',
                'is_active' => 'Active',
            ],
            'help' => [
                'effective_date' => 'Radios on this template that were programmed before this date are marked "Needs reprogramming".',
            ],
        ],
    ],

    // What the page and its script say, whichever table it shows.
    'page' => [
        'tabs' => 'Master data',
        'search' => 'Search',
        'no' => 'No',
        'usage' => 'In use',
        'actions' => 'Actions',
        'new_sub' => 'Fill in and save; new data can be chosen in the register at once.',
        'edit_sub' => 'Change and save; the radios that use it change with it.',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'edit_button' => 'Edit',
        'delete_button' => 'Delete',
        'empty' => 'No data yet',
        'load_failed' => 'The data could not be loaded.',
        'delete_text' => 'Data still used by a radio cannot be deleted.',
    ],

    // What the server says (MasterData, MasterDataController; the API too).
    'messages' => [
        'required' => ':attribute is required.',
        'unique' => ':attribute already exists.',
        'in_use' => '{1} Still used by :count radio. Switch it off instead (it can then no longer be chosen for other radios), or move the radio to other data first.|[2,*] Still used by :count radios. Switch it off instead (it can then no longer be chosen for other radios), or move the radios to other data first.',
        'busy_text' => 'A radio is being saved, or an Excel import is running. Try again in a moment.',
    ],
];
