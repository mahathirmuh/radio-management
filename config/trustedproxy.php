<?php

/*
|--------------------------------------------------------------------------
| Reverse proxies that are believed
|--------------------------------------------------------------------------
|
| When the application runs behind a reverse proxy (Nginx Proxy Manager in front of the Docker container, which ends
| HTTPS), the request reaches Laravel from the proxy, over plain HTTP. The proxy says what the visitor asked for in the
| X-Forwarded-* headers. Laravel believes them only from the addresses named here (TRUSTED_PROXIES in .env, comma
| separated, addresses or ranges such as 172.16.0.0/12). Without that, the pages of an https site link their assets
| with http:// and the browser blocks them.
|
| Empty (the default) believes nobody: right when the application is opened directly. Not "*": anybody who can reach
| the port would then write their own client address and scheme into the request.
|
| Read by Illuminate\Http\Middleware\TrustProxies when App\Http\Middleware\TrustProxies names no proxies itself.
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
