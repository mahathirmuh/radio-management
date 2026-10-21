<?php

// What the REST API (/api/v1) says of its own accord, in the language the client asks for (Accept-Language; English
// when it asks for none): the token, the account and the role refused, the API not switched on, too many requests.

return [
    'unauthenticated' => 'Tidak terautentikasi.',
    'inactive' => 'Akun ini nonaktif.',
    'read_only' => 'Token ini hanya boleh membaca.',
    'closed' => 'Bagian API ini tertutup: menunya tidak ada di aplikasi ini.',
    'no_access' => 'Role akun ini tidak punya akses ke ":menu".',
    'not_allowed' => 'Role akun ini tidak boleh :verb di ":menu" (:permission).',
    'verbs' => [
        'Read' => 'membaca',
        'Create' => 'menambah',
        'Update' => 'mengubah',
        'Delete' => 'menghapus',
    ],
    'not_ready' => 'API belum aktif: migrasinya belum dijalankan di database ini.',
    'audit_not_ready' => 'Audit Log belum aktif: migrasinya belum dijalankan di database ini.',
    'too_many' => 'Terlalu banyak permintaan.',
    'not_found' => 'Tidak ditemukan.',
];
