<?php

// The texts of the frame of every page (top bar, sidebar, bell, footer) and of the shared helpers of the pages' scripts
// (main/components/js): tables, pagination, pickers, dialogs, the session's warning.

return [
    'ok' => 'OK',
    'cancel' => 'Batal',
    'close' => 'Tutup',
    'sorry' => 'Maaf!',

    'language' => [
        'title' => 'Bahasa',
    ],

    'topbar' => [
        'toggle_sidebar' => 'Lebarkan atau ciutkan menu',
        'breadcrumb' => 'Jalur halaman',
        'user_menu' => 'Menu pengguna',
        'notifications' => 'Notifikasi',
        'sign_out' => 'Keluar',
    ],

    'sidebar' => [
        'label' => 'Menu utama',
        'open' => 'Buka menu',
        'sign_out' => 'Keluar (:name)',
        'edit_requests' => ':count pengajuan menunggu',
        'attention' => ':count item perlu perhatian',
    ],

    'bell' => [
        'title' => 'Notifikasi',
        'read_all' => 'Tandai semua dibaca',
        'empty' => 'Belum ada notifikasi',
        'see_all' => 'Lihat semua notifikasi',
        'unread' => 'Belum dibaca',
    ],

    'changelog' => [
        'open' => 'Lihat perubahan tiap versi',
        'title' => 'Riwayat versi',
        'sub' => 'Versi yang berjalan: v:version. Yang terbaru di atas.',
        'loading' => 'Memuat riwayat versi…',
        'failed' => 'Riwayat versi tidak bisa dimuat. Tutup lalu buka lagi untuk mencoba sekali lagi.',
        'empty' => 'Belum ada catatan perubahan.',
        'current' => 'Versi ini',
    ],

    'table' => [
        'loading' => 'Memuat...',
        'empty' => 'Tidak ada data',
        'showing' => 'Menampilkan',
        'of' => 'dari',
        'rows' => 'Baris per halaman',
        'previous' => 'Halaman sebelumnya',
        'next' => 'Halaman berikutnya',
        // How the pages write 1011: 1.011.
        'thousands' => '.',
    ],

    'pickers' => [
        'months' => ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
        'short_months' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        // Monday first.
        'weekdays' => ['Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb', 'Mg'],
        'all' => 'Semua',
        'pick_all' => 'Pilih semua',
        'clear' => 'Kosongkan',
        'any_date' => 'Semua tanggal',
        'previous_month' => 'Bulan sebelumnya',
        'next_month' => 'Bulan berikutnya',
        'today' => 'Hari ini',
    ],

    'session' => [
        'hours' => ':count jam',
        'minutes' => ':count menit',
        'ending' => 'Sesi akan berakhir',
        'idle' => 'Tidak ada aktivitas selama hampir :minutes menit.',
        'maximum' => 'Demi keamanan, satu sesi paling lama :maximum; simpan pekerjaan Anda.',
        'countdown' => 'Anda akan keluar otomatis dalam :countdown.',
        'stay' => 'Tetap masuk',
        'sign_in_again' => 'Login ulang sekarang',
        'sign_out' => 'Keluar',
        'later' => 'Nanti',
        'ended' => 'Sesi berakhir',
        'ended_text' => 'Sesi Anda sudah berakhir. Silakan login lagi.',
        'sign_in' => 'Login',
    ],

    'flash' => [
        'success' => 'Berhasil',
        'error' => 'Gagal',
        'warning' => 'Peringatan',
    ],

    'loader' => [
        'title' => 'Memproses dokumen',
        'upload' => 'Unggahan Anda sedang diproses. Jangan tinggalkan halaman ini supaya prosesnya tidak hilang.',
        'download' => 'Sebentar lagi. Data Anda sedang diambil; tetap di halaman ini supaya tidak tertunda.',
    ],
];
