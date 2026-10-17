<?php

namespace App\Http\Controllers\Auth;

use Exception;
use App\Models\UserManagement\User;
use App\Models\Log\LogActivity;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\Audit\AuditLogger;
use App\Services\Directory\DirectoryUnavailable;
use App\Services\Directory\LdapAuthenticator;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use RealRashid\SweetAlert\Facades\Alert;

//MerdekaApps
use Illuminate\Support\Facades\Log;
use App\Models\UserManagement\UserMdka;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    private $urlSafe;
    private $urlMerdeka;

    public function __construct()
    {
        $this->urlMerdeka = config('merdeka.url');
        $this->urlSafe = config('merdeka.safe');
        $this->middleware('guest')->except('logout');
        // Accounts sign in with SSO and have random passwords; the form is still rate limited
        // (10 tries a minute per address) so it cannot be used to guess one.
        $this->middleware('throttle:10,1')->only('login');
    }
    //LOGIN INTERNAL
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emailusername' => 'required|min:2|max:255|string',
            // 'password' => 'required|min:2|max:255|string|regex:/^[a-zA-Z0-9\s\-\.,]+$/',
        ]);
        if ($validator->fails()) {
            Alert::warning('Input Salah!', $validator->errors()->all());
            return view('auth.login');
        } else {
            try {
                // $auth = false;
                $remember = $request->input('remember');
                //PROCCESS Login
                $account = $this->accountFor((string) $request->emailusername);
                $email = $account?->email;
                if (isset($email) && $account->signsInWithDirectory()) {
                    return $this->loginWithDirectory($request, $account);
                }
                if (isset($email)) {
                    // An account set to inactive in User Management is refused the way a wrong password is.
                    $active = fn ($user) => $user->isActive();
                    if (Auth::guard('web')->attemptWhen(['email' => $email, 'password' => $request->password], $active, $remember)) {
                        Auth::user()->recordSignIn('Password');
                        $request->session()->regenerate();
                        return redirect()->route(Auth::user()->homeRoute());
                    } else {
                        $this->refused((string) $request->emailusername, $account, 'Password', $account->isActive() ? 'Wrong password' : 'Account not active');
                        Alert::warning('Username or Password Wrong!', 'Try Again!');
                        return view('auth.login');
                    }
                } else {
                    $this->refused((string) $request->emailusername, null, 'Login form', 'Not registered');
                    Alert::warning('Your account is not registered!', 'please contact IT the Helpdesk');
                    return view('auth.login');
                }
            } catch (Exception $e) {
                Alert::error('Server Error!', 'please contact IT the Helpdesk');
                return view('auth.login');
            };
        }
    }
    /**
     * The account the login form is about. It is named by its e-mail, or by its directory username: who signs in with
     * the Active Directory password may type the name Windows knows them by ("budi.santoso"), without the "@...".
     */
    private function accountFor(string $typed): ?User
    {
        $typed = trim($typed);
        // People only: an API client (page API Tokens) never signs in on the screen, whatever is typed.
        if ($account = User::people()->where('email', $typed)->first()) {
            return $account;
        }
        if ($typed === '' || ! User::directoryReady()) {
            return null;
        }

        // One directory username belongs to one account (User Management sees to it). Should two answer to it all the
        // same, nobody is signed in on that: it would be the first one found, not the one who typed it.
        $named = User::people()->whereRaw('LOWER(ldap_username) = ?', [Str::lower($typed)])->limit(2)->get();

        return $named->count() === 1 ? $named->first() : null;
    }

    /**
     * A refused sign-in, for the audit trail: what was typed into the form or sent by the provider (never a password),
     * the way in, and why it was refused. What the screen says does not change: the reason is for whoever reads the
     * trail (a wrong password and an inactive account still look the same to the person at the form).
     */
    private function refused(string $typed, ?User $account, string $via, string $reason): void
    {
        $typed = mb_substr(trim($typed), 0, 255);
        $values = ['Account' => $typed !== '' ? $typed : null, 'Via' => $via, 'Reason' => $reason];
        // Signing in with the directory username: the address of the account says whose it is.
        if ($account !== null && strcasecmp($typed, (string) $account->email) !== 0) {
            $values['Email'] = $account->email;
        }

        app(AuditLogger::class)->log(AuditLogger::LOGIN_FAILED, new: array_filter($values, fn ($value) => $value !== null));
    }

    /**
     * The login form for an account that has a directory username (User Management, "LDAP"): the password is the one
     * of its Active Directory account, checked by binding to the directory as that person. The password this
     * application keeps for the account is not looked at.
     */
    private function loginWithDirectory(Request $request, User $account)
    {
        $wrong = function (string $reason) use ($request, $account) {
            $this->refused((string) $request->emailusername, $account, User::SIGN_IN_LDAP, $reason);
            Alert::warning('Username or Password Wrong!', 'Try Again!');
            return view('auth.login');
        };

        // Set to inactive in User Management: refused the way a wrong password is, and the directory is not asked.
        if (! $account->isActive()) {
            return $wrong('Account not active');
        }

        try {
            $identity = app(LdapAuthenticator::class)->authenticate((string) $account->ldap_username, (string) $request->password);
        } catch (DirectoryUnavailable $e) {
            // Not a wrong password: saying so would send people to reset one that was right.
            Log::error('A sign-in could not be checked against the directory: ' . $e->getMessage());
            Alert::warning('The directory cannot be reached', 'Try again shortly, or sign in with Microsoft.')->persistent(true);
            return view('auth.login');
        }

        if ($identity === null) {
            return $wrong('Wrong password');
        }

        // As with Microsoft: every page is gated by menu/role, so an account without a role could only see 403 pages.
        if ($account->roles()->doesntExist()) {
            $this->refused((string) $request->emailusername, $account, User::SIGN_IN_LDAP, 'No role');
            Alert::warning('No access yet', $account->email . ' has no role in this application. Ask the admin to assign one.')->persistent(true);
            return view('auth.login');
        }

        // No "remember me", as with Microsoft: access ends with the session, and with the directory account.
        Auth::login($account);
        $account->recordSignIn(User::SIGN_IN_LDAP);
        $request->session()->regenerate();

        return redirect()->route($account->homeRoute());
    }
    //LOGIN MERDEKA APPS
    public function redirectToApps(Request $request)
    {
        if ($request->has('apps')) {
            try {
                $access = $request->input('apps');
                //Buka Gate Merdeka Apps
                $gate = UserMdka::where('remember_token', $access)->whereNotNull('remember_token')->first();
                if (isset($gate)) {
                    //cek keys dengan Apps Tujuan
                    $user = User::where('email', $gate->email)->first();
                    if ($user && ! $user->isActive()) {
                        Alert::warning('Account inactive', 'Ask the admin to activate it.');
                        return redirect()->route('login');
                    }
                    if ($user) {
                        if (!Auth::check()) {
                            $gate->remember_token = null;
                            $gate->save();
                            Auth::login($user, true);
                            $user->recordSignIn('Merdeka Apps');
                            $request->session()->regenerate();
                            return redirect()->route('dashboard.index');
                        } else {
                            Alert::error('Authenticate Failed!', 'try again!');
                            return redirect()->route('login');
                        }
                    } else {
                        return redirect()->to($this->urlMerdeka);
                    }
                } else {
                    return redirect()->to($this->urlMerdeka);
                }
            } catch (Exception $e) {
                Log::error($e->getMessage());
                return redirect()->to($this->urlMerdeka);
            }
        } else {
            Alert::info('Sign in failed!', 'Please use MerdekaApps Credential!');
            return redirect()->route('login');
        }
    }
    //LOGIN SSO GOOGLE
    public function redirectToProviderGoogle()
    {
        return Socialite::driver('google')->redirect();
    }
    public function handleProviderCallbackGoogle(Request $request)
    {
        try {
            $user = Socialite::driver('google')->user();
            return $this->loginSsoUser($request, $user->getEmail(), 'Google');
        } catch (\Throwable $e) {
            Log::error('Google SSO login failed: ' . get_class($e) . ': ' . $e->getMessage());
            Alert::error('Server Error!', 'please contact IT the Helpdesk');
            return redirect()->route('login');
        }
    }
    //LOGIN SSO MICROSOFT
    public function redirectToProviderMicrosoft(Request $request)
    {
        // The OAuth state lives in a host-only session cookie, so the flow has to start on the
        // host Microsoft redirects back to. Locally that is "localhost", while `php artisan serve`
        // advertises 127.0.0.1: hop between loopback names only, never to another site.
        $callback = parse_url((string) config('services.azure.redirect')) ?: [];
        $loopback = ['localhost', '127.0.0.1', '[::1]'];
        if (isset($callback['host']) && $callback['host'] !== $request->getHost()
            && in_array($callback['host'], $loopback, true) && in_array($request->getHost(), $loopback, true)) {
            return redirect()->away(($callback['scheme'] ?? 'http') . '://' . $callback['host']
                . (isset($callback['port']) ? ':' . $callback['port'] : '') . $request->getRequestUri());
        }

        return Socialite::driver('azure')->redirect();
    }
    public function handleProviderCallbackMicrosoft(Request $request)
    {
        // Microsoft answers with ?error=... instead of a code when the person cancels or consent is refused.
        if ($request->filled('error')) {
            Log::warning('Microsoft SSO returned an error instead of a code', $request->only(['error', 'error_subcode', 'error_description']));
            Alert::warning('Microsoft sign-in was not completed', 'Please try again.');
            return redirect()->route('login');
        }

        try {
            $user = Socialite::driver('azure')->user();
            return $this->loginSsoUser($request, $user->getEmail(), 'Microsoft');
        } catch (InvalidStateException $e) {
            Log::warning('Microsoft SSO: state mismatch on ' . $request->getHttpHost() . ' (login started on another host, or the session expired)');
            Alert::warning('Sign-in session expired', 'Please click Microsoft again.');
            return redirect()->route('login');
        } catch (\Throwable $e) {
            Log::error('Microsoft SSO login failed: ' . get_class($e) . ': ' . $e->getMessage());
            Alert::error('Server Error!', 'please contact IT the Helpdesk');
            return redirect()->route('login');
        }
    }

    /**
     * Shared login flow for SSO providers: rejects public email domains,
     * requires a pre-registered user record that has a role, then authenticates.
     */
    private function loginSsoUser(Request $request, ?string $email, string $provider)
    {
        $email = trim((string) $email);
        if ($email === '') {
            Log::warning($provider . ' SSO returned no e-mail / userPrincipalName');
            $this->refused('', null, $provider, 'No e-mail address from ' . $provider);
            Alert::warning('Sign-in failed', $provider . ' did not send an e-mail address for this account.')->persistent(true);
            return redirect()->route('login');
        }

        if (Str::endsWith(Str::lower($email), ['@gmail.com', '@yahoo.com'])) {
            $this->refused($email, null, $provider, 'Public e-mail address');
            Alert::warning('Sorry you are using public email for login!', 'Please contact HR immediately to address this issue. your email logged : ' . $email);
            return redirect()->route('login');
        }

        $user = User::people()->where('email', $email)->first();

        if ($user === null) {
            Log::warning($provider . ' SSO refused: no users row', ['email' => $email]);
            $this->refused($email, null, $provider, 'Not registered');
            Alert::warning("The user is not registered in {$provider} SSO!", 'Please contact HR immediately to address this issue. your email logged : ' . $email)->persistent(true);
            return redirect()->route('login');
        }

        // Every page is gated by menu/role, so an account without a role could only see 403 pages.
        if ($user->roles()->doesntExist()) {
            Log::warning($provider . ' SSO refused: user has no role', ['email' => $email]);
            $this->refused($email, $user, $provider, 'No role');
            Alert::warning('No access yet', $email . ' has no role in this application. Ask the admin to assign one.')->persistent(true);
            return redirect()->route('login');
        }

        // Set to inactive in User Management.
        if (! $user->isActive()) {
            Log::warning($provider . ' SSO refused: account is inactive', ['email' => $email]);
            $this->refused($email, $user, $provider, 'Account not active');
            Alert::warning('Account inactive', $email . ' has been deactivated in this application. Ask the admin to activate it.')->persistent(true);
            return redirect()->route('login');
        }

        // No "remember me": access should end with the session, not outlive the SSO account by months.
        Auth::login($user);
        $user->recordSignIn($provider);
        $request->session()->regenerate();

        return redirect()->route($user->homeRoute());
    }
    // LOGIN M-SAFE
    public function nonCompanyEmailLogin(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if the employee exists
            $user = User::where('employee_id', $request->employee_id)->first();

            if (!$user || !Auth::guard('web')->attempt(['employee_id' => $request->employee_id, 'password' => $request->password])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials. Please try again.'
                ], 401);
            }

            // Regenerate session and log the user in
            $request->session()->regenerate();
            Auth::login($user);
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'redirect_url' => route('dashboard.index')
            ], 200);

        } catch (Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login. Please try again later.'
            ], 500);
        }
    }
    // LOGOUT
    public function logout(Request $request)
    {
        // Written while it is still known who it is.
        if (Auth::check()) {
            app(AuditLogger::class)->log(AuditLogger::LOGOUT);
        }
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        Alert::success('Logout Success', 'Good Bye!');
        // return redirect()->away($this->urlMerdeka);
        return redirect()->route('login');
    }
}
