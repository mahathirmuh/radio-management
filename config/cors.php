<?php

/*
| Cross-Origin Resource Sharing: whether a web page of ANOTHER site may call the API from its visitors' browsers.
| Closed unless CORS_ALLOWED_ORIGINS names those sites (comma separated, e.g. https://dashboard.example.com).
| An application that calls the API from its server needs none of this.
*/
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // The API takes a token, never a cookie.
    'supports_credentials' => false,

];
