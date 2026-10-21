<?php

// The page "Persetujuan Edit" (EditApprovalController, edit-approvals.blade.php): the requests to change a cell of a
// workbook, their status, approving and rejecting them, and the answers of its routes. What a request is about (the
// workbook, the sheet, the cell, the values, the names) is data and is never translated.
//
// "statuses", "empty" and "cleared" are what the pages show of a request (EditRequest::statusName(), oldShown(),
// newShown()); the audit log and the API keep EditRequest::LABELS and the Indonesian words.

return [
    'title' => 'Persetujuan Edit',
    'sub' => 'Pengajuan perubahan sel ICT Masterlist, yang menunggu disetujui oleh orang lain.',
    'tabs' => 'Status pengajuan',
    'search' => 'Cari pengajuan',
    'search_hint' => 'Sel, kolom, nomor dokumen, nilai, nama pengaju, …',
    'reset_hint' => 'Reset pencarian dan status',
    'help' => 'Perubahan sel workbook SharePoint yang diajukan dari halaman ICT Masterlist. Isi workbook <b>baru berubah setelah disetujui</b> di sini oleh orang lain, bukan oleh pengajunya. Saat disetujui, aplikasi membaca workbook yang sekarang dan menulis hanya jika sel dan barisnya masih seperti saat diajukan; kalau sudah berubah, statusnya <b>Konflik</b> dan tidak ada yang ditulis.',

    'columns' => [
        'no' => 'No',
        'requested' => 'Diajukan',
        'cell' => 'Sel',
        'value' => 'Nilai',
        'status' => 'Status',
        'actions' => 'Aksi',
    ],

    'statuses' => [
        'pending' => 'Menunggu',
        'processing' => 'Diproses',
        'applied' => 'Diterapkan',
        'rejected' => 'Ditolak',
        'conflict' => 'Konflik',
        'failed' => 'Gagal',
    ],
    // An empty cell before the change, and a cell that the change empties.
    'empty' => '(kosong)',
    'cleared' => '(dikosongkan)',

    // What the script of the page says (eaUi, eaPage).
    'script' => [
        'you' => '(Anda)',
        'see_row' => 'Lihat baris ini di :workbook',
        'row_key' => 'baris :key',
        'direct' => 'Ditulis langsung oleh :name, :time',
        'approved' => 'Disetujui :name, :time',
        'rejected' => 'Ditolak :name, :time',
        'reason' => 'Alasan: :note',
        'approve' => 'Setujui',
        'reject' => 'Tolak',
        'waiting_others' => 'Menunggu orang lain',
        'no_requests' => 'Tidak ada pengajuan',
        'view_only' => 'Role Anda hanya bisa melihat pengajuan. Menyetujui atau menolak perlu hak Update di menu ini.',
        'load_failed' => 'Daftar pengajuan tidak bisa dimuat.',
        'none' => 'Tidak ada pengajuan.',
        'none_search' => 'Tidak ada pengajuan yang cocok dengan pencarian.',
        'none_status' => 'Tidak ada pengajuan dengan status ":status".',
        'none_status_search' => 'Tidak ada pengajuan dengan status ":status" yang cocok dengan pencarian.',
        'no_access' => 'Role Anda tidak punya izin untuk menu ini.',
        'error' => 'Terjadi kesalahan. Coba lagi beberapa saat lagi.',
        'all' => 'Semua',
        'where' => ':workbook · sheet :sheet · sel :cell',
        'now' => 'Sekarang',
        'becomes' => 'Menjadi',
        'requested_by' => 'Diajukan oleh :name, :time.',
        'approve_title' => 'Setujui perubahan ini?',
        'approve_warning' => 'Workbook di SharePoint akan langsung diubah.',
        'approve_button' => 'Setujui dan tulis',
        'not_written' => 'Tidak ditulis',
        'failed' => 'Gagal',
        'reject_title' => 'Tolak perubahan ini?',
        'reject_reason' => 'Alasan (boleh dikosongkan)',
        'reject_button' => 'Tolak',
    ],

    // The answers of the routes of the page.
    'applied' => 'Perubahan sel :cell sudah ditulis ke SharePoint.',
    'rejected' => 'Pengajuan perubahan sel :cell ditolak.',
];
