<?php

// The page "E-Sign Master" (e-sign.index, ESignController; the API's E-Sign endpoints for their messages): the list of
// the signatures of the people at ICT, the dialog to register or change one, the record of where one was put, the page
// with the pad (e-sign.pad; the pad itself speaks RadioManagement::checkout.pad), and what a picture of a signature is
// refused for (SignatureImage, also for the handover). The names of the people and the record of the forms are data.

return [
    'search' => 'Cari nama atau e-mail…',
    'search_label' => 'Cari nama atau e-mail',
    'search_button' => 'Cari',
    'view' => 'Tampilan',
    'view_grid' => 'Grid',
    'view_list' => 'List',
    'add' => 'Daftarkan tanda tangan',
    'banner' => 'Di form, yang muncul adalah tanda tangan <b>akun yang sedang membuka form itu</b>: tidak ada yang bisa menempelkan tanda tangan orang lain. Setiap pemakaian dicatat (tombol riwayat). Gambar disimpan terenkripsi.',

    'columns' => [
        'no' => 'No',
        'account' => 'Akun',
        'name' => 'Nama tercetak',
        'signature' => 'Tanda tangan',
        'status' => 'Status',
        'uses' => 'Dipakai',
        'actions' => 'Aksi',
    ],

    // The rows of the list and the cards of the grid (the page's script).
    'list' => [
        'signature_of' => 'Tanda tangan :name',
        'account_gone' => 'Akun dihapus',
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'times' => ':count kali',
        'last' => 'terakhir :at',
        'edit' => 'Edit',
        'uses' => 'Riwayat pemakaian',
        'delete' => 'Hapus',
        'change' => 'Ganti',
        'empty' => 'Belum ada tanda tangan yang didaftarkan',
        'load_failed' => 'Gagal memuat data.',
        'no_picture' => 'Tidak ada gambar',
        'updated' => 'Diperbarui :at · :count kali dipakai',
    ],

    'dialog' => [
        'add' => 'Daftarkan tanda tangan',
        'edit' => 'Edit tanda tangan',
        'owner' => 'Akun pemilik',
        'owner_hint' => 'Tanda tangan ini hanya muncul di form yang dibuka dengan akun ini.',
        'owner_fixed' => 'Pemilik tanda tangan tidak bisa diganti. Untuk akun lain, daftarkan tanda tangan baru.',
        'choose' => 'Pilih akun…',
        'account_gone' => '(akun dihapus)',
        'name' => 'Nama tercetak di bawah tanda tangan',
        'name_hint' => 'Kosongkan untuk memakai nama akun',
        'active' => 'Aktif (nonaktif: tidak muncul di form, kotaknya ditandatangani di kertas)',
        'current' => 'Tanda tangan sekarang',
        'signature' => 'Tanda tangan',
        'signature_new' => 'Tanda tangan baru (biarkan kosong bila tidak diganti)',
        'pad_open' => 'Tanda tangan belum dipakai. Tekan "Pakai tanda tangan" atau "Batal" di kotaknya dulu.',
        'session' => 'Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.',
    ],

    'delete' => [
        'title' => 'Hapus tanda tangan ini?',
        'text' => 'Tanda tangan tidak lagi muncul di form. Riwayat pemakaiannya tetap tersimpan.',
        'confirm' => 'Hapus',
    ],

    'uses' => [
        'title' => 'Riwayat pemakaian',
        'at' => 'Waktu',
        'radio' => 'Form radio',
        'user' => 'Dibuka oleh',
        'total' => ':count kali dipakai.',
        'total_shown' => ':count kali dipakai, ditampilkan :shown terakhir.',
        'never' => 'Belum pernah dipakai di form.',
    ],

    // The page with the pad (e-sign.pad), around the pad of the form.
    'pad' => [
        'hint' => 'Dua cara: <b>klik kotak</b> untuk tanda tangan di layar (pena, jari, atau mouse), atau <b>seret &amp; lepas / unggah file</b> gambar tanda tangan.',
        'drop' => 'atau seret & lepas file gambar tanda tangan ke sini',
        'or' => 'atau',
        'upload' => 'Unggah file tanda tangan',
        'upload_hint' => 'PNG atau JPG, tinta gelap di latar putih',
        'not_image' => 'File harus berupa gambar PNG atau JPG.',
        'too_big' => 'File terlalu besar (paling besar 5 MB).',
        'unreadable' => 'File itu tidak bisa dibaca.',
        'not_picture' => 'File itu bukan gambar yang bisa dibaca.',
        'no_ink' => 'Tidak ada tanda tangan yang terlihat di gambar itu. Tintanya harus gelap di atas latar putih atau terang.',
    ],

    // What the server says (ESignController, the API).
    'messages' => [
        'not_ready' => 'Tabel tanda tangan belum dibuat di database. Jalankan "php artisan migrate" dulu.',
        'choose_owner' => 'Pilih akun pemilik tanda tangan.',
        'owner_missing' => 'Akun itu tidak ditemukan.',
        'owner_has_one' => 'Akun itu sudah punya tanda tangan. Ganti lewat tombol Edit.',
        'no_signature' => 'Tanda tangan belum ada. Klik kotaknya untuk tanda tangan di layar, atau unggah file gambarnya.',
        'name_required' => 'Nama yang tercetak di bawah tanda tangan harus diisi.',
        'registered' => 'Tanda tangan :name berhasil didaftarkan.',
        'saved' => 'Tanda tangan :name berhasil disimpan.',
        'deleted' => 'Tanda tangan :name dihapus.',
        'deactivated' => 'Tanda tangan :name dinonaktifkan.',
        'inactive_already' => 'Tanda tangan :name memang sudah nonaktif.',
    ],

    // Why a picture of a signature is refused (SignatureImage).
    'picture' => [
        'not_png' => 'Tanda tangan harus berupa gambar PNG dari kotak tanda tangan.',
        'bad_size' => 'Ukuran gambar tanda tangan tidak sesuai.',
        'unreadable' => 'Gambar tanda tangan tidak bisa dibaca.',
        'empty' => 'Tanda tangan masih kosong.',
        'not_saved' => 'Gambar tanda tangan tidak bisa disimpan.',
    ],
];
