<?php

// What the REST API (/api/v1) says of its own accord, in the language the client asks for (Accept-Language; English
// when it asks for none): the token, the account and the role refused, the API not switched on, too many requests.

return [
    'unauthenticated' => 'Unauthenticated.',
    'inactive' => 'This account is inactive.',
    'read_only' => 'This token may only read.',
    'closed' => 'This part of the API is closed: its menu is not in this application.',
    'no_access' => 'The role of this account has no access to ":menu".',
    'not_allowed' => 'The role of this account may not :verb on ":menu" (:permission).',
    'verbs' => [
        'Read' => 'read',
        'Create' => 'add',
        'Update' => 'change',
        'Delete' => 'delete',
    ],
    'not_ready' => 'The API is not switched on yet: its migration has not been run on this database.',
    'audit_not_ready' => 'The audit log is not switched on yet: its migration has not been run on this database.',
    'too_many' => 'Too Many Attempts.',
    'not_found' => 'Not found.',

    // GET /api/v1/health: why a check is not ok (its "reason" => its "message").
    'health' => [
        'not_configured' => 'Not set up here.',
        'unreachable' => 'Cannot be reached.',
        'unavailable' => 'Cannot be asked: not reached, or its credentials were refused.',
        'refused' => 'Refused the credentials.',
        'failed' => 'Answered with an error.',
        'not_found' => 'Not found.',
        'not_writable' => 'Cannot be written.',
        'no_table' => 'The table of the migrations is missing.',
        'not_working' => 'The login page is not served as it should be.',
        'tables' => 'The tables of the application cannot be read.',
        'pending' => '{1} :count migration has not been run on this database yet.|[2,*] :count migrations have not been run on this database yet.',
        'database' => 'Not checked: the database does not answer.',
    ],
];
