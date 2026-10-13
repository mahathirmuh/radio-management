<?php

namespace Tests\Feature;

use App\Models\UserManagement\User;
use App\Services\Directory\DirectoryUnavailable;
use App\Services\Directory\LdapAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\FakesDirectory;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

/**
 * The login form for an account that has a directory username: its password is the one of Active Directory.
 * (The directory itself is a stand-in here, see FakesDirectory.)
 */
class DirectoryLoginTest extends TestCase
{
    use FakesDirectory;
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    /** An account whose directory username is $username. Its password here is "secret-password", as for every test account. */
    private function directoryAccount(string $email, string $username, int|string|null $role = 'Viewer Radio'): User
    {
        $user = $this->makeUser($email, $role);
        $user->update(['ldap_username' => $username]);

        return $user->fresh();
    }

    /** The login page as it came back from the last attempt (it carries what is said about a refusal). */
    private string $page = '';

    private function login(string $email, ?string $password, array $more = [])
    {
        $response = $this->post('/login', ['emailusername' => $email, 'password' => $password] + $more);
        $this->page = (string) $response->getContent();

        return $response;
    }

    /** What the login page says after a refusal. */
    private function said(): string
    {
        return $this->page;
    }

    public function test_the_directory_password_signs_a_directory_account_in(): void
    {
        $user = $this->directoryAccount('budi.santoso@merdekabattery.com', 'budi.santoso');
        $directory = $this->fakeDirectory(['budi.santoso' => 'Kata-Sandi-AD-1']);

        $this->login($user->email, 'Kata-Sandi-AD-1', ['remember' => 'on'])->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(['budi.santoso'], $directory->asked);
        $this->assertSame('LDAP', $user->fresh()->last_login_method);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertNull($user->fresh()->remember_token, 'access ends with the session, as with Microsoft');
        $this->get(route('dashboard.index'))->assertOk();
    }

