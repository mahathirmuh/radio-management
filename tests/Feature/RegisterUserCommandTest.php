<?php

namespace Tests\Feature;

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegisterUserCommandTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_it_registers_a_new_sso_user_with_a_role(): void
    {
        $this->artisan('user:register', ['email' => 'Budi.Santoso@MerdekaBattery.com', '--role' => 'Admin Radio'])
            ->assertSuccessful();

        $user = User::where('email', 'budi.santoso@merdekabattery.com')->firstOrFail();
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertSame('MTI', $user->company->company_orangehr_code);
        $this->assertSame(['Admin Radio'], $user->roles->pluck('name')->all());
        $this->assertNotSame('', $user->password); // random hash, never an empty or shared password

        // The registered user can open the radio register.
        $this->actingAs($user)->get(route('radio-management.radio.index'))->assertOk();
    }

    public function test_running_it_again_does_not_duplicate_the_user_or_the_role(): void
    {
        $this->artisan('user:register', ['email' => 'budi@merdekabattery.com'])->assertSuccessful();
        $this->artisan('user:register', ['email' => 'BUDI@merdekabattery.com', '--name' => 'Budi S'])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'budi@merdekabattery.com')->count());
        $user = User::where('email', 'budi@merdekabattery.com')->firstOrFail();
        $this->assertSame('Budi S', $user->name);
        $this->assertSame(1, $user->roles()->where('roles.name', 'Super Admin')->count());
    }

    public function test_the_default_role_and_company_only_apply_to_a_first_registration(): void
    {
        $this->artisan('user:register', ['email' => 'rina@merdekabattery.com', '--role' => 'Viewer Radio', '--company' => 'MBM'])
            ->assertSuccessful();
        $user = User::where('email', 'rina@merdekabattery.com')->firstOrFail();
        $company = $user->company_id;

        // Fixing the name must not turn a viewer into a Super Admin or move her to MTI.
        $this->artisan('user:register', ['email' => 'rina@merdekabattery.com', '--name' => 'Rina W'])
            ->doesntExpectOutputToContain('is now Super Admin')
            ->assertSuccessful();
        $user->refresh();
        $this->assertSame('Rina W', $user->name);
        $this->assertSame($company, $user->company_id);
        $this->assertSame(['Viewer Radio'], $user->roles()->pluck('name')->all());

        // An explicit role is added to what the account has ...
        $this->artisan('user:register', ['email' => 'rina@merdekabattery.com', '--role' => 'Admin Radio'])->assertSuccessful();
        $this->assertEqualsCanonicalizing(['Admin Radio', 'Viewer Radio'], $user->roles()->pluck('name')->all());

        // ... and --replace leaves only that role.
        $this->artisan('user:register', ['email' => 'rina@merdekabattery.com', '--role' => 'Viewer Radio', '--replace' => true])
            ->assertSuccessful();
        $this->assertSame(['Viewer Radio'], $user->roles()->pluck('name')->all());
        $this->artisan('user:register', ['email' => 'rina@merdekabattery.com', '--replace' => true])->assertFailed();

        // Granting Super Admin is said out loud.
        $this->artisan('user:register', ['email' => 'rina@merdekabattery.com', '--role' => 'Super Admin'])
            ->expectsOutputToContain('is now Super Admin')
            ->assertSuccessful();
    }

    public function test_an_existing_account_without_a_role_only_gets_the_role_that_is_typed(): void
    {
        $this->artisan('user:register', ['email' => 'budi@merdekabattery.com', '--role' => 'Admin Radio'])->assertSuccessful();
        $user = User::where('email', 'budi@merdekabattery.com')->firstOrFail();
        $user->roles()->detach();
        $user->delete(); // what User Management > delete does

        // Brought back, but not made Super Admin by a default nobody typed.
        $this->artisan('user:register', ['email' => 'budi@merdekabattery.com', '--name' => 'Budi S'])
            ->expectsOutputToContain('has no role and cannot sign in')
            ->doesntExpectOutputToContain('is now Super Admin')
            ->assertSuccessful();
        $user = User::where('email', 'budi@merdekabattery.com')->firstOrFail();
        $this->assertSame('Budi S', $user->name);
        $this->assertSame([], $user->roles()->pluck('name')->all());

        // Recovery of a locked-out owner: say the role.
        $this->artisan('user:register', ['email' => 'budi@merdekabattery.com', '--role' => 'Super Admin'])
            ->expectsOutputToContain('is now Super Admin')
            ->assertSuccessful();
        $this->assertSame(['Super Admin'], $user->roles()->pluck('name')->all());
    }

    public function test_updating_an_account_does_not_need_the_default_role_or_company_to_exist(): void
    {
        $this->artisan('user:register', ['email' => 'rina@merdekabattery.com', '--role' => 'Viewer Radio', '--company' => 'MBM'])
            ->assertSuccessful();
        $user = User::where('email', 'rina@merdekabattery.com')->firstOrFail();
        $company = $user->company_id;

        // The owner renamed role 1, and this database has no company "MTI".
        DB::table('roles')->where('id', 1)->update(['name' => 'Administrator']);
        DB::table('companies')->where('company_orangehr_code', 'MTI')->orWhere('company_code', 'MTI')
            ->update(['company_orangehr_code' => 'MTX', 'company_code' => 'MTX']);

        // Neither default is applied to an existing account, so neither is needed.
        $this->artisan('user:register', ['email' => 'rina@merdekabattery.com', '--name' => 'Rina W'])->assertSuccessful();
        $user->refresh();
        $this->assertSame('Rina W', $user->name);
        $this->assertSame($company, $user->company_id);
        $this->assertSame(['Viewer Radio'], $user->roles()->pluck('name')->all());

        // A new account does need them: the message says what is missing.
        $this->artisan('user:register', ['email' => 'baru@merdekabattery.com'])
            ->expectsOutputToContain('Role "Super Admin" not found')
            ->assertFailed();
        $this->artisan('user:register', ['email' => 'baru@merdekabattery.com', '--role' => 'Administrator'])
            ->expectsOutputToContain('Company "MTI" not found')
            ->assertFailed();
        $this->assertFalse(User::where('email', 'baru@merdekabattery.com')->exists());
    }

    public function test_it_rejects_unknown_roles_companies_and_bad_emails(): void
    {
        $this->artisan('user:register', ['email' => 'not-an-email'])->assertFailed();
        $this->artisan('user:register', ['email' => 'a@merdekabattery.com', '--role' => 'Nope'])->assertFailed();
        $this->artisan('user:register', ['email' => 'a@merdekabattery.com', '--company' => 'ZZZ'])->assertFailed();

        $this->assertFalse(User::where('email', 'a@merdekabattery.com')->exists());
    }
}
