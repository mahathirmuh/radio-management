<?php

// Riwayat Radio (history.index): the lines of the history of every radio, newest first, and their filters. The kind of a
// line is kept in Indonesian and shown with its name in common.history_actions; what a line says is shown as written.

return [
    'title' => 'Riwayat Radio',
    'sub' => 'Setiap kejadian pada radio, yang terbaru di atas',
    'search' => 'Cari',
    'search_hint' => 'Radio ID, SN, user, keterangan, no. dokumen',
    'kind' => 'Jenis',
    'from' => 'Dari tanggal',
    'to' => 'Sampai tanggal',
    'reset' => 'Reset',
    'columns' => [
        'no' => 'No',
        'date' => 'Tanggal',
        'radio' => 'Radio',
        'kind' => 'Jenis',
        'description' => 'Keterangan',
        'document' => 'No. Dokumen',
        'by' => 'Oleh',
    ],

    // The script of the page (historyTable).
    'list' => [
        'empty' => 'Belum ada riwayat',
        'noun' => 'kejadian',
        'load_failed' => 'Gagal memuat data.',
        'all_kinds' => 'Semua jenis',
        'all_dates' => 'Semua tanggal',
        'until_today' => 'Sampai hari ini',
    ],
];
