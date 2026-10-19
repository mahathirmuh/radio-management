<?php

namespace Tests\Feature;

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

class SsoLoginTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    private const CALLBACK = '/auth/microsoft/callback?code=abc&state=xyz';

    private function register(string $email, ?string $role = 'Admin Radio'): User
    {
        $user = User::create([
            'name' => 'SSO Tester',
            'email' => $email,
            'company_id' => 3,
            'password' => bcrypt('irrelevant'),
            'verify_key' => '',
            'mark_user' => '',
            'active' => '1',
        ]);
        if ($role) {
            $user->roles()->attach(DB::table('roles')->where('name', $role)->value('id'));
        }

        return $user;
    }

    /** Microsoft answers the callback with this userPrincipalName. */
    private function microsoftReturns(?string $upn): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn((new SocialiteUser())->map(['email' => $upn]));
        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);
    }

    public function test_a_registered_user_with_a_role_is_signed_in(): void
    {
        $user = $this->register('budi@merdekabattery.com');
        $this->microsoftReturns('Budi@MerdekaBattery.com'); // SQL Server compares case-insensitively

        $this->get(self::CALLBACK)->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->remember_token, 'SSO sign-in must not set a remember-me token');
        $this->get(route('dashboard.index'))->assertOk();
    }

    public function test_a_sign_in_is_noted_on_the_account_and_an_inactive_account_is_refused(): void
    {
        $user = $this->register('budi@merdekabattery.com');
        $this->microsoftReturns('budi@merdekabattery.com');

        $this->get(self::CALLBACK)->assertRedirect(route('dashboard.index'));
        $this->assertSame('Microsoft', $user->fresh()->last_login_method);
        $this->assertNotNull($user->fresh()->last_login_at);

        // Set to inactive in User Management: Microsoft says who it is, the application does not let it in.
        $this->post('/logout');
        DB::table('users')->where('id', $user->id)->update(['active' => '0', 'last_login_at' => null]);
        $this->get(self::CALLBACK)->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_an_unregistered_microsoft_account_is_refused(): void
    {
        $this->microsoftReturns('stranger@merdekabattery.com');

        $this->get(self::CALLBACK)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_registered_user_without_a_role_is_refused(): void
    {
        $this->register('norole@merdekabattery.com', null);
        $this->microsoftReturns('norole@merdekabattery.com');

        $this->get(self::CALLBACK)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_missing_identity_or_public_mailbox_is_refused(): void
    {
        $this->microsoftReturns(null);
        $this->get(self::CALLBACK)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_error_answer_from_microsoft_never_reaches_the_token_exchange(): void
    {
        Socialite::shouldReceive('driver')->never();

        $this->get('/auth/microsoft/callback?error=access_denied&error_description=cancelled')
            ->assertRedirect(route('login'));
        // An array instead of a string must not crash either.
        $this->get('/auth/microsoft/callback?error[]=x')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_state_mismatch_ends_on_the_login_page_not_on_an_error_page(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new InvalidStateException());
        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);

        $this->get(self::CALLBACK)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_starting_on_127_0_0_1_hops_to_the_callback_host_first(): void
    {
        config(['services.azure.redirect' => 'http://localhost:8020/auth/microsoft/callback']);
        Socialite::shouldReceive('driver')->never();

        $this->get('http://127.0.0.1:8020/auth/microsoft/redirect')
            ->assertRedirect('http://localhost:8020/auth/microsoft/redirect');
    }

    public function test_starting_on_the_callback_host_goes_to_microsoft(): void
    {
        config(['services.azure.redirect' => 'http://localhost:8020/auth/microsoft/callback']);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->once()->andReturn(redirect()->away('https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize'));
        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);

        $this->get('http://localhost:8020/auth/microsoft/redirect')
            ->assertRedirect('https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize');
    }

    public function test_a_production_callback_host_is_never_used_as_a_hop_target(): void
    {
        // Another environment's callback in .env must not turn the local app into a redirector.
        config(['services.azure.redirect' => 'https://radio.example.com/auth/microsoft/callback']);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->once()->andReturn(redirect()->away('https://login.microsoftonline.com/x'));
        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);

        $this->get('http://127.0.0.1:8020/auth/microsoft/redirect')
            ->assertRedirect('https://login.microsoftonline.com/x');
    }

    public function test_the_login_page_offers_microsoft_and_not_google(): void
    {
        // No Google sign-in in this application: no button, no card in the Access Login Guide, no address.
        $page = (string) $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString(route('sso-login-mirosoft'), $page);
        $this->assertStringContainsString('Microsoft SSO', $page);
        $this->assertStringNotContainsString('Google SSO', $page);
        $this->assertStringNotContainsString('logo/google.png', $page);
        $this->assertStringNotContainsString('auth/google', $page);
        // The guide names the address of the people of this application only.
        $this->assertStringContainsString('@merdekabattery.com', $page);
        $this->assertStringNotContainsString('scmnickel', $page);
        $this->get('/auth/google/redirect')->assertNotFound();
        $this->get('/auth/google/callback?code=abc&state=xyz')->assertNotFound();
    }

    public function test_self_registration_and_password_reset_are_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@x.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertNotFound();
        $this->assertFalse(User::where('email', 'x@x.com')->exists());
        $this->get('/password/reset')->assertNotFound();
        $this->get('/login')->assertOk()->assertDontSee('Register new account')
            // Nor a link to reset a password elsewhere: the one of a directory account is changed in Active Directory.
            ->assertDontSee('Forgot Password')->assertDontSee('ForgotPassword');
    }

    public function test_the_login_page_is_signed_merdeka_group_as_the_other_pages_are(): void
    {
        $this->get('/login')->assertOk()->assertSee('© Merdeka Group ' . date('Y'))->assertDontSee('Copper Gold Tbk');
    }

    public function test_the_login_pictures_are_of_merdeka_battery_materials_and_every_one_exists(): void
    {
        $page = (string) $this->get('/login')->assertOk()->getContent();
        // The plant beside the form, and the landscape behind the card (in the page's style).
        $this->assertStringContainsString('assets/img/bg-auth/mbma-operations.webp', $page);
        $this->assertStringContainsString('assets/img/bg-auth/login-landscape.webp', $page);
        $this->assertStringNotContainsString('carousel', $page);
        // No picture of the page is missing (the carousel it replaced pointed its thumbnails at files that were not there).
        preg_match_all('#/assets/img/[^"\')\s?]+#', $page, $found);
        $this->assertNotEmpty($found[0]);
        foreach (array_unique($found[0]) as $picture) {
            $this->assertFileExists(public_path(ltrim($picture, '/')), "{$picture} is on the login page but not in public/");
        }
    }

    public function test_no_welcome_popup_greets_a_page_while_real_alerts_still_show(): void
    {
        // The template flashed a "Welcome!" alert on every request, so it popped up on the login page and the others.
        $this->get('/login')->assertOk()->assertDontSee('Welcome!');
        $viewer = $this->register('viewer@merdekabattery.com', 'Viewer Radio');
        $this->actingAs($viewer)->get(route('dashboard.index'))->assertOk()->assertDontSee('Welcome!');
        // An alert that says something is still shown: the one of signing out, on the login page it ends on.
        $this->followingRedirects()->post(route('logout'))->assertOk()->assertSee('Good Bye!')->assertDontSee('Welcome!');
    }

    public function test_passwords_typed_into_the_login_form_are_not_logged(): void
    {
        $this->post('/login', ['emailusername' => 'nobody@merdekabattery.com', 'password' => 'S3cret-Typed-Password']);

        $logged = DB::table('activity_logs')->orderByDesc('id')->value('data');
        $this->assertNotNull($logged, 'the login attempt itself is still logged');
        $this->assertStringContainsString('nobody@merdekabattery.com', $logged);
        $this->assertStringNotContainsString('S3cret-Typed-Password', $logged);
    }

    public function test_the_password_form_allows_ten_tries_a_minute(): void
    {
        $attempt = fn (int $n) => $this->post('/login', ['emailusername' => 'nobody@merdekabattery.com', 'password' => 'guess-' . $n]);

        foreach (range(1, 10) as $n) {
            $attempt($n)->assertOk(); // the form again, with "not registered"
        }
        $attempt(11)->assertStatus(429);

        // Signing in with Microsoft is not held back by it.
        $this->get('/auth/microsoft/callback?error=access_denied')->assertRedirect(route('login'));
    }

    public function test_the_dashboard_follows_menu_permissions(): void
    {
        // A role that has not been granted any menu.
        DB::table('roles')->insert(['name' => 'Tanpa Menu', 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now()]);
        $outsider = $this->register('outsider@merdekabattery.com', 'Tanpa Menu');
        $viewer = $this->register('viewer@merdekabattery.com', 'Viewer Radio');

        $this->actingAs($outsider)->get(route('dashboard.index'))
            ->assertForbidden()
            ->assertSee('Logout'); // the 403 page offers a way out
        $this->actingAs($viewer)->get(route('dashboard.index'))->assertOk();
    }

    public function test_user_management_can_add_a_user_on_this_schema(): void
    {
        $this->actingAs($this->superAdmin());
        $roleId = DB::table('roles')->where('name', 'Viewer Radio')->value('id');

        $this->postJson(route('user-management.user-registered.store'), [
            'name' => 'Siti Aminah',
            'email' => 'siti.aminah@merdekabattery.com',
            'role_id' => [$roleId],
        ])->assertOk();

        $user = User::where('email', 'siti.aminah@merdekabattery.com')->firstOrFail();
        $this->assertSame(['Viewer Radio'], $user->roles->pluck('name')->all());
        $this->assertSame('1', (string) $user->active);
    }
}
