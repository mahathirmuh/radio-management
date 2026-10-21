<?php

namespace App\Exceptions;

use App\Helpers\LogApps;
use App\Models\Log\LogError;
use App\Services\Language;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sentry\Laravel\Integration;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //Internal Error Monitoring *uncomment buat mengaktifkan internal error monitoring
            try {
                $this->logError(
                    $e,
                    request()->fullUrl(),
                    request()->method(),
                    request()->header('user-agent'),
                    request()->except(LogApps::SENSITIVE_INPUT)
                );
            } catch (Throwable $loggingFailure) {
                // error_logs is not writable (e.g. the database is down): Laravel still
                // writes $e to storage/logs/laravel.log, so nothing is lost.
            }

            // Sentry Error Monitoring *uncomment buat mengaktifkan sentry monitoring
            Integration::captureUnhandledException($e);
        });

        // The API says "not found" in its own words: Laravel's would name the class of the model ("No query results for
        // model [Modules\RadioManagement\Models\Radio] 12"). In the language of the client: an address that is no route
        // never reached the API's own SetLocale.
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/v1', 'api/v1/*')) {
                return response()->json(['message' => __('Api::api.not_found', [], Language::forApi($request))], 404);
            }
        });

        // Too many requests (Laravel's throttle of the API), said as the API says it.
        $this->renderable(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/v1', 'api/v1/*')) {
                return response()->json(['message' => __('Api::api.too_many', [], Language::forApi($request))], 429, $e->getHeaders());
            }
        });

        // A form sent from a page left open after its session had ended (the login page too): to the login page, which
        // says so, rather than Laravel's "Page Expired". A page's script gets the 419 itself (sessionTimer).
        $this->renderable(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419 && $e->getPrevious() instanceof TokenMismatchException && ! $request->expectsJson()) {
                Alert::info(__('login.alerts.session_expired'), __('login.alerts.session_ended') . ' ' . __('login.alerts.sign_in_again'))->persistent(true);

                return redirect()->route('login');
            }
        });
    }

    /** No token, or one Sanctum does not know: the API says so in the language of the client. */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->is('api/v1', 'api/v1/*')) {
            return response()->json(['message' => __('Api::api.unauthenticated', [], Language::forApi($request))], 401);
        }

        return parent::unauthenticated($request, $exception);
    }

    private function logError(
        Throwable $exception,
        ?string $url = null,
        ?string $method = null,
        ?string $agent = null,
        $data = null
    ): void {
        $logError = new LogError();
        $logError->subject = 'Compliance Management System';
        $logError->message = $exception->getMessage();
        $logError->stack_trace = $exception->getTraceAsString();
        $logError->method = $method ?? '-';
        $logError->data = json_encode($data);
        $logError->user_id = 'user';
        $logError->file = $exception->getFile();
        $logError->line = $exception->getLine();
        $logError->save();
    }
}
