<?php

// The page of the roles (user-management.role-permission): the list, the dialog of a role (its name, its home page, the
// menus it opens and what it may do in each) and what the server answers (RolePermissionsController). The menus are
// named as in the sidebar (Menu::label(), lang/<code>/menu.php); what the audit log keeps of a role stays as it is.

return [
    'tab_title' => 'Role & Hak Akses',
    'title' => 'Role dan hak akses',
    'search' => 'Cari…',
    'search_label' => 'Cari role',
    'reset' => 'Reset',
    'add' => 'Tambah role',

    'columns' => [
        'no' => 'No',
        'name' => 'Nama',
        'users' => 'Jumlah user',
        'action' => 'Aksi',
    ],

    // What the script of the list says.
    'list' => [
        'view' => 'Lihat',
        'edit' => 'Edit',
        'delete' => 'Hapus',
        'noun' => 'role',
        'load_failed' => 'Data tidak bisa dimuat. Coba lagi.',
        'delete_title' => 'Hapus role ini?',
        'delete_text' => 'Role ini dikeluarkan dari daftar role. User yang memegangnya kehilangan akses yang diberikan role ini.',
        'cancel' => 'Batal',
        'error' => 'Gagal',
    ],

    // The dialog of a role.
    'form' => [
        'close' => 'Tutup',
        'cancel' => 'Batal',
        'role' => 'Role',
        'role_placeholder' => 'Role..',
        'home_page' => 'Halaman awal',
        'home_page_placeholder' => 'Pilih halaman awal',
        'menus' => 'Menu & hak akses',
        'menu' => 'Menu',
        'access' => 'Akses',
        'all' => 'Memberi akses penuh ke sistem',
        'all_label' => 'Semua menu',
        'access_label' => ':menu: akses',
        'permission_label' => ':menu: :permission',
        'update' => 'Simpan perubahan',
        'submit' => 'Simpan',
        'view_title' => 'Lihat role',
        'edit_title' => 'Edit role',
        'add_title' => 'Tambah role',
        'confirm_title' => 'Datanya sudah benar?',
        'confirm_add' => 'Tindakan ini akan menambahkan role!',
        'confirm_update' => 'Tindakan ini akan memperbarui role!',
        'save' => 'Simpan',
        'error' => 'Gagal',
    ],

    // The kinds of permission, by the name they are kept under (permissions.name).
    'permissions' => [
        'Create' => 'Tambah',
        'Read' => 'Lihat',
        'Update' => 'Ubah',
        'Delete' => 'Hapus',
    ],

    // What the server answers.
    'messages' => [
        'invalid' => 'Isian salah!',
        'saved' => 'Data role tersimpan',
        'deleted' => 'Role dihapus',
        'in_use' => 'Role masih dipakai user',
    ],

    // The names of the fields in the messages of the validation (the English ones are Laravel's own).
    'attributes' => [
        'role_name' => 'nama role',
        'role_type' => 'jenis role',
        'home_page' => 'halaman awal',
    ],
];
