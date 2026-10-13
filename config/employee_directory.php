<?php

/*
|--------------------------------------------------------------------------
| The employee directory (HR database), read only
|--------------------------------------------------------------------------
|
| "Find employee" in the form of a user: a colleague is looked up by name or work address, and choosing one fills in
| the name and the e-mail. The address has to be the one Microsoft signs the person in with; one character out and
| the account is made, looks right, and can never be signed in to. Choosing it takes that step away.
|
| Optional. Without a host for the connection (HR_DB_HOST) the search is not shown and the form is typed by hand.
| See Modules\UserManagement\Services\EmployeeDirectory.
|
*/

return [

    'enabled' => filter_var(env('EMPLOYEE_DIRECTORY_ENABLED', true), FILTER_VALIDATE_BOOL),

    // The database connection it is read through (config/database.php).
    'connection' => env('EMPLOYEE_DIRECTORY_CONNECTION', 'hr'),

    // The table, or better a view, it is read from, and its three columns. A view with just these three is better
    // than the table of the employees itself: that one also holds dates of birth, ID card numbers and more, and this
    // application needs to be able to read none of it. What they are really called is said in .env.
    'table' => env('EMPLOYEE_DIRECTORY_TABLE', 'employee_directory'),

    'columns' => [
        'id' => env('EMPLOYEE_DIRECTORY_ID_COLUMN', 'employee_id'),
        'name' => env('EMPLOYEE_DIRECTORY_NAME_COLUMN', 'name'),
        // The work address, not the personal one.
        'email' => env('EMPLOYEE_DIRECTORY_EMAIL_COLUMN', 'email'),
    ],

    // Only an address in one of these domains is offered: an address elsewhere (gmail.com, "no need", ...) cannot
    // sign in with Microsoft here. Comma separated; empty offers every address.
    'sign_in_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('EMPLOYEE_DIRECTORY_DOMAINS', 'merdekabattery.com')),
    ))),

    // Fewer letters than this are not searched for, and no more matches than the limit are shown: more than that
    // means the search was too vague, and the answer is to type more.
    'minimum_query_length' => 2,

    'limit' => 8,

];
