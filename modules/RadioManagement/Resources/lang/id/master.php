<?php

// The pages of "Master Data Radio" (master.index, MasterData, MasterDataController): the four small tables (radio types,
// companies, departments, channel templates), the names of their fields, and what the page and its answers say. What
// the tables hold is data and is never translated; the audit trail keeps the Indonesian names of the tables and fields.

return [
    'tables' => [
        'radio-type' => [
            'title' => 'Tipe Radio',
            'add' => 'Tambah Tipe Radio',
            'edit' => 'Edit Tipe Radio',
            'search' => 'Cari tipe radio…',
            'delete' => 'Hapus tipe radio ini?',
            'created' => 'Tipe Radio berhasil ditambahkan.',
            'saved' => 'Tipe Radio berhasil disimpan.',
            'deleted' => 'Tipe Radio dihapus.',
            'fields' => [
                'name' => 'Nama Tipe',
                'category' => 'Kategori',
                'power' => 'Daya',
                'is_active' => 'Aktif',
            ],
        ],
        'company' => [
            'title' => 'Perusahaan',
            'add' => 'Tambah Perusahaan',
            'edit' => 'Edit Perusahaan',
            'search' => 'Cari perusahaan…',
            'delete' => 'Hapus perusahaan ini?',
            'created' => 'Perusahaan berhasil ditambahkan.',
            'saved' => 'Perusahaan berhasil disimpan.',
            'deleted' => 'Perusahaan dihapus.',
            'fields' => [
                'code' => 'Kode',
                'name' => 'Nama Perusahaan',
                'is_active' => 'Aktif',
            ],
        ],
        'department' => [
            'title' => 'Department',
            'add' => 'Tambah Department',
            'edit' => 'Edit Department',
            'search' => 'Cari department…',
            'delete' => 'Hapus department ini?',
            'created' => 'Department berhasil ditambahkan.',
            'saved' => 'Department berhasil disimpan.',
            'deleted' => 'Department dihapus.',
            'fields' => [
                'name' => 'Nama Department',
                'is_active' => 'Aktif',
            ],
        ],
        'channel-template' => [
            'title' => 'Template Channel',
            'add' => 'Tambah Template Channel',
            'edit' => 'Edit Template Channel',
            'search' => 'Cari template channel…',
            'delete' => 'Hapus template channel ini?',
            'created' => 'Template Channel berhasil ditambahkan.',
            'saved' => 'Template Channel berhasil disimpan.',
            'deleted' => 'Template Channel dihapus.',
            'fields' => [
                'name' => 'Nama Template',
                'channels' => 'Isi Channel',
                'effective_date' => 'Berlaku Sejak',
                'is_active' => 'Aktif',
            ],
            'help' => [
                'effective_date' => 'Radio dengan template ini yang diprogram sebelum tanggal ini ditandai "Perlu program ulang".',
            ],
        ],
    ],

    // What the page and its script say, whichever table it shows.
    'page' => [
        'tabs' => 'Master data',
        'search' => 'Cari',
        'no' => 'No',
        'usage' => 'Dipakai',
        'actions' => 'Aksi',
        'new_sub' => 'Isi lalu simpan; data baru langsung bisa dipilih di register.',
        'edit_sub' => 'Ubah lalu simpan; radio yang memakainya ikut berubah.',
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'edit_button' => 'Edit',
        'delete_button' => 'Hapus',
        'empty' => 'Belum ada data',
        'load_failed' => 'Gagal memuat data.',
        'delete_text' => 'Data yang masih dipakai radio tidak bisa dihapus.',
    ],

    // What the server says (MasterData, MasterDataController; the API too).
    'messages' => [
        'required' => ':attribute wajib diisi.',
        'unique' => ':attribute sudah ada.',
        'in_use' => 'Masih dipakai :count radio. Nonaktifkan saja (tidak bisa dipilih lagi untuk radio lain), atau pindahkan radio ke data lain dulu.',
        'busy_text' => 'Data radio sedang disimpan, atau impor Excel sedang berjalan. Coba lagi beberapa saat lagi.',
    ],
];
