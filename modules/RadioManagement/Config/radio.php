<?php

return [
    /*
    | Radio ID pool. IDs whose hexadecimal form contains "F" are never handed out,
    | because "F" is the wildcard digit in MDC-1200 (same convention as the Excel
    | register, where the MDC Code column highlights any "F").
    */
    'id_start' => 11,
    'id_max' => 16776415,

    /*
    | The column "Owner" of the register says MTI or Non MTI. It is not typed in: it follows from
    | "Pemilik Aset" (the company that owns the radio), which is MTI when its code is this one.
    */
    'owner_mti_code' => 'MTI',

    // A radio counts as "stale" when its last physical verification is older than this.
    'verify_max_months' => 12,

    // Motorola serial numbers in the register look like 867TZB5309.
    'sn_pattern' => '/^\d{3}[A-Z]{3}[A-Z0-9]{4}$/',

    'archive_reasons' => [
        'Pindah Site',
        'Afkir',
        'Hilang (BA selesai)',
        'Lainnya',
    ],

    'history_actions' => [
        'Serah Terima',
        'Service',
        'Program',
        'Verifikasi',
        'Catatan',
    ],

    'categories' => [
        'HT' => 'HT (Portable)',
        'RIG' => 'Rig (Mobile)',
    ],
];
