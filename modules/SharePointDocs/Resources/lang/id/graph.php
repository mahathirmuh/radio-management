<?php

// Why SharePoint could not be read or written (GraphException), by the reason, in words a person can act on: what
// Microsoft said goes to the log only. "user": a folder or a file; "workbook": a workbook (its other reasons are said
// as "user"); "write": putting a change into a workbook (its other reasons are said as "workbook").

return [
    'user' => [
        'not_configured' => 'Alamat folder SharePoint atau akun aplikasi Microsoft belum diisi di pengaturan (.env).',
        'credentials' => 'Aplikasi tidak bisa masuk ke Microsoft. Periksa tenant, client id dan client secret di pengaturan (client secret mungkin sudah kedaluwarsa).',
        'forbidden' => 'Aplikasi tidak punya izin membaca folder ini di SharePoint (perlu izin aplikasi Sites.Read.All dengan admin consent).',
        'not_found' => 'Folder tidak ditemukan di SharePoint. Mungkin sudah dipindahkan atau dihapus, atau alamat di pengaturan keliru.',
        'not_a_folder' => 'Alamat di pengaturan menunjuk ke sebuah file, bukan folder.',
        'unsupported' => 'File ini tidak bisa ditampilkan di sini (hanya PDF dan gambar). Buka di SharePoint.',
        'too_large' => 'File ini terlalu besar untuk ditampilkan di sini. Buka di SharePoint.',
        'too_slow' => 'File ini terlalu lama diambil dari SharePoint (koneksi lambat atau file besar). Buka di SharePoint.',
        'out_of_time' => 'Waktu untuk satu permintaan habis sebelum SharePoint sempat ditanya. Muat ulang halaman beberapa saat lagi.',
        'unavailable' => 'SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.',
    ],

    'workbook' => [
        'not_configured' => 'Alamat workbook atau akun aplikasi Microsoft belum diisi di pengaturan (.env).',
        'not_found' => 'Workbook tidak ditemukan di SharePoint. Mungkin sudah dipindahkan, diganti namanya atau dihapus, atau alamat di pengaturan keliru.',
        'forbidden' => 'Aplikasi tidak punya izin membaca workbook ini di SharePoint (perlu izin aplikasi Sites.Read.All dengan admin consent).',
        'unsupported' => 'Alamat di pengaturan bukan workbook Excel (.xlsx) yang bisa dibaca aplikasi (file yang diberi password tidak bisa dibaca).',
        'too_large' => 'Workbook ini terlalu besar untuk ditampilkan di sini.',
        'too_slow' => 'Workbook terlalu lama diambil dari SharePoint atau dibaca (koneksi lambat atau workbook sangat besar). Coba lagi beberapa saat lagi.',
    ],

    'write' => [
        'forbidden' => 'Aplikasi belum punya izin menulis ke workbook ini di SharePoint (perlu izin aplikasi Sites.Selected dengan akses write untuk site ini, atau Sites.ReadWrite.All, dengan admin consent).',
        'changed' => 'Workbook disimpan orang lain di SharePoint ketika perubahan ini sedang diterapkan. Tidak ada yang ditulis; coba setujui lagi.',
        'locked' => 'Workbook sedang dikunci karena dibuka orang lain di Excel. Tidak ada yang ditulis; coba lagi beberapa saat lagi.',
        'not_found' => 'Workbook tidak ditemukan di SharePoint. Mungkin sudah dipindahkan, diganti namanya atau dihapus.',
        'unavailable' => 'SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab. Tidak ada yang ditulis; coba lagi beberapa saat lagi.',
    ],
];
