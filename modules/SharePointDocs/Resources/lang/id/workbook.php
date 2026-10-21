<?php

// The page of an Excel workbook in SharePoint (ICT Masterlist: WorkbookController, workbook.blade.php): the toolbar, the
// cards and the column "File scan" of a sheet that is checked against a folder, the dialogs that ask for a change of a
// cell or write a row, what the page's script says, and the answers of its routes. Sheet names, column titles and cells
// come from the workbook and are never translated ("File scan" is the name of the column the application adds).
//
// "workbooks": the words of the workbooks of config/sharepoint-docs.php by their key, shown like the folders' in
// scans.php (while the config still says what the Indonesian ones here say).

return [
    'not_configured_title' => ':title belum bisa ditampilkan.',
    'not_configured' => 'Alamat workbook di SharePoint atau akun aplikasi Microsoft belum diisi di file <code>.env</code> (lihat README, bagian "Dokumen SharePoint").',
    'read_only' => 'Halaman ini hanya menampilkan; mengubah data tetap dilakukan di Excel.',
    'search' => 'Cari di semua kolom',
    'search_hint' => 'Nama, nomor dokumen, serial number, …',
    'reset_hint' => 'Reset pencarian, kartu dan baris yang dipilih, dan urutan',
    'open_excel' => 'Buka di Excel',
    'show_all' => 'Tampilkan semua baris',
    'cards' => 'Ringkasan kolom File scan',
    'legend' => '<b>Ada</b>: file dengan nama yang sama seperti di link (huruf besar atau kecil tidak dibedakan). <b>Nama beda</b>: ada file dengan nomor yang sama, tetapi link-nya kosong atau menyebut nama lain (spasi lebih atau kurang, misalnya). <b>Subfolder</b>: link menunjuk ke subfolder, biasanya karena nama pemohon mengandung tanda "/". <b>Belum ada</b>: tidak ada file dengan nama atau nomor itu. Klik sebuah kartu untuk hanya melihat barisnya; pencarian tidak mencari di kolom ini.',
    'sheets' => 'Sheet workbook',

    // Asking for a change of one cell.
    'ask' => [
        'title' => 'Ajukan perubahan sel',
        'current' => 'Isi sekarang',
        'new' => 'Isi baru',
        'hint' => 'Kosongkan untuk mengosongkan sel. Kolom angka diisi angka (1500 atau 1.500,50); kolom tanggal dengan format hh-bb-tttt (15-03-2023).',
        'not_yet' => 'Perubahan <b>belum</b> tersimpan di SharePoint. Orang lain yang berwenang harus menyetujuinya dulu di halaman Persetujuan Edit; Anda tidak bisa menyetujui pengajuan Anda sendiri.',
        'send' => 'Ajukan perubahan',
    ],

    // Writing the cells of one row straight away.
    'row' => [
        'title' => 'Edit baris',
        'note' => 'Langsung ditulis ke workbook di SharePoint, tanpa persetujuan orang lain; tercatat di Audit log.',
        'save' => 'Simpan',
    ],

    // What the script of the page says (wbUi, wbBook, wbEdit, wbRow).
    'script' => [
        // The column "File scan": the words of each status, and what it means (by the code of the status).
        'statuses' => [
            'ok' => 'Ada',
            'missing' => 'Belum ada',
            'differs' => 'Nama beda',
            'below' => 'Subfolder',
        ],
        'status_hints' => [
            'ok' => 'Ada file dengan nama yang sama seperti di link (huruf besar atau kecil tidak dibedakan)',
            'missing' => 'Tidak ada file dengan nama atau nomor ini di folder',
            'differs' => 'Ada file dengan nomor ini, tetapi link di Excel kosong atau menyebut nama lain (spasi lebih atau kurang, misalnya)',
            'below' => 'Link menunjuk ke subfolder: biasanya karena nama pemohon mengandung tanda "/"',
        ],
        'click_file' => 'Klik untuk melihat file ini di :folder',
        'see_file' => ':text: lihat file di :folder',
        'waiting' => 'Menunggu persetujuan: :value',
        'waiting_badge' => 'menunggu',
        'formula' => 'Sel ini berisi rumus: ubah di Excel',
        'formula_label' => 'Berisi rumus',
        'ask' => 'Ajukan perubahan sel ini',
        'edit_row' => 'Edit baris ini',
        'edit_row_label' => 'Edit baris :line',
        'row' => 'Baris',
        'no_rows' => 'Tidak ada baris',
        'hint_direct' => 'Klik ikon pensil di nomor baris untuk mengubah baris itu. Perubahan langsung ditulis ke SharePoint dan tercatat di Audit log.',
        'hint_ask' => 'Klik ikon pensil di sebuah sel untuk mengajukan perubahannya. Isi workbook baru berubah setelah disetujui orang lain di halaman Persetujuan Edit.',
        'read_only' => 'Halaman ini hanya menampilkan; mengubah data tetap dilakukan di Excel.',
        'load_failed' => 'Isi workbook tidak bisa dimuat.',
        'load_failed_retry' => 'Isi workbook tidak bisa dimuat. Coba lagi beberapa saat lagi.',
        'none_lines' => 'Baris Excel :lines tidak ada di sheet ini, atau tidak cocok dengan pencarian atau kartu yang dipilih.',
        'none_status' => 'Tidak ada baris dengan status ":status" di sheet ini.',
        'none_status_search' => 'Tidak ada baris dengan status ":status" yang cocok dengan pencarian di sheet ini.',
        'none_search' => 'Tidak ada baris yang cocok dengan pencarian di sheet ini.',
        'empty_sheet' => 'Sheet ini kosong.',
        'only_lines' => 'Hanya baris Excel :lines.',
        'no_read' => 'Role Anda tidak punya izin Read untuk menu ini.',
        'search' => 'Cari di semua kolom',
        'search_except' => 'Cari di semua kolom kecuali File scan',
        'total' => 'Total baris',
        'cards_info' => 'Status dicek terhadap folder :folder (data folder per :time).',
        'rows' => ':count baris di sheet ":sheet"',
        'rows_matching' => ':count baris cocok di sheet ":sheet"',
        'modified' => 'workbook terakhir diubah :time',
        'data_at' => 'data SharePoint per :time',
        'stale' => 'SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab. Yang tampil adalah isi terakhir yang berhasil diambil (:time).',
        'scan_stale' => 'SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab. Kolom File scan dicek terhadap daftar terakhir folder :folder yang berhasil diambil (:time).',
        'truncated' => 'Sheet ini sangat besar: hanya sebagian yang ditampilkan.',
        'open_all' => 'Buka di Excel untuk melihat semuanya.',
        'where' => 'Kolom ":column", baris Excel :line',
        'empty' => '(kosong)',
        'no_update' => 'Role Anda tidak punya izin Update untuk menu ini.',
        'send_failed' => 'Pengajuan tidak bisa dikirim. Coba lagi beberapa saat lagi.',
        'sent' => 'Pengajuan dikirim',
        'hint_scan' => 'Kolom pemeriksaan file scan: tidak diubah dari sini.',
        'hint_formula' => 'Berisi rumus: ubah di Excel.',
        'hint_waiting' => 'Menunggu persetujuan: :value. Putuskan dulu di Persetujuan Edit.',
        'hint_fixed' => 'Tidak diubah dari sini.',
        'row_title' => 'Edit baris :line',
        'row_where' => 'Sheet :sheet · langsung tersimpan ke SharePoint',
        'nothing_changed' => 'Tidak ada isi yang berubah.',
    ],

    // The answers of the routes of the page.
    'asked' => 'Pengajuan perubahan sel :cell dikirim. Isi workbook baru berubah setelah disetujui.',
    'column_unknown' => 'Kolom yang diubah tidak dikenal. Muat ulang halaman.',
    'row_same' => 'Isi baris :line di SharePoint sudah seperti itu; tidak ada yang ditulis.',
    'row_saved' => 'Baris :line tersimpan ke SharePoint (:count sel).',
];
