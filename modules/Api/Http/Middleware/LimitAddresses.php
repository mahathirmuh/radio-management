<?php

namespace Modules\Api\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Modules\Api\Providers\ApiServiceProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requests a minute per address, before a token is looked up (a wrong token costs a query too). Not Laravel's
 * `throttle`: Laravel puts that one after the authentication whatever the order of the route, and then the lookup
 * would come first.
 */
class LimitAddresses
{
    public function __construct(private RateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'api-address:' . $request->ip();
        if ($this->limiter->tooManyAttempts($key, ApiServiceProvider::PER_ADDRESS)) {
            return response()->json(['message' => __('Api::api.too_many')], 429)
                ->header('Retry-After', (string) $this->limiter->availableIn($key));
        }
        $this->limiter->hit($key, 60);

        return $next($request);
    }
}
