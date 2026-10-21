<?php

// The words the pages of the radios share (partials/scripts: RadioUi), and the names of the values the register keeps.
// The values are stored in Indonesian (the history, the Excel files, the API): these names are only what is shown.

return [
    'ui' => [
        'no_issues' => 'No problems',
        'no_permission' => 'You are not allowed to do this.',
        'error' => 'Something went wrong, please try again.',
        'failed' => 'Failed',
        'success' => 'Done',
        'inactive' => ':name (inactive)',
        'choose' => 'Choose…',
        'loading' => 'Loading…',
        'load_failed' => 'Could not load',
        'cancel' => 'Cancel',
        'close' => 'Close',
        'save' => 'Save',
        'yes' => 'Yes',
        'no' => 'No',
        'none' => 'None',
        'more' => 'and :count more',
        'system' => 'System',
    ],

    'placements' => [
        'di_user' => 'With user',
        'stok_it' => 'IT stock',
        'service' => 'In service',
        'hilang' => 'Lost',
    ],

    'conditions' => [
        'layak' => 'Good',
        'perlu_service' => 'Needs service',
        'rusak' => 'Broken',
        'belum_dicek' => 'Not checked',
    ],

    // By the value kept (config radio.archive_reasons).
    'archive_reasons' => [
        'Pindah Site' => 'Moved to another site',
        'Afkir' => 'Written off',
        'Hilang (BA selesai)' => 'Lost (BA signed)',
        'Lainnya' => 'Other',
    ],
    'archive_hints' => [
        'Pindah Site' => 'The radio is now in the register of another site',
        'Afkir' => 'End of its service life',
        'Hilang (BA selesai)' => 'The radio is lost and the BA has been signed',
        'Lainnya' => 'Explain in the note',
    ],

    // By the value kept (config radio.handover_accessories).
    'accessories' => [
        'Baterai' => 'Battery',
        'Charger' => 'Charger',
        'Antena' => 'Antenna',
        'Belt clip' => 'Belt clip',
        'Earpiece' => 'Earpiece',
    ],

    // The kinds of lines of a radio's history, by the name kept.
    'history_actions' => [
        'Dibuat' => 'Created',
        'Ubah Data' => 'Data changed',
        'Ubah Status' => 'Status changed',
        'Serah Terima' => 'Handover',
        'Service' => 'Service',
        'Program' => 'Programming',
        'Verifikasi' => 'Verification',
        'Catatan' => 'Note',
        'Arsip' => 'Archived',
        'Dipulihkan' => 'Restored',
        'Dihapus' => 'Deleted',
        'Impor' => 'Import',
    ],

    // What the checks of the register say more of a radio (RadioCheckService::issuesFor).
    'hints' => [
        'sn_format' => 'Usual format: 3 digits + 3 letters + 4 characters, e.g. 867TZB5309',
        'id_empty' => 'Last known ID: :id',
        'id_hex_f' => 'MDC :mdc contains the letter F (an MDC wildcard). Reprogram the radio to another ID',
        'reprogram' => 'Template in force since :date',
        'verify_stale' => 'Last :date',
    ],
];