    public function test_it_is_the_recorded_directory_account_that_is_asked_not_the_address(): void
    {
        // The account of this person is named differently from the address: that is what the field is for.
        $user = $this->directoryAccount('budi.santoso@merdekabattery.com', 'bsantoso');
        $directory = $this->fakeDirectory(['bsantoso' => 'Kata-Sandi-AD-1', 'budi.santoso' => 'Milik-Orang-Lain-9']);

        // Somebody else holds the directory account that is named like the address: their password does not get in.
        $this->login($user->email, 'Milik-Orang-Lain-9');
        $this->assertGuest();

        $this->login($user->email, 'Kata-Sandi-AD-1')->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['bsantoso', 'bsantoso'], $directory->asked);
    }

    public function test_a_wrong_or_empty_password_is_refused_and_the_password_kept_here_opens_nothing(): void
    {
        $user = $this->directoryAccount('budi.santoso@merdekabattery.com', 'budi.santoso');
        $directory = $this->fakeDirectory(['budi.santoso' => 'Kata-Sandi-AD-1']);

        $this->login($user->email, 'salah')->assertOk();
        $this->assertGuest();
        $this->assertStringContainsString('Username or Password Wrong', $this->said());

        // The password this application keeps for the account is not a way in: the directory is the only check.
        $this->assertTrue(password_verify('secret-password', $user->password));
        $this->login($user->email, 'secret-password')->assertOk();
        $this->assertGuest();
        $this->assertSame(['budi.santoso', 'budi.santoso'], $directory->asked);

        // An empty password never reaches the directory: it would answer "yes" to it for any account that exists.
        foreach (['', null] as $empty) {
            $this->login($user->email, $empty);
            $this->assertGuest();
        }
        $this->assertCount(2, $directory->asked);
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_a_directory_that_cannot_be_asked_is_not_called_a_wrong_password(): void
    {
        $user = $this->directoryAccount('budi.santoso@merdekabattery.com', 'budi.santoso');
        $this->fakeDirectory(['budi.santoso' => 'Kata-Sandi-AD-1'], down: true);

        $this->login($user->email, 'Kata-Sandi-AD-1')->assertOk();

        $this->assertGuest();
        $this->assertStringContainsString('directory cannot be reached', $this->said());
        $this->assertStringContainsString('sign in with Microsoft', $this->said());
        $this->assertStringNotContainsString('Wrong', $this->said());
        // And the password kept here does not become a way in while the directory is away.
        $this->login($user->email, 'secret-password');
        $this->assertGuest();
    }

    public function test_without_the_ldap_extension_or_its_settings_the_answer_is_the_same_outage(): void
    {
        $user = $this->directoryAccount('budi.santoso@merdekabattery.com', 'budi.santoso');

        // Not set up at all (phpunit.xml leaves LDAP_URL empty): the real authenticator says so.
        $this->assertFalse(app(LdapAuthenticator::class)->isConfigured());
        $this->assertFalse(app(LdapAuthenticator::class)->usable());
        $this->login($user->email, 'Kata-Sandi-AD-1')->assertOk();
        $this->assertGuest();
        $this->assertStringContainsString('directory cannot be reached', $this->said());

        // The refusals that need no directory come first, set up or not.
        $this->assertNull(app(LdapAuthenticator::class)->authenticate('budi.santoso', ''));
        $this->assertNull(app(LdapAuthenticator::class)->authenticate('  ', 'Kata-Sandi-AD-1'));
        try {
            app(LdapAuthenticator::class)->authenticate('budi.santoso', 'Kata-Sandi-AD-1');
            $this->fail('A directory that is not set up was asked.');
        } catch (DirectoryUnavailable $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }

        // Set up, but this PHP has no ldap extension (the state of a server where it could not be loaded).
        if (! extension_loaded('ldap')) {
            config(['ldap.url' => 'ldaps://dc.example.test', 'ldap.bind_dn' => 'CN=svc-radio,DC=example,DC=test']);
            $this->assertTrue(app(LdapAuthenticator::class)->isConfigured());
            $this->assertFalse(app(LdapAuthenticator::class)->usable());
            try {
                app(LdapAuthenticator::class)->authenticate('budi.santoso', 'Kata-Sandi-AD-1');
                $this->fail('Nothing can be asked without the extension.');
            } catch (DirectoryUnavailable $e) {
                $this->assertStringContainsString('ldap extension', $e->getMessage());
            }
            $this->login($user->email, 'Kata-Sandi-AD-1')->assertOk();
            $this->assertGuest();
            $this->assertStringContainsString('directory cannot be reached', $this->said());
        }
    }

    public function test_an_inactive_account_or_one_without_a_role_is_not_let_in_by_the_directory(): void
    {
        $inactive = $this->directoryAccount('nonaktif@merdekabattery.com', 'nonaktif');
        DB::table('users')->where('id', $inactive->id)->update(['active' => '0']);
        $bare = $this->directoryAccount('tanpa.role@merdekabattery.com', 'tanpa.role', null);
        $directory = $this->fakeDirectory(['nonaktif' => 'Kata-Sandi-AD-1', 'tanpa.role' => 'Kata-Sandi-AD-2']);

        // Refused the way a wrong password is, and the directory is not even asked.
        $this->login($inactive->email, 'Kata-Sandi-AD-1')->assertOk();
        $this->assertGuest();
        $this->assertStringContainsString('Username or Password Wrong', $this->said());
        $this->assertSame([], $directory->asked);

        // Who they are is proved; what they may do here is nothing yet.
        $this->login($bare->email, 'Kata-Sandi-AD-2')->assertOk();
        $this->assertGuest();
        $this->assertStringContainsString('has no role', $this->said());
        $this->assertNull($bare->fresh()->last_login_at);
    }

    public function test_an_account_without_a_directory_username_signs_in_as_before(): void
    {
        $user = $this->makeUser('lama@merdekabattery.com', 'Viewer Radio');
        $directory = $this->fakeDirectory(['lama' => 'Kata-Sandi-AD-1']);

        // A directory account that happens to be named like the address is not this account's: nothing was recorded.
        $this->login($user->email, 'Kata-Sandi-AD-1');
        $this->assertGuest();
        $this->assertSame([], $directory->asked);

        $this->login($user->email, 'secret-password')->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Password', $user->fresh()->last_login_method);
    }

    public function test_before_the_migration_has_run_nobody_is_asked_for_a_directory_password(): void
    {
        // The code of this folder is live as soon as it is saved; the migration is run by hand, later.
        $user = $this->makeUser('lama@merdekabattery.com', 'Viewer Radio');
        $directory = $this->fakeDirectory(['lama' => 'Kata-Sandi-AD-1']);
        Schema::table('users', function ($table) {
            $table->dropIndex(['ldap_username']);
            $table->dropColumn(['ldap_username', 'microsoft_linked_at']);
        });
        Cache::forget('users-directory-columns');

        $this->assertFalse(User::directoryReady());
        $this->assertFalse($user->fresh()->signsInWithDirectory());
        $this->assertSame('Pending', $user->fresh()->signInMethod());

        $this->login($user->email, 'secret-password')->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $directory->asked);
        $this->assertSame('Password', $user->fresh()->signInMethod());

        // A sign-in with Microsoft is noted as far as there is room for it.
        $user->fresh()->recordSignIn('Microsoft');
        $this->assertSame('Microsoft', $user->fresh()->signInMethod());
        Cache::forget('users-directory-columns');
    }

    public function test_the_migration_knows_who_has_signed_in_with_microsoft_before(): void
    {
        $viaMicrosoft = $this->makeUser('microsoft@merdekabattery.com', 'Viewer Radio');
        $viaPassword = $this->makeUser('password@merdekabattery.com', 'Viewer Radio');
        $never = $this->makeUser('belum@merdekabattery.com', 'Viewer Radio');
        DB::table('users')->where('id', $viaMicrosoft->id)->update(['last_login_at' => '2026-10-13 08:00:00', 'last_login_method' => 'Microsoft']);
        DB::table('users')->where('id', $viaPassword->id)->update(['last_login_at' => '2026-10-13 09:00:00', 'last_login_method' => 'Password']);

        // (The pages remember for a minute whether the columns are there; the migration makes them ask again.)
        $this->assertTrue(User::directoryReady());
        $migration = require database_path('migrations/2026_10_13_110000_add_directory_sign_in_to_users_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'ldap_username'));
        $this->assertFalse(User::directoryReady());
        $migration->up();
        $migration->up(); // run twice: nothing to do the second time
        $this->assertTrue(User::directoryReady());

        $this->assertSame('2026-10-13 08:00', $viaMicrosoft->fresh()->microsoft_linked_at->format('Y-m-d H:i'));
        $this->assertNull($viaPassword->fresh()->microsoft_linked_at);
        $this->assertNull($never->fresh()->microsoft_linked_at);
        $this->assertSame(['Microsoft', 'Password', 'Pending'], [
            $viaMicrosoft->fresh()->signInMethod(), $viaPassword->fresh()->signInMethod(), $never->fresh()->signInMethod(),
        ]);
    }
}
