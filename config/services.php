<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI')
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'azure' => [
        // Microsoft SSO. Accepts the AZURE_* names or the MS_GRAPH_* / MS_SSO_* names.
        'client_id' => env('AZURE_CLIENT_ID', env('MS_GRAPH_CLIENT_ID')),
        'client_secret' => env('AZURE_CLIENT_SECRET', env('MS_GRAPH_CLIENT_SECRET')),
        'redirect' => env('AZURE_REDIRECT_URI', env('MS_SSO_REDIRECT_URI')),
        'tenant' => env('AZURE_TENANT_ID', env('MS_GRAPH_TENANT_ID')),
        'proxy' => env('PROXY')  // optionally
    ],

    'snipeit' => [
        // Snipe-IT (the menu "Snipe-IT"), read only: the address of its API ("http://host/api/v1") and an API token. The
        // pages only ever ask (GET); best a token of a Snipe-IT account that may only view.
        'url' => env('SNIPEIT_URL'),
        'token' => env('SNIPEIT_TOKEN'),
        // Minutes an answer of Snipe-IT is kept before it is asked again (the button "Refresh" asks at once); 0 keeps none.
        'cache_minutes' => (int) env('SNIPEIT_CACHE_MINUTES', 5),
    ],

];
