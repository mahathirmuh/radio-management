<?php

// The pages of an error (resources/views/errors) and the page shown offline.

return [
    'forbidden' => [
        'title' => 'Akses ditolak',
        'heading' => 'Anda tidak punya akses ke halaman ini',
        'reason' => 'Role Anda belum diberi akses ke menu ini.',
        'ask' => 'Hubungi admin aplikasi bila akses ini memang diperlukan.',
        'home' => 'Ke halaman utama',
        'sign_out' => 'Keluar',
    ],
    'not_found' => [
        'title' => 'Halaman tidak ditemukan',
        'heading' => 'Halaman tidak ditemukan',
        'redirect' => 'mengarahkan ke halaman yang aktif...',
    ],
    'maintenance' => [
        'title' => 'Sedang dalam perbaikan',
        'heading' => 'Aplikasi sedang dalam perbaikan',
    ],
    'offline' => [
        'title' => 'Offline',
        'heading' => 'Anda sedang offline!',
        'text' => 'Periksa koneksi internet Anda.',
    ],
];
