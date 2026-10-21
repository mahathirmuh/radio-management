<?php

// What CellEdits says about changing a cell of a workbook: why a request or a row is refused (in the language of whoever
// asks: the page's, or the API client's), why an approved change was not written (stored with the request in
// Indonesian, as everything the application writes down; the answer and the notification say it in their own
// language), and the notifications to the approvers and to whoever asked (in the language of each of them).

return [
    // Refusals.
    'not_ready' => 'Tabel pengajuan belum dibuat di database. Jalankan "php artisan migrate" dulu.',
    'disabled' => 'Data workbook ini belum bisa diubah dari sini.',
    'stale' => 'SharePoint sedang tidak bisa dihubungi, jadi isi yang tampil mungkin sudah lama. Coba lagi beberapa saat lagi.',
    'no_sheet' => 'Sheet tidak ditemukan. Muat ulang halaman.',
    'no_row' => 'Baris itu tidak ada lagi di sheet ini. Muat ulang halaman.',
    'column_fixed' => 'Kolom ini tidak bisa diubah.',
    'formula' => 'Sel ini berisi rumus. Ubah di Excel.',
    'same' => 'Nilai baru sama dengan nilai sekarang.',
    'waiting' => 'Sudah ada pengajuan untuk sel :cell yang menunggu keputusan (diajukan oleh :name).',
    'own' => 'Pengajuan Anda sendiri tidak bisa Anda setujui. Minta orang lain yang menyetujuinya.',
    'taken' => 'Pengajuan ini sudah diputuskan atau sedang diproses orang lain. Muat ulang daftar.',
    'decided' => 'Pengajuan ini sudah diputuskan atau sedang diproses. Muat ulang daftar.',
    'not_direct' => 'Anda tidak berwenang mengubah workbook ini langsung. Ajukan perubahan per sel.',
    'column_fixed_named' => 'Kolom ":column" tidak bisa diubah. Muat ulang halaman.',
    'in_column' => 'Kolom ":column": :message',
    'column_formula' => 'Kolom ":column" berisi rumus. Ubah di Excel.',
    'nothing_changed' => 'Tidak ada isi yang berubah.',
    'cell_waiting' => 'Sel :cell punya pengajuan yang menunggu keputusan (diajukan oleh :name). Putuskan dulu di halaman Persetujuan Edit.',
    'row_changed' => 'Baris :line sudah berubah di SharePoint sejak halaman dimuat (sel :cell). Tidak ada yang ditulis; muat ulang halaman, lalu ulangi.',
    'row_not_kept' => 'Workbook yang sudah diubah tidak bisa disimpan sementara di server aplikasi. Tidak ada yang ditulis ke SharePoint; coba lagi.',
    'row_unsupported' => 'Baris ini tidak bisa ditulis ke workbook: bentuk sheet atau sel di file ini belum didukung. Ubah di Excel.',
    'row_saved_meanwhile' => 'Workbook disimpan orang lain di SharePoint sejak dibaca. Tidak ada yang ditulis; muat ulang halaman, lalu ulangi.',

    // What is typed as the new value.
    'bad_characters' => 'Teks mengandung karakter yang tidak bisa disimpan di Excel.',
    'too_long' => 'Teks terlalu panjang (paling banyak :max karakter).',
    'number' => 'Kolom ini berisi angka: isi dengan angka saja, paling banyak 15 digit (misalnya 1500 atau 1.500,50), atau kosongkan.',
    'date' => 'Kolom ini berisi tanggal: isi dengan format hh-bb-tttt (misalnya 15-03-2023), atau hh-bb-tttt jj:mm bila ada jamnya.',

    // Why an approved change was not written, or that it was there already.
    'app_error' => 'Terjadi kesalahan di aplikasi saat menulis ke SharePoint. Tidak ada yang ditulis; coba lagi, atau hubungi admin.',
    'already' => 'Sel ini sudah berisi nilai itu di SharePoint, jadi tidak ada yang ditulis.',
    'not_kept' => 'Workbook yang sudah diubah tidak bisa disimpan sementara di server aplikasi. Tidak ada yang ditulis ke SharePoint; coba setujui lagi.',
    'cell_unsupported' => 'Sel ini tidak bisa ditulis ke workbook: bentuk sheet atau sel di file ini belum didukung.',
    'sheet_gone' => 'Sheet ":sheet" tidak ditemukan lagi di workbook (mungkin diganti namanya). Tidak ada yang ditulis.',
    'unreadable' => 'Isi workbook di SharePoint tidak bisa dibaca sebagai workbook Excel. Tidak ada yang ditulis; coba lagi, atau hubungi admin.',
    'now_formula' => 'Sel :cell sekarang berisi rumus, dan tidak ditimpa. Tidak ada yang ditulis.',
    'cell_kind' => 'Sel :cell bukan jenis sel yang bisa ditulis dari sini. Ubah di Excel.',
    'row_moved' => 'Baris :line sudah berubah atau bergeser di SharePoint sejak diajukan (kolom pengenalnya tidak sama lagi). Tidak ada yang ditulis; ajukan lagi.',
    'cell_changed' => 'Isi sel :cell sudah berubah di SharePoint sejak diajukan (sekarang: ":now"). Tidak ada yang ditulis; ajukan lagi.',
    'date1904' => 'Workbook ini menghitung tanggal dari tahun 1904; angka dan tanggal belum bisa ditulis dari sini. Ubah di Excel.',

    // The notifications.
    'notify' => [
        'requested_title' => 'Pengajuan perubahan menunggu persetujuan',
        'requested_body' => ':name mengajukan :place: :old → :new.',
        'applied_title' => 'Perubahan Anda sudah diterapkan',
        'applied_body' => ':place: :new sudah ditulis ke workbook (disetujui :name).',
        'rejected_title' => 'Pengajuan Anda ditolak',
        'rejected_body' => ':place: ditolak oleh :name.',
        'rejected_body_note' => ':place: ditolak oleh :name: :note.',
        'conflict_title' => 'Perubahan Anda tidak diterapkan',
        'failed_title' => 'Perubahan Anda belum bisa diterapkan',
        'failure_body' => ':place: :failure',
    ],
];
