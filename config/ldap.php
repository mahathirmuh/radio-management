<?php

/*
|--------------------------------------------------------------------------
| Active Directory: signing in with the directory password
|--------------------------------------------------------------------------
|
| An account that has a directory username (User Management, sign-in method "LDAP") signs in on the login form
| with the password of its Active Directory account. The application looks the person up with the service
| account below and then binds to the directory as that person: binding is the check, and no password is kept
| here. See App\Services\Directory\LdapAuthenticator.
|
| Without LDAP_URL and LDAP_BIND_DN the directory is "not configured": User Management does not offer LDAP, and
| the form of a user is the one it was before.
|
| The PHP ldap extension has to be loaded for it. Where it is not, such a sign-in is answered with "the directory
| cannot be reached" (never with "wrong password"), and signing in with Microsoft keeps working.
|
*/

return [

    'url' => env('LDAP_URL'),

    'base_dn' => env('LDAP_BASE_DN'),

    // The account that looks a person up before their own password is tried. It needs to read, nothing more.
    'bind_dn' => env('LDAP_BIND_DN'),
    'bind_password' => env('LDAP_BIND_PASSWORD'),

    // (An empty value in .env counts as not given.)
    'user_search_base' => env('LDAP_USER_SEARCH_BASE') ?: env('LDAP_BASE_DN'),

    // {username} is replaced with the directory username of the account, escaped for a filter.
    'user_search_filter' => env('LDAP_USER_SEARCH_FILTER') ?: '(sAMAccountName={username})',

    // Seconds. The login page waits while the directory is asked: one that does not answer should say so quickly.
    'timeout' => (int) env('LDAP_TIMEOUT', 5),

    // Whether the certificate of the domain controller is checked. A bind sends the password of the person, so
    // without the check whoever answers the connection receives it. Off only where the certificate comes from an
    // internal CA this server does not trust yet: install that CA, then turn this on again.
    'tls_verify' => filter_var(env('LDAP_TLS_VERIFY', true), FILTER_VALIDATE_BOOL),

    // Which attributes of the directory carry what is read from it.
    'attributes' => [
        'username' => env('LDAP_ATTR_USERNAME', 'samaccountname'),
        'mail' => env('LDAP_ATTR_MAIL', 'mail'),
        'name' => env('LDAP_ATTR_NAME', 'displayname'),
    ],

];
