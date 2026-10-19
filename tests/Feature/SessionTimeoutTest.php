<?php

namespace Tests\Feature;

use App\Models\Log\AuditLog;
use App\Services\Audit\AuditLogger;
use App\Services\SessionClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * The timeouts of a signed-in session (SessionClock, EndExpiredSession): 30 minutes without activity, 8 hours after
 * signing in whatever is done, both on the server; the warning of the pages (sessionTimer); no "remember me".
 */
class SessionTimeoutTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        // The clocks count whole seconds: time stands still between the requests unless a test moves it.
        $this->freezeTime();
    }

    private function signIn(string $email = 'staf@test.local'): void
    {
        $this->makeUser($email, 'Admin Radio');
        $this->post('/login', ['emailusername' => $email, 'password' => 'secret-password'])->assertRedirect(route('dashboard.index'));
    }

    private function expiredEntries(): array
    {
        return AuditLog::query()->where('action', AuditLogger::SESSION_EXPIRED)->orderBy('id')->get()
            ->map(fn (AuditLog $entry) => [$entry->user_email, $entry->new_values['Reason'] ?? null])->all();
    }

    public function test_a_session_ends_after_thirty_minutes_without_activity_and_the_login_page_says_why(): void
    {
        $this->signIn();
        $this->get(route('dashboard.index'))->assertOk();
        $this->travel(29)->minutes();
        $this->get(route('dashboard.index'))->assertOk();
        // Each request starts the idle clock again.
        $this->travel(29)->minutes();
        $this->get(route('dashboard.index'))->assertOk();

        $this->travel(30)->minutes();
        $this->get(route('dashboard.index'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/login')->assertOk()->assertSee('Session expired')->assertSee('You were inactive for 30 minutes. Please sign in again.');
        $this->assertSame([['staf@test.local', 'You were inactive for 30 minutes.']], $this->expiredEntries());
    }

    public function test_a_session_ends_eight_hours_after_signing_in_however_active(): void
    {
        $this->signIn();
        $this->assertSame(now()->getTimestamp(), session(SessionClock::SIGNED_IN));
        // Active every 25 minutes: still signed in up to the eighth hour.
        for ($minutes = 25; $minutes < 480; $minutes += 25) {
            $this->travel(25)->minutes();
            $this->postJson(route('session.keep-alive'))->assertOk()->assertJson(['idle_left' => 1800, 'absolute_left' => 28800 - $minutes * 60]);
        }
        $this->travel(25)->minutes();
        $this->postJson(route('session.keep-alive'))->assertStatus(401)->assertJson(['session_ended' => true, 'reason' => 'absolute']);
        $this->assertGuest();
        $this->get('/login')->assertSee('A session lasts at most 8 hours. Please sign in again.');
    }

    public function test_asking_the_clock_and_the_bell_is_not_activity_staying_signed_in_is(): void
    {
        $this->signIn();
        $this->travel(20)->minutes();
        $this->getJson(route('session.status'))->assertOk()->assertExactJson(['idle_left' => 600, 'absolute_left' => 27600]);
        $this->getJson(route('notifications.summary'))->assertOk();
        $this->travel(9)->minutes();
        // "Stay signed in": the idle clock starts again.
        $this->postJson(route('session.keep-alive'))->assertOk()->assertJson(['idle_left' => 1800]);
        $this->travel(29)->minutes();
        $this->getJson(route('session.status'))->assertOk()->assertJson(['idle_left' => 60]);
        $this->travel(1)->minutes();
        // A page's script is told the session ended, and goes to the login page, which says why.
        $this->getJson(route('session.status'))->assertStatus(401)->assertExactJson(['message' => 'Session expired.', 'session_ended' => true, 'reason' => 'idle']);
        $this->assertGuest();
        // A request of a page whose session is gone (signed out elsewhere): a plain 401, and the page asks the person to sign in.
        $this->getJson(route('session.status'))->assertStatus(401)->assertJsonMissing(['session_ended' => true]);
    }

    public function test_the_timeouts_follow_the_settings(): void
    {
        config(['session.idle_timeout' => 5, 'session.absolute_timeout' => 60]);
        $this->signIn();
        $this->getJson(route('session.status'))->assertExactJson(['idle_left' => 300, 'absolute_left' => 3600]);
        $this->travel(5)->minutes();
        $this->get(route('dashboard.index'))->assertRedirect(route('login'));
        $this->get('/login')->assertSee('You were inactive for 5 minutes.');

        $this->signIn('kedua@test.local');
        for ($step = 1; $step <= 14; $step++) {
            $this->travel(4)->minutes();
            $this->get(route('dashboard.index'))->assertOk();
        }
        $this->travel(4)->minutes();
        $this->get(route('dashboard.index'))->assertRedirect(route('login'));
        $this->get('/login')->assertSee('A session lasts at most 1 hour.');
    }

    public function test_no_remember_me_cookie_is_given_even_when_asked_for(): void
    {
        $user = $this->makeUser('staf@test.local', 'Admin Radio');
        $this->post('/login', ['emailusername' => 'staf@test.local', 'password' => 'secret-password', 'remember' => '1'])
            ->assertRedirect(route('dashboard.index'))
            ->assertCookieMissing(Auth::guard('web')->getRecallerName());
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_a_remember_me_cookie_from_before_brings_no_session_back(): void
    {
        $user = $this->makeUser('staf@test.local', 'Admin Radio');
        $user->forceFill(['remember_token' => 'token-dari-dulu'])->save();

        $this->withCookie(Auth::guard('web')->getRecallerName(), $user->id . '|token-dari-dulu|' . $user->password)
            ->get(route('dashboard.index'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame([['staf@test.local', 'Your session has ended.']], $this->expiredEntries());
    }

    public function test_a_form_sent_after_the_session_ended_goes_to_the_login_page_instead_of_page_expired(): void
    {
        Route::middleware('web')->post('/__session-test/form', fn () => throw new TokenMismatchException('CSRF token mismatch.'));

        $this->post('/__session-test/form')->assertRedirect(route('login'));
        $this->get('/login')->assertSee('Your session has ended. Please sign in again.');
        // A page's script still gets the 419 (sessionTimer takes it to the login page).
        $this->postJson('/__session-test/form')->assertStatus(419);
    }

    public function test_microsoft_always_asks_which_account(): void
    {
        config(['services.azure' => [
            'client_id' => 'client-of-the-test',
            'client_secret' => 'secret-of-the-test',
            'redirect' => 'http://localhost:8020/auth/microsoft/callback',
            'tenant' => 'tenant-of-the-test',
        ]]);

        $location = $this->get('http://localhost:8020/auth/microsoft/redirect')->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://login.microsoftonline.com/tenant-of-the-test/oauth2/v2.0/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('select_account', $query['prompt'] ?? null);
        $this->assertSame('client-of-the-test', $query['client_id'] ?? null);
    }

    public function test_every_page_carries_the_warning_of_its_session(): void
    {
        $page = (string) $this->actingAs($this->superAdmin())->get(route('dashboard.index'))->assertOk()->getContent();
        $this->assertStringContainsString('const sessionTimer = {', $page);
        $this->assertStringContainsString('idleMinutes: 30,', $page);
        $this->assertStringContainsString('maximum: "8 jam",', $page);
        $this->assertStringContainsString('sessionTimer.plan({"idle_left":1800,"absolute_left":28800});', $page);
        // Not on the login page: nobody is signed in there.
        Auth::logout();
        $this->assertStringNotContainsString('sessionTimer', (string) $this->get('/login')->getContent());
    }

    public function test_the_script_of_the_warning(): void
    {
        $this->checkPageScript(route('dashboard.index'), 'tests/tools/session-timer-behaviour.mjs', 10);
    }
}
