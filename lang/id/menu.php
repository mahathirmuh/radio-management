<?php

// The names of the sidebar (App\Models\UserManagement\Menu::label()): an entry by its route, a group (no route) by its
// title in the table, a section by its value. An entry added later without a name here shows its title as it is.

return [
    'routes' => [
        'dashboard.index' => 'Dashboard',
        'radio-management.radio.index' => 'Register Radio',
        'radio-management.attention.index' => 'Perlu Perhatian',
        'radio-management.service.index' => 'Servis & Perbaikan',
        'radio-management.opname.index' => 'Stock Opname',
        'radio-management.history.index' => 'Riwayat Radio',
        'radio-management.radio-type.index' => 'Tipe Radio',
        'radio-management.company.index' => 'Perusahaan',
        'radio-management.department.index' => 'Departemen',
        'radio-management.channel-template.index' => 'Template Channel',
        'radio-management.e-sign.index' => 'E-Sign Master',
        'radio-management.import.index' => 'Import Excel',
        'documents.cof-scan.index' => 'COF Scan',
        'documents.ict-masterlist.index' => 'ICT Masterlist',
        'documents.edit-approvals.index' => 'Persetujuan Edit',
        'user-management.user-registered.index' => 'User Terdaftar',
        'user-management.role-permission.index' => 'Role & Hak Akses',
        'audit-log.index' => 'Audit Log',
        'api-tokens.index' => 'API Tokens',
        // Hidden from every role (HideApprovalMenus): the module of the template keeps its English names.
        'approval-management.approval-inbox.index' => 'Approval Inbox',
        'approval-management.approval-flows.index' => 'Approval Flows',
        'approval-management.approval-delegate.index' => 'Delegate Approval',
        'approval-management.approval-takeover.index' => 'Takeover Approval',
    ],

    'groups' => [
        'Master Data Radio' => 'Master Data Radio',
        'User Management' => 'User Management',
        'Approval Management' => 'Approval Management',
    ],

    'sections' => [
        'Radio' => 'Radio',
        'Dokumen' => 'Dokumen',
        'User' => 'User',
        'Settings' => 'Pengaturan',
    ],
];
