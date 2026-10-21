<?php

// The names of the sidebar (App\Models\UserManagement\Menu::label()): an entry by its route, a group (no route) by its
// title in the table, a section by its value. An entry added later without a name here shows its title as it is.

return [
    'routes' => [
        'dashboard.index' => 'Dashboard',
        'radio-management.radio.index' => 'Register Radio',
        'radio-management.attention.index' => 'Needs Attention',
        'radio-management.service.index' => 'Service & Repair',
        'radio-management.opname.index' => 'Stock Opname',
        'radio-management.history.index' => 'Radio History',
        'radio-management.radio-type.index' => 'Radio Types',
        'radio-management.company.index' => 'Companies',
        'radio-management.department.index' => 'Departments',
        'radio-management.channel-template.index' => 'Channel Templates',
        'radio-management.e-sign.index' => 'E-Sign Master',
        'radio-management.import.index' => 'Import Excel',
        'documents.cof-scan.index' => 'COF Scan',
        'documents.ict-masterlist.index' => 'ICT Masterlist',
        'documents.edit-approvals.index' => 'Edit Approvals',
        'user-management.user-registered.index' => 'Registered Users',
        'user-management.role-permission.index' => 'Role & Permission',
        'audit-log.index' => 'Audit Log',
        'api-tokens.index' => 'API Tokens',
        // Hidden from every role (HideApprovalMenus): the module of the template keeps its English names.
        'approval-management.approval-inbox.index' => 'Approval Inbox',
        'approval-management.approval-flows.index' => 'Approval Flows',
        'approval-management.approval-delegate.index' => 'Delegate Approval',
        'approval-management.approval-takeover.index' => 'Takeover Approval',
    ],

    'groups' => [
        'Master Data Radio' => 'Radio Master Data',
        'User Management' => 'User Management',
        'Approval Management' => 'Approval Management',
    ],

    'sections' => [
        'Radio' => 'Radio',
        'Dokumen' => 'Documents',
        'User' => 'User',
        'Settings' => 'Settings',
    ],
];
