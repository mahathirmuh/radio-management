<?php

namespace App\Console\Commands;

use App\Models\Organization\Company;
use App\Models\UserManagement\Role;
use App\Models\UserManagement\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers a user for SSO login. SSO only lets in e-mail addresses that already
 * exist in `users`, so the very first account has to be created from the console.
 */
class RegisterUser extends Command
{
    protected $signature = 'user:register
        {email : E-mail the person signs in with (for Microsoft: the userPrincipalName)}
        {--name= : Display name (default: derived from the e-mail)}
        {--role=Super Admin : Role name, e.g. "Super Admin", "Admin Radio", "Viewer Radio". The default is for a new account; an existing account only gets a role that is typed}
        {--replace : Remove the roles the account already has and leave only --role}
        {--company=MTI : Company code (companies.company_orangehr_code or company_code). Without --company an existing account keeps its company}';

    protected $description = 'Register (or update) a user so they can sign in with SSO, and give them a role';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("\"{$email}\" is not a valid e-mail address.");

            return self::FAILURE;
        }

        // The defaults are for a first registration. On an account that already exists (also a
        // deleted one that this command brings back), role and company only change when typed:
        // re-running the command to fix a name must not make anyone Super Admin.
        $roleGiven = $this->input->hasParameterOption('--role');
        $companyGiven = $this->input->hasParameterOption('--company');

        if ($this->option('replace') && ! $roleGiven) {
            $this->error('--replace needs --role: say which role the account should be left with.');

            return self::FAILURE;
        }

        $existing = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
        $isNew = $existing === null;

        // Role and company are only looked up when they will be applied: a renamed default role
        // or a missing default company must not stop an update that does not touch them.
        $role = null;
        if ($roleGiven || $isNew) {
            $role = Role::query()->whereRaw('LOWER(name) = ?', [Str::lower($this->option('role'))])->first();
            if (! $role) {
                $this->error("Role \"{$this->option('role')}\" not found. Available: " . Role::query()->orderBy('name')->pluck('name')->implode(', '));

                return self::FAILURE;
            }
        }

        $company = null;
        if ($companyGiven || $isNew || ! $existing->company_id) {
            $code = $this->option('company');
            $company = Company::query()
                ->where('company_orangehr_code', $code)
                ->orWhere('company_code', $code)
                ->first();
            if (! $company) {
                $this->error("Company \"{$code}\" not found. Use a code from companies.company_orangehr_code, e.g. MTI, MBM, MCG.");

                return self::FAILURE;
            }
        }

        $name = $this->option('name') ?: Str::title(str_replace(['.', '_', '-'], ' ', Str::before($email, '@')));
        $grantedSuperAdmin = false;

        $user = DB::transaction(function () use ($existing, $email, $name, $company, $role, &$grantedSuperAdmin) {
            if ($user = $existing) {
                if ($user->trashed()) {
                    $user->restore();
                }
                $user->active = '1';
                if ($company) {
                    $user->company_id = $company->id;
                }
                if ($this->option('name')) {
                    $user->name = $name;
                }
                // The row may predate this command (seeded, self-registered, shared password):
                // make sure only SSO can open the account from now on.
                $user->password = bcrypt(Str::random(40));
                $user->remember_token = null;
                $user->save();
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'company_id' => $company->id,
                    // Never used: these accounts sign in through SSO only.
                    'password' => bcrypt(Str::random(40)),
                    'verify_key' => '',
                    'mark_user' => '',
                    'active' => '1',
                ]);
            }

            if ($role) {
                $held = $user->roles()->pluck('roles.id');
                if ($this->option('replace')) {
                    $user->roles()->sync([$role->id]);
                } elseif (! $held->contains($role->id)) {
                    $user->roles()->attach($role->id);
                }
                $grantedSuperAdmin = (int) $role->id === 1 && ! $held->contains(1);
            }

            return $user;
        });

        $this->info(($user->wasRecentlyCreated ? 'Registered' : 'Updated') . " {$user->email}");
        $this->table(['Name', 'E-mail', 'Company', 'Roles'], [[
            $user->name,
            $user->email,
            $user->company()->value('name'),
            $user->roles()->pluck('name')->implode(', '),
        ]]);
        if ($grantedSuperAdmin) {
            $this->warn("{$user->email} is now Super Admin: full access to every menu, including User Management.");
        }
        if (! $user->roles()->exists()) {
            $this->warn("{$user->email} has no role and cannot sign in. Run the command again with --role=\"...\".");
        }

        return self::SUCCESS;
    }
}
