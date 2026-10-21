<?php

// The page of the roles (user-management.role-permission): the list, the dialog of a role (its name, its home page, the
// menus it opens and what it may do in each) and what the server answers (RolePermissionsController). The menus are
// named as in the sidebar (Menu::label(), lang/<code>/menu.php); what the audit log keeps of a role stays as it is.

return [
    'tab_title' => 'Role & Permission',
    'title' => 'Roles and permissions',
    'search' => 'Search…',
    'search_label' => 'Search roles',
    'reset' => 'Reset',
    'add' => 'Add Role',

    'columns' => [
        'no' => 'No',
        'name' => 'Name',
        'users' => 'Total user',
        'action' => 'Action',
    ],

    // What the script of the list says.
    'list' => [
        'view' => 'View',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'noun' => 'roles',
        'load_failed' => 'Error loading data. Please try again.',
        'delete_title' => 'Delete this role?',
        'delete_text' => 'It is taken off the list of roles. Users who hold it lose what it gives them.',
        'cancel' => 'Cancel',
        'error' => 'Oops...',
    ],

    // The dialog of a role.
    'form' => [
        'close' => 'Close',
        'cancel' => 'Cancel',
        'role' => 'Role',
        'role_placeholder' => 'Role..',
        'home_page' => 'Home Page',
        'home_page_placeholder' => 'Select Home Page',
        'menus' => 'Menu & Permission',
        'menu' => 'Menu',
        'access' => 'Access',
        'all' => 'Allows a full access to the system',
        'all_label' => 'Every menu',
        'access_label' => ':menu: access',
        'permission_label' => ':menu: :permission',
        'update' => 'Update',
        'submit' => 'Submit',
        'view_title' => 'View role',
        'edit_title' => 'Edit role',
        'add_title' => 'Add role',
        'confirm_title' => 'Is the data correct?',
        'confirm_add' => 'This action will add the role!',
        'confirm_update' => 'This action will update the role!',
        'save' => 'Save',
        'error' => 'Oops...',
    ],

    // The kinds of permission, by the name they are kept under (permissions.name).
    'permissions' => [
        'Create' => 'Create',
        'Read' => 'Read',
        'Update' => 'Update',
        'Delete' => 'Delete',
    ],

    // What the server answers.
    'messages' => [
        'invalid' => 'Invalid Input!',
        'saved' => 'Data Updated Succesfully',
        'deleted' => 'Data Deleted Successfully',
        'in_use' => 'Role still assigned to user',
    ],

    // The names of the fields in the messages of the validation (the English ones are Laravel's own).
    'attributes' => [
        'role_name' => 'role name',
        'role_type' => 'role type',
        'home_page' => 'home page',
    ],
];
