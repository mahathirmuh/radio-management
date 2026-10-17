<?php

use Modules\Api\Http\Middleware\CanReadApiDocs;

/*
| The documentation of the API, made from the code by dedoc/scramble: /docs/api (the page) and /docs/api.json (OpenAPI).
| Only the routes of /api/v1 are in it, and only an account that may hold a token can open it (CanReadApiDocs).
*/
return [
    'api_path' => 'api/v1',

    'api_domain' => null,

    'export_path' => 'api.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'openapi_version' => \Dedoc\Scramble\OpenApiVersion::V3_1,

    'info' => [
        'version' => '1.0',

        'description' => <<<'MD'
The register of radios of MTI Radio Management, for other applications.

**Token.** Made on the page *Settings → API Tokens* and sent as `Authorization: Bearer <token>`. A token acts as its
account: an API client (an application) or a person, with the role of that account (Role & Permission). It reads, or
reads and writes; it ends on its expiry date, when it is revoked, and when its account is set to inactive.

**Permissions.** Each endpoint belongs to the menu of its page and needs the permission of its method there: GET Read,
POST Create, PUT Update, DELETE Delete. Archiving and restoring a radio, and adding to its history, are an Update.

**Rules.** The same as on the pages: one Radio ID and one SN per radio, no Radio ID whose MDC code contains F, one save
at a time. Every change is in *Riwayat Radio* and in the *Audit Log*, with the token that made it.

**Answers.** JSON. Lists come a page at a time (`page`, `per_page` up to 100) with `meta` and `links`. Errors:
`401` no or a wrong token, `403` not allowed, `404` not found, `409` the radio was changed since you read it (send its
`version`) or the register is busy, `422` refused (`errors` by field), `429` too many requests (60 a minute per token),
`503` the API is not switched on yet. Messages of the register are in Indonesian, as on the pages.
MD,
    ],

    'ui' => [
        'title' => 'MTI Radio Management API',
    ],

    'dev_tools' => [
        'enabled' => false,
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'omit',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        'auth',
        CanReadApiDocs::class,
    ],

    'extensions' => [],

    'security_strategy' => null,
];
