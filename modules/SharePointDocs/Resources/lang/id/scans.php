<?php

// The page of a SharePoint folder (COF Scan: FolderController, index.blade.php): the cards over the list, the toolbar,
// the table, the file viewer, what the page's script says, and the answers of its routes. Names of files and folders
// come from SharePoint and are never translated.
//
// "folders": the words of the folders of config/sharepoint-docs.php by their key. They are shown while the config still
// says what the Indonesian ones here say; a folder that is not here, or whose config has been changed, shows the
// config's own words.

return [
    'not_configured_title' => ':title belum bisa ditampilkan.',
    'not_configured' => 'Alamat folder SharePoint atau akun aplikasi Microsoft belum diisi di file <code>.env</code> (lihat README, bagian "Dokumen SharePoint").',

    'synced' => 'Sinkron terakhir',
    'linked' => 'Tertaut ke baris',
    'linked_by' => 'Dicocokkan lewat :what',
    'document_number' => 'nomor dokumen',
    'unmatched' => 'Tidak tertaut',
    'unmatched_hint' => 'Tidak ada baris, atau nama beda',
    'unmatched_bar' => 'Barisnya tidak ada, atau link-nya menyebut file lain. Perbaiki link-nya di Excel.',
    'review' => 'Tinjau',

    'search' => 'Cari',
    'search_split' => ':code atau :label…',
    'search_name' => 'Nama file…',
    'status_label' => 'Status di Masterlist',
    'all' => 'Semua',
    'status_linked' => 'Tertaut',
    'status_unmatched' => 'Tidak tertaut',
    'reset_hint' => 'Reset pencarian, pilihan nomor, dan urutan',
    'open_sharepoint' => 'Buka di SharePoint',
    'help' => 'Tombol <i class="ti ti-eye"></i> menampilkan file di sini; klik namanya untuk membukanya di SharePoint dengan akun Microsoft Anda.',
    'help_register' => 'Kolom Masterlist dan Catatan dari sheet :sheet di :workbook: baris yang menyebut file itu, dan catatannya.',
    'show_all' => 'Tampilkan semua file',

    'columns' => [
        'no' => 'No',
        'name' => 'Nama',
        'masterlist' => 'Masterlist',
        'note' => 'Catatan',
        'type' => 'Tipe',
        'modified' => 'Diubah',
        'by' => 'Oleh',
        'size' => 'Ukuran',
        'view' => 'Lihat',
    ],

    'viewer' => [
        'previous' => 'File sebelumnya',
        'next' => 'File berikutnya',
        'new_tab' => 'Buka di tab baru',
        'loading' => 'Memuat file…',
    ],

    // What the script of the page says (spUi, spDocs, spViewer).
    'script' => [
        'open_folder' => 'Buka folder di SharePoint',
        'folder' => 'Folder',
        'row' => 'Baris :line',
        'row_hint' => 'Baris di Masterlist yang menyebut file ini',
        'differs' => 'Nama beda · baris :line',
        'differs_hint' => 'Ada baris dengan nomor ini, tetapi link-nya kosong atau menyebut nama file yang lain (atau file ini salinan ganda dari file yang sudah disebut)',
        'unlinked' => 'Tidak ada baris',
        'unlinked_hint' => 'Tidak ada baris di Masterlist untuk file ini',
        'and_more' => '(dan :count baris lain)',
        'click_row' => 'Klik untuk melihat barisnya di :workbook',
        'see_row' => ':text: lihat barisnya di :workbook',
        'view_file' => 'Lihat file',
        'not_here' => 'Tidak bisa ditampilkan di sini: buka di SharePoint',
        'open_sharepoint' => 'Buka di SharePoint',
        'no_documents' => 'Tidak ada dokumen',
        'load_failed' => 'Daftar tidak bisa dimuat.',
        'load_failed_retry' => 'Daftar tidak bisa dimuat. Coba lagi beberapa saat lagi.',
        'no_read' => 'Role Anda tidak punya izin Read untuk menu ini.',
        'only_code' => 'Hanya file dengan nomor :code.',
        'none_linked' => 'Tidak ada file yang tertaut ke baris.',
        'none_linked_among' => 'Tidak ada file yang tertaut ke baris di antara yang dicari.',
        'none_unmatched' => 'Tidak ada file yang tidak tertaut.',
        'none_unmatched_among' => 'Tidak ada file yang tidak tertaut di antara yang dicari.',
        'none_code' => 'Tidak ada file dengan nomor :code.',
        'none_code_search' => 'Tidak ada file dengan nomor :code yang cocok dengan pencarian.',
        'none_search' => 'Tidak ada dokumen yang cocok dengan pencarian.',
        'empty_folder' => 'Folder ini kosong.',
        'synced_hint' => ':day · :count file di folder',
        'unmatched_text' => ':count file tidak cocok dengan baris di :workbook.',
        'folders' => ':count folder',
        'files' => ':count file',
        'data_at' => 'data SharePoint per :time',
        'book_at' => ':workbook per :time',
        'stale' => 'SharePoint sedang tidak bisa dihubungi. Yang tampil adalah daftar terakhir yang berhasil diambil (:time).',
        'truncated' => 'Isi folder ini sangat banyak: hanya sebagian yang ditampilkan. Buka di SharePoint untuk melihat semuanya.',
        'register_stale' => 'SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab. Kolom Masterlist dan Catatan memakai isi terakhir :workbook yang berhasil diambil (:time).',
        'frame' => 'Isi file',
        'loading' => 'Memuat file…',
        'still_loading' => 'Masih memuat… Bila file tidak juga tampil, buka lewat tombol "tab baru" atau "SharePoint" di kanan atas.',
    ],

    // The answers of the routes of the page.
    'folder_unknown' => 'Folder tidak dikenali.',
    'file_unknown' => 'File tidak dikenali.',
    'file_gone' => 'File tidak ditemukan di SharePoint. Mungkin sudah dipindahkan atau dihapus; muat ulang daftarnya.',

    'folders' => [
        'cof-scan' => [
            'code' => 'No. COF',
            'label' => 'Nama',
        ],
    ],
];
