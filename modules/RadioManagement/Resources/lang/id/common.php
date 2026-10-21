<?php

// The words the pages of the radios share (partials/scripts: RadioUi), and the names of the values the register keeps.
// The values are stored in Indonesian (the history, the Excel files, the API): these names are only what is shown.

return [
    'ui' => [
        'no_issues' => 'Tidak ada masalah',
        'no_permission' => 'Anda tidak memiliki izin untuk aksi ini.',
        'error' => 'Terjadi kesalahan, coba lagi.',
        'failed' => 'Gagal',
        'success' => 'Berhasil',
        'inactive' => ':name (nonaktif)',
        'choose' => 'Pilih…',
        'loading' => 'Memuat…',
        'load_failed' => 'Gagal memuat',
        'cancel' => 'Batal',
        'close' => 'Tutup',
        'save' => 'Simpan',
        'yes' => 'Ya',
        'no' => 'Tidak',
        'none' => 'Tidak ada',
        'more' => 'dan :count lainnya',
        'system' => 'Sistem',
    ],

    'placements' => [
        'di_user' => 'Di User',
        'stok_it' => 'Stok IT',
        'service' => 'Service',
        'hilang' => 'Hilang',
    ],

    'conditions' => [
        'layak' => 'Layak',
        'perlu_service' => 'Perlu Service',
        'rusak' => 'Rusak',
        'belum_dicek' => 'Belum dicek',
    ],

    // By the value kept (config radio.archive_reasons).
    'archive_reasons' => [
        'Pindah Site' => 'Pindah Site',
        'Afkir' => 'Afkir',
        'Hilang (BA selesai)' => 'Hilang (BA selesai)',
        'Lainnya' => 'Lainnya',
    ],
    'archive_hints' => [
        'Pindah Site' => 'Radio kini masuk register site lain',
        'Afkir' => 'Akhir masa pakai',
        'Hilang (BA selesai)' => 'Radio hilang, BA sudah ditandatangani',
        'Lainnya' => 'Jelaskan di catatan',
    ],

    // By the value kept (config radio.handover_accessories).
    'accessories' => [
        'Baterai' => 'Baterai',
        'Charger' => 'Charger',
        'Antena' => 'Antena',
        'Belt clip' => 'Belt clip',
        'Earpiece' => 'Earpiece',
    ],

    // The kinds of lines of a radio's history, by the name kept.
    'history_actions' => [
        'Dibuat' => 'Dibuat',
        'Ubah Data' => 'Ubah Data',
        'Ubah Status' => 'Ubah Status',
        'Serah Terima' => 'Serah Terima',
        'Service' => 'Service',
        'Program' => 'Program',
        'Verifikasi' => 'Verifikasi',
        'Catatan' => 'Catatan',
        'Arsip' => 'Arsip',
        'Dipulihkan' => 'Dipulihkan',
        'Dihapus' => 'Dihapus',
        'Impor' => 'Impor',
    ],

    // What the checks of the register say more of a radio (RadioCheckService::issuesFor).
    'hints' => [
        'sn_format' => 'Format umum: 3 angka + 3 huruf + 4 karakter, mis. 867TZB5309',
        'id_empty' => 'ID terakhir diketahui: :id',
        'id_hex_f' => 'MDC :mdc mengandung huruf F (wildcard MDC). Program ulang radio ke ID lain',
        'reprogram' => 'Template berlaku sejak :date',
        'verify_stale' => 'Terakhir :date',
    ],
];
