<?php

// Import Excel (import.index): the form, the report of the last import as its script draws it (radioImport.words), and
// what the server says back (RadioImportController, RadioImports, ImportProblem, RadioImportService, and the API that runs
// the same import). The Excel format itself (sheets, columns) is the old register's and is not translated, nor are the
// reasons a radio is marked "Perlu dicek": the import keeps them with the radio.

return [
    'title' => 'Impor register radio dari Excel',
    'sub' => 'Membaca sheet <b>Form</b> (dan <b>to Konawe</b>) dari file register lama, misalnya <span class="radio-mono">Radio Register 07-08-25.xlsx</span>.',
    'rules' => [
        'rows' => '1 baris berisi SN atau User Name = 1 radio. Baris yang hanya berisi No + Radio ID (slot kosong) dilewati.',
        'tidy' => 'Ejaan, spasi, dan isi kolom Status dirapikan otomatis menjadi <b>Posisi</b> + <b>Kondisi</b>.',
        'conflicts' => 'Data yang bertentangan (SN/ID dobel, status vs catatan, dll.) <b>tidak ditebak</b>: radio tetap diimpor dan ditandai <b>Perlu dicek</b>.',
        'original' => 'Isi asli setiap baris disimpan dan bisa dilihat di halaman detail radio.',
    ],
    'file' => 'File Excel (.xlsx)',
    'pick_file' => 'Pilih file',
    'konawe' => 'Impor juga sheet "to Konawe" (radio pindah site)',
    'fresh' => 'Hapus semua data radio & riwayat yang ada sebelum impor',
    'existing' => 'Saat ini ada <b>:count</b> radio di database. Impor ulang hanya bisa dilakukan dengan menghapus data lama (master data tetap dipertahankan).',
    'no_delete' => 'Role Anda tidak punya izin <b>Delete</b> pada menu Register Radio, jadi impor ulang tidak bisa dilakukan dari akun ini.',
    'start' => 'Mulai impor',
    'none' => 'Belum ada impor.',

    // What the script of the page writes (radioImport.words).
    'page' => [
        'no_file' => 'Belum ada file dipilih',
        'pick_first' => 'Pilih file Excel (.xlsx) terlebih dahulu.',
        'confirm_title' => 'Mulai impor?',
        'confirm_fresh' => '<b>Semua data radio dan riwayat yang ada akan dihapus</b> lalu diganti isi file ini.',
        'confirm_add' => 'Data dari file akan ditambahkan ke database.',
        'confirm' => 'Impor',
        'importing' => 'Mengimpor…',
        'wait' => 'Mohon tunggu, proses bisa memakan waktu sekitar 1 menit.',
        'done' => ':count radio diimpor, :flagged ditandai "Perlu dicek".',
        'last' => 'Hasil impor terakhir',
        'seconds' => ':seconds detik',
        'imported' => 'Radio diimpor',
        'active' => 'Aktif',
        'archived' => 'Diarsipkan',
        'flagged' => 'Perlu dicek',
        'summary' => 'Slot ID kosong dilewati: <b>:slots</b> · Master baru: tipe :types, perusahaan :companies, department :departments, template :templates',
        'skipped' => '<b>Baris dilewati</b> (ada isi tapi tanpa SN/User Name):',
        'skipped_row' => ':sheet baris :row: :reason',
        'flags' => 'Radio yang perlu dicek',
        'open_register' => 'Buka di Register Radio',
        'row' => 'Baris',
        'sn' => 'SN',
        'reason' => 'Alasan',
    ],

    // What the server says back.
    'messages' => [
        'not_allowed' => 'Tidak diizinkan',
        'replace_refused' => 'Menghapus data lama membutuhkan izin Delete pada menu Register Radio.',
        'busy_text' => 'Data radio sedang disimpan atau impor lain masih berjalan. Tunggu sebentar lalu coba lagi.',
        'done' => 'Impor selesai.',
        'file_required' => 'Pilih file Excel (.xlsx) terlebih dahulu.',
        'file_type' => 'File harus berformat .xlsx.',
        'file_size' => 'Ukuran file maksimal 20 MB.',
        'refused' => 'Impor dibatalkan',
        'failed' => 'Impor gagal',
        'database' => 'Terjadi kesalahan database saat impor, tidak ada data yang berubah. Detailnya ada di log aplikasi.',
        'error' => 'Terjadi kesalahan saat impor: :error',
        'not_empty' => 'Data radio sudah ada. Pilih opsi "hapus data lama" untuk mengimpor ulang dari Excel.',
        'open_tickets' => 'Masih ada :count tiket servis yang terbuka. Impor yang menghapus data lama baru bisa dijalankan setelah semua radio itu kembali (menu Servis & Perbaikan).',
        'open_opnames' => 'Masih ada :count sesi stock opname yang terbuka. Kirim sesi itu ke register dulu (menu Stock Opname), baru impor yang menghapus data lama bisa dijalankan.',
        'no_sheet' => 'Sheet ":sheet" tidak ditemukan di file ini.',
        'no_column' => 'Kolom ":column" tidak ditemukan di sheet :sheet.',
    ],
];
