<?php

// The page "Servis & Perbaikan" (service.index): the board and the list of the open tickets, the drawer of a ticket, its
// dialogs (a new ticket, the next step, a note), and what the server answers (ServiceTicketController, ServiceTickets).
// A ticket keeps its step by a code (reported, sent, ...). What the app writes down of it (the activity of the ticket, the
// radio's history, the audit trail) and the Excel export stay in Indonesian: 'events' only says how the page shows the
// activity in its language.

return [
    'title' => 'Servis & Perbaikan',
    'not_ready' => 'Tabel tiket servis belum dibuat di database. Jalankan "php artisan migrate" dulu.',

    // By the code kept (ServiceTicket::STATUSES).
    'statuses' => [
        'reported' => 'Dilaporkan',
        'sent' => 'Dikirim ke servis',
        'in_repair' => 'Dalam perbaikan',
        'ready' => 'Siap diambil',
        'returned' => 'Kembali',
    ],
    // The same steps in the stepper of the drawer: short words.
    'steps' => [
        'reported' => 'Dilaporkan',
        'sent' => 'Dikirim',
        'in_repair' => 'Diperbaiki',
        'ready' => 'Siap',
        'returned' => 'Kembali',
    ],
    // The next step: the drawer's button, the title of the dialog it opens, and its button.
    'next' => [
        'sent' => ['button' => 'Kirim ke servis', 'title' => 'Kirim ke servis', 'save' => 'Kirim'],
        'in_repair' => ['button' => 'Tandai dalam perbaikan', 'title' => 'Mulai diperbaiki', 'save' => 'Simpan'],
        'ready' => ['button' => 'Tandai siap diambil', 'title' => 'Siap diambil', 'save' => 'Simpan'],
        'returned' => ['button' => 'Tandai sudah kembali', 'title' => 'Radio kembali', 'save' => 'Simpan'],
    ],

    'toolbar' => [
        'view' => 'Tampilan',
        'board' => 'Board',
        'list' => 'List',
        'search' => 'Radio ID, SN atau masalah',
        'search_label' => 'Cari Radio ID, SN atau masalah',
        'center' => 'Pusat servis',
        'clear' => 'Hapus filter',
        'export' => 'Export',
        'new' => 'Tiket baru',
    ],

    'columns' => [
        'ticket' => 'Tiket',
        'radio' => 'Radio',
        'issue' => 'Masalah',
        'reported_by' => 'Dilaporkan oleh',
        'center' => 'Pusat servis',
        'status' => 'Status',
        'days' => 'Lama',
    ],

    'drawer' => [
        'title' => 'Tiket servis',
        'add_note' => 'Tambah catatan',
    ],

    'new' => [
        'title' => 'Tiket servis baru',
        'sub' => 'Catat radio yang bermasalah; pengiriman ke pusat servis dicatat di langkah berikutnya.',
        'radio' => 'Radio',
        'radio_hint' => 'Radio ID, SN atau nama user',
        'issue' => 'Masalah',
        'issue_hint' => 'Contoh: layar retak setelah jatuh',
        'reporter' => 'Dilaporkan oleh',
        'reporter_hint' => 'Nama atau unit · department',
        'reporter_help' => 'Terisi dari pemegang radio; boleh diubah.',
        'save' => 'Buat tiket',
    ],

    'move' => [
        'title' => 'Kirim ke servis',
        'center' => 'Pusat servis',
        'center_hint' => 'Nama pusat servis',
        'sent_at' => 'Tanggal kirim',
        'ba' => 'No. BA',
        'returned_at' => 'Tanggal kembali',
        'expected' => 'Perkiraan kembali',
        'quote' => 'Penawaran (Rp)',
        'placement' => 'Lokasi radio sesudahnya',
        'condition' => 'Kondisi saat kembali',
        'cost' => 'Biaya (Rp)',
        'sent_note' => 'Lokasi radio di register menjadi <b>Service</b>, dan riwayatnya mencatat pengiriman ini.',
        'returned_note' => 'Lokasi dan kondisi radio di register diubah, dan riwayatnya mencatat kepulangan ini.',
    ],

    'note' => [
        'title' => 'Tambah catatan',
        'note' => 'Catatan',
        'note_hint' => 'Contoh: pusat servis menunggu suku cadang',
        'save' => 'Simpan catatan',
    ],

    // What the script of the page writes (serviceBoard.words).
    'board' => [
        // The four numbers above the board.
        'stats' => [
            'open' => 'Tiket terbuka',
            'turnaround' => 'Rata-rata lama servis',
            'overdue' => 'Terlambat',
            'cost' => 'Biaya kuartal ini',
        ],
        'open_hint' => ':reported dilaporkan, :away di pusat servis',
        'turnaround_hint' => '90 hari terakhir',
        'overdue_hint' => 'Lebih dari :days hari di servis',
        'closed_hint' => ':count tiket selesai',
        // A short amount ("Rp 14,2 jt"): the number with one decimal, and its unit.
        'money' => 'Rp :amount :unit',
        'million' => 'jt',
        'billion' => 'M',
        'decimal' => ',',
        'days' => ':count hari',

        'all_centers' => 'Semua pusat servis',
        'hide' => 'Sembunyikan',
        'more' => ':count lagi',
        'no_match' => 'Tidak ada yang cocok',
        'no_tickets' => 'Tidak ada tiket',
        'not_sent' => 'Belum dikirim',
        'list_no_match' => 'Tidak ada tiket yang cocok.',
        'list_none' => 'Tidak ada tiket terbuka.',
        'foot_no_match' => 'Tidak ada tiket yang cocok',
        'foot_none' => 'Tidak ada tiket terbuka',
        'foot_matches' => ':count tiket cocok',
        'foot_all' => 'Semua :count tiket terbuka',

        'not_found' => 'Tiket itu tidak ditemukan.',
        'not_in_register' => 'Tidak ada di register',
        'deleted' => 'Sudah dihapus dari register',
        'radio_state' => 'Lokasi: :placement · Kondisi: :condition',
        'archived' => '(diarsipkan)',
        'issue' => 'Masalah',
        'reported' => 'Dilaporkan',
        'center' => 'Pusat servis',
        'sent' => 'Dikirim',
        'expected' => 'Perkiraan kembali',
        'quote' => 'Penawaran',
        'cost' => 'Biaya',
        'returned' => 'Kembali',
        'radio_status' => 'Status radio',
        'open_radio' => 'Buka radio',
        'note' => 'Catatan',
        'activity' => 'Aktivitas',
        'no_activity' => 'Belum ada aktivitas.',

        'no_id' => 'Radio tanpa ID',
        'no_sn' => 'tanpa SN',
        'still_open' => 'Tiket :number masih terbuka',
        'no_radio' => 'Tidak ada radio aktif yang cocok.',
        'change' => 'Ganti',
        'center_needed' => 'Isi pusat servis tujuan.',
        'today' => 'Hari ini',
        'unknown' => 'Belum tahu',
    ],

    // How the page shows a step in the activity of a ticket; kept as the first form of each (ServiceTickets::move).
    'events' => [
        'reported' => 'Dilaporkan: :issue',
        'sent' => 'Dikirim ke :center',
        'sent_ba' => 'Dikirim ke :center · BA :ba',
        'in_repair' => 'Mulai diperbaiki',
        'in_repair_quote' => 'Mulai diperbaiki · penawaran :quote',
        'ready' => 'Siap diambil',
        'ready_cost' => 'Siap diambil · biaya :cost',
        'returned' => 'Kembali: :placement · :condition',
    ],

    // The names of the fields in the messages of the server.
    'fields' => [
        'radio_id' => 'Radio',
        'issue' => 'Masalah',
        'reported_by' => 'Pelapor',
        'status' => 'Status',
        'service_center' => 'Pusat servis',
        'sent_at' => 'Tanggal kirim',
        'document_no' => 'No. BA',
        'expected_at' => 'Perkiraan kembali',
        'quote' => 'Penawaran',
        'cost' => 'Biaya',
        'returned_at' => 'Tanggal kembali',
        'placement' => 'Lokasi radio',
        'condition' => 'Kondisi radio',
        'note' => 'Catatan',
    ],
    // What a field says when it is not right (Laravel's custom messages: by rule, a size rule by type).
    'validation' => [
        'required' => ':attribute wajib diisi.',
        'in' => 'Pilihan :attribute tidak dikenal.',
        'integer' => ':attribute harus angka bulat.',
        'date_format' => ':attribute harus tanggal yang benar.',
        'min' => ['numeric' => ':attribute tidak boleh kurang dari :min.'],
        'max' => ['numeric' => ':attribute terlalu besar.', 'string' => ':attribute paling panjang :max huruf.'],
    ],
    // ... and what one field says for one rule.
    'rules' => [
        'radio_id.required' => 'Pilih radio yang akan diservis.',
        'issue.required' => 'Tulis masalah radionya.',
        'note.required' => 'Tulis catatannya.',
        'sent_at.before_or_equal' => 'Tanggal kirim tidak boleh lewat dari hari ini.',
        'sent_at.after_or_equal' => 'Tanggal kirim tidak boleh sebelum tiket dibuat.',
        'returned_at.before_or_equal' => 'Tanggal kembali tidak boleh lewat dari hari ini.',
        'returned_at.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal kirim.',
        'expected_at.after_or_equal' => 'Perkiraan kembali tidak boleh sebelum tanggal kirim.',
    ],

    'messages' => [
        'created' => 'Tiket :number dibuat.',
        'moved' => 'Tiket :number: :status.',
        'note_added' => 'Catatan ditambahkan ke :number.',
        'invalid' => 'Input tidak valid',
        'busy' => 'Register sedang sibuk',
        'busy_text' => 'Data radio lain sedang disimpan, atau impor Excel sedang berjalan. Coba lagi beberapa saat lagi.',
    ],
    // Why a write is refused (ServiceTickets).
    'refusals' => [
        'not_in_register' => 'Radio itu tidak ada di register.',
        'archived' => 'Radio yang diarsipkan tidak bisa diservis. Pulihkan dulu.',
        'open' => 'Radio ini masih punya tiket servis yang terbuka: :number.',
        'finished' => 'Tiket ini sudah selesai: radionya sudah kembali.',
        'moved' => 'Tiket ini sudah dipindah orang lain. Muat ulang halaman.',
    ],
];
