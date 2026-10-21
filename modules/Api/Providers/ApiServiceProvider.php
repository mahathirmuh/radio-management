<?php

namespace Modules\Api\Providers;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Events\TokenAuthenticated;
use Laravel\Sanctum\Sanctum;
use Modules\Api\Models\ApiToken;

/**
 * The API of the application (/api/v1, documented at /docs/api) and the page that hands out its tokens ("API Tokens").
 */
class ApiServiceProvider extends ServiceProvider
{
    /** Requests a minute: per token once it is known, and per address before (a wrong token costs a lookup too). */
    public const PER_TOKEN = 60;

    public const PER_ADDRESS = 120;

    /** For what is slow or heavy (import and export of Excel, writing to SharePoint). */
    public const HEAVY_PER_TOKEN = 10;

    /** For what is read from SharePoint (a folder, a workbook, a file): paging through a large sheet stays possible. */
    public const SHAREPOINT_PER_TOKEN = 30;

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(ApiToken::class);

        $this->loadRoutesFrom(base_path('modules/Api/Routes/api/v1.php'));
        $this->loadRoutesFrom(base_path('modules/Api/Routes/health.php'));
        $this->loadRoutesFrom(base_path('modules/Api/Routes/web/api-tokens.php'));
        $this->loadViewsFrom(base_path('modules/Api/Resources/views'), 'Api');
        $this->loadTranslationsFrom(base_path('modules/Api/Resources/lang'), 'Api');

        $token = fn (Request $request) => $request->user()?->currentAccessToken()?->getKey() ?? $request->ip();
        RateLimiter::for('api-v1', fn (Request $request) => Limit::perMinute(self::PER_TOKEN)->by('api-token:' . $token($request)));
        RateLimiter::for('api-v1-heavy', fn (Request $request) => Limit::perMinute(self::HEAVY_PER_TOKEN)->by('api-heavy:' . $token($request)));
        RateLimiter::for('api-v1-sharepoint', fn (Request $request) => Limit::perMinute(self::SHAREPOINT_PER_TOKEN)->by('api-sharepoint:' . $token($request)));
        // (Per address, before the token: Http\Middleware\LimitAddresses.)

        // Where a token was last used from, next to when: Sanctum saves the token right after this event.
        Event::listen(TokenAuthenticated::class, function (TokenAuthenticated $event) {
            $event->token->forceFill(['last_used_ip' => request()->ip()]);
        });

        // The documentation: for an account that may hold a token (the menu "API Tokens"; Super Admin always).
        Gate::define('viewApiDocs', function (?User $user = null) {
            if ($user === null || ! $user->isActive() || $user->isApiClient()) {
                return false;
            }
            if ($user->roles->contains('id', 1)) {
                return true;
            }
            $menu = CheckMenuPermission::menuForKey('api-tokens');

            return $menu !== null && $user->guardRole($menu->id);
        });

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer')->as('token')
                ->setDescription('A token from Settings → API Tokens, sent as `Authorization: Bearer <token>`.'));
        });
    }
}
