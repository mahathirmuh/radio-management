<?php

// The radio dashboard (Dashboard::index): its title, the tiles, the conditions as a pie, the next free Radio IDs, what
// needs action, the bars per company and per type, and the recent activity. The names of the placements, conditions,
// checks and kinds of history lines come from the RadioManagement module.

return [
    'title' => 'Selamat datang di Radio Management System',
    'radios' => ':count radio',
    // The group of the radios without a company or a type.
    'no_name' => '(kosong)',

    'empty' => [
        'title' => 'Belum ada data radio',
        'text' => 'Impor register lama dari Excel, atau tambah radio satu per satu di Register Radio.',
    ],

    'kpi' => [
        'active' => 'Radio aktif',
        'archived' => 'Diarsipkan',
    ],

    'conditions' => [
        'title' => 'Kondisi radio aktif',
        'sub' => ':count radio, menurut kondisi terakhir yang tercatat',
        'chart' => 'Kondisi radio aktif: :parts',
        'show' => 'Lihat radio :condition',
    ],

    'next_ids' => [
        'title' => 'Radio ID bebas berikutnya',
        'sub' => 'ID yang MDC Code-nya mengandung F otomatis dilewati',
    ],

    'attention' => [
        'title' => 'Perlu tindakan',
        'sub' => 'Hasil cek otomatis di kolom "Cek" pada Register Radio',
        'open' => 'Buka inbox',
        'none' => 'Tidak ada masalah yang terdeteksi.',
        'show' => 'Lihat',
    ],

    'by_company' => [
        'title' => 'Radio aktif per company pengguna',
        'sub' => 'Jumlah unit, diurutkan dari terbanyak',
    ],

    'by_type' => [
        'title' => 'Radio aktif per tipe',
        'sub' => 'MotoTrbo XiR · jumlah unit',
    ],

    'activity' => [
        'title' => 'Aktivitas terbaru',
        'sub' => 'Riwayat terbaru menurut tanggal kejadian (tanpa catatan impor)',
        'all' => 'Semua riwayat',
        'none' => 'Belum ada aktivitas.',
        'date' => 'Tanggal',
        'kind' => 'Jenis',
        'radio' => 'Radio',
        'description' => 'Keterangan',
        'by' => 'Oleh',
    ],
];
