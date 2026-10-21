<?php

// The page "E-Sign Master" (e-sign.index, ESignController; the API's E-Sign endpoints for their messages): the list of
// the signatures of the people at ICT, the dialog to register or change one, the record of where one was put, the page
// with the pad (e-sign.pad; the pad itself speaks RadioManagement::checkout.pad), and what a picture of a signature is
// refused for (SignatureImage, also for the handover). The names of the people and the record of the forms are data.

return [
    'search' => 'Search name or e-mail…',
    'search_label' => 'Search name or e-mail',
    'search_button' => 'Search',
    'view' => 'View',
    'view_grid' => 'Grid',
    'view_list' => 'List',
    'add' => 'Register a signature',
    'banner' => 'A form shows the signature of <b>the account that opens that form</b>: nobody can put another person\'s signature on it. Every use is recorded (the history button). Pictures are stored encrypted.',

    'columns' => [
        'no' => 'No',
        'account' => 'Account',
        'name' => 'Printed name',
        'signature' => 'Signature',
        'status' => 'Status',
        'uses' => 'Used',
        'actions' => 'Actions',
    ],

    // The rows of the list and the cards of the grid (the page's script).
    'list' => [
        'signature_of' => 'Signature of :name',
        'account_gone' => 'Account deleted',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'times' => '{1} :count time|[0,*] :count times',
        'last' => 'last :at',
        'edit' => 'Edit',
        'uses' => 'Use history',
        'delete' => 'Delete',
        'change' => 'Replace',
        'empty' => 'No signature has been registered yet',
        'load_failed' => 'The data could not be loaded.',
        'no_picture' => 'No picture',
        'updated' => '{1} Updated :at · used :count time|[0,*] Updated :at · used :count times',
    ],

    'dialog' => [
        'add' => 'Register a signature',
        'edit' => 'Edit signature',
        'owner' => 'Owner account',
        'owner_hint' => 'This signature only appears on forms opened with this account.',
        'owner_fixed' => 'The owner of a signature cannot be changed. For another account, register a new signature.',
        'choose' => 'Choose an account…',
        'account_gone' => '(account deleted)',
        'name' => 'Name printed under the signature',
        'name_hint' => 'Leave empty to use the name of the account',
        'active' => 'Active (inactive: it does not appear on forms, and the box is signed on paper)',
        'current' => 'Current signature',
        'signature' => 'Signature',
        'signature_new' => 'New signature (leave empty to keep the current one)',
        'pad_open' => 'The signature has not been used yet. Press "Use signature" or "Cancel" in its box first.',
        'session' => 'Your session has ended. Reload the page to sign in again.',
    ],

    'delete' => [
        'title' => 'Delete this signature?',
        'text' => 'The signature no longer appears on forms. The record of where it was used is kept.',
        'confirm' => 'Delete',
    ],

    'uses' => [
        'title' => 'Use history',
        'at' => 'Time',
        'radio' => 'Radio form',
        'user' => 'Opened by',
        'total' => '{1} Used :count time.|[0,*] Used :count times.',
        'total_shown' => 'Used :count times, the last :shown shown.',
        'never' => 'Never used on a form yet.',
    ],

    // The page with the pad (e-sign.pad), around the pad of the form.
    'pad' => [
        'hint' => 'Two ways: <b>click the box</b> to sign on the screen (pen, finger or mouse), or <b>drag &amp; drop / upload a file</b> with the signature.',
        'drop' => 'or drag & drop a picture of the signature here',
        'or' => 'or',
        'upload' => 'Upload a signature file',
        'upload_hint' => 'PNG or JPG, dark ink on a white background',
        'not_image' => 'The file must be a PNG or JPG picture.',
        'too_big' => 'The file is too large (5 MB at most).',
        'unreadable' => 'That file cannot be read.',
        'not_picture' => 'That file is not a picture that can be read.',
        'no_ink' => 'No signature can be seen in that picture. The ink must be dark on a white or light background.',
    ],

    // What the server says (ESignController, the API).
    'messages' => [
        'not_ready' => 'The signature tables have not been created in the database yet. Run "php artisan migrate" first.',
        'choose_owner' => 'Choose the account the signature belongs to.',
        'owner_missing' => 'That account was not found.',
        'owner_has_one' => 'That account already has a signature. Replace it with the Edit button.',
        'no_signature' => 'There is no signature yet. Click the box to sign on the screen, or upload a picture of it.',
        'name_required' => 'The name printed under the signature is required.',
        'registered' => 'Signature of :name registered.',
        'saved' => 'Signature of :name saved.',
        'deleted' => 'Signature of :name deleted.',
        'deactivated' => 'Signature of :name switched off.',
        'inactive_already' => 'The signature of :name was already switched off.',
    ],

    // Why a picture of a signature is refused (SignatureImage).
    'picture' => [
        'not_png' => 'The signature must be a PNG picture from the signature box.',
        'bad_size' => 'The size of the signature picture is not right.',
        'unreadable' => 'The signature picture cannot be read.',
        'empty' => 'The signature is still empty.',
        'not_saved' => 'The signature picture could not be saved.',
    ],
];
