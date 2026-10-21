<?php

// The page "Notifikasi" (Notifications::index): every notification of whoever is signed in, by day, in tabs. "Mark all
// as read" and "Unread" are the bell's words (lang/<code>/ui.php); a notification's own title and text are kept in the
// language they were written in.

return [
    'tabs' => 'Notifikasi yang ditampilkan',
    'all' => 'Semua',
    // The tab of each category (UserNotification::CATEGORIES), also the word beside a title.
    'categories' => [
        'radio' => 'Radio',
        'documents' => 'Dokumen',
        'system' => 'Sistem',
    ],
    'not_ready' => 'Notifikasi belum aktif di database ini: jalankan <code>php artisan migrate</code> lebih dulu.',
    'empty' => [
        'all_read' => 'Semua notifikasi sudah dibaca',
        'all_read_text' => 'Tidak ada yang menunggu untuk dibaca.',
        'none' => 'Belum ada notifikasi',
        'none_text' => 'Notifikasi muncul di sini saat ada yang perlu Anda ketahui.',
        'none_kind' => 'Belum ada di sini',
        'none_kind_text' => 'Notifikasi jenis ini muncul di sini.',
    ],
    // The rows by day, and when each came: "12 menit yang lalu" today, "Kemarin, 16:40", "17 Okt, 08:00" before.
    'groups' => [
        'today' => 'Hari ini',
        'yesterday' => 'Kemarin',
        'earlier' => 'Sebelumnya',
    ],
    'yesterday_at' => 'Kemarin, :time',
    'mark_read' => 'Tandai dibaca',
    'range' => ':from – :to dari :total',
    'previous' => 'Sebelumnya',
    'next' => 'Berikutnya',

    // The words of the page's script.
    'script' => [
        'marked' => 'Semua notifikasi ditandai sudah dibaca.',
        'failed' => 'Notifikasi tidak bisa ditandai dibaca. Coba lagi.',
        'failed_one' => 'Notifikasi itu tidak bisa ditandai dibaca. Coba lagi.',
    ],
];
