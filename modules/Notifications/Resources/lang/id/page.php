<?php

// The page "Notifikasi" (Notifications::index): every notification of whoever is signed in, by day. The title, "Mark all
// as read" and "Unread" are the bell's words (lang/<code>/ui.php); a notification's own title and text are kept in the
// language they were written in.

return [
    'sub' => 'Pengajuan perubahan workbook, token API yang hampir berakhir, dan ringkasan mingguan register. Yang sudah dibaca dihapus otomatis setelah 90 hari.',
    'unread_count' => ':count belum dibaca.',
    'tabs' => 'Notifikasi yang ditampilkan',
    'all' => 'Semua',
    'not_ready' => 'Notifikasi belum aktif di database ini: jalankan <code>php artisan migrate</code> lebih dulu.',
    'empty' => [
        'all_read' => 'Semua notifikasi sudah dibaca',
        'all_read_text' => 'Tidak ada yang menunggu untuk dibaca.',
        'none' => 'Belum ada notifikasi',
        'none_text' => 'Notifikasi muncul di sini saat ada yang perlu Anda ketahui.',
    ],
    // The rows by day.
    'groups' => [
        'today' => 'Hari ini',
        'yesterday' => 'Kemarin',
        'earlier' => 'Sebelumnya',
    ],
    // The word of each kind, beside its title.
    'kinds' => [
        'edit_requested' => 'Persetujuan',
        'edit_decided' => 'Pengajuan',
        'token_expiring' => 'Token API',
        'register_weekly' => 'Register',
    ],
    'range' => ':from – :to dari :total',
    'previous' => 'Sebelumnya',
    'next' => 'Berikutnya',

    // The words of the page's script.
    'script' => [
        'all_read' => 'Semua notifikasi sudah dibaca.',
        'marked' => 'Semua notifikasi ditandai sudah dibaca.',
        'failed' => 'Notifikasi tidak bisa ditandai dibaca. Coba lagi.',
    ],
];
