<?php

namespace Tests\Concerns;

use App\Services\Directory\DirectoryUnavailable;
use App\Services\Directory\LdapAuthenticator;
use App\Services\Directory\LdapIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Active Directory and the employee directory, as the tests have them. A suite that bound to the real domain
 * controller would need the company network, and its wrong-password tests would count against the lock-out of a
 * real account; the HR database is somebody else's server.
 */
trait FakesDirectory
{
    /**
     * Active Directory set up, with these accounts. It keeps the one refusal that is not the directory's own (an
     * empty password), so a test cannot pass against something more permissive than what it stands in for.
     *
     * @param array<string, string> $passwords directory username => password
     * @param bool $down the directory cannot be reached
     * @return LdapAuthenticator&object{asked: string[]}
     */
    protected function fakeDirectory(array $passwords = [], bool $down = false): LdapAuthenticator
    {
        config(['ldap.url' => 'ldaps://dc.example.test', 'ldap.bind_dn' => 'CN=svc-radio,DC=example,DC=test']);

        $directory = new class($passwords, $down) extends LdapAuthenticator
        {
            /** The usernames the directory was asked about, in order. */
            public array $asked = [];

            public function __construct(private array $passwords, private bool $down)
            {
            }

            public function authenticate(string $username, string $password): ?LdapIdentity
            {
                if (trim($username) === '' || $password === '') {
                    return null;
                }
                $this->asked[] = $username;
                if ($this->down) {
                    throw new DirectoryUnavailable('The directory could not be reached.');
                }
                foreach ($this->passwords as $name => $right) {
                    if (strcasecmp((string) $name, $username) === 0 && hash_equals($right, $password)) {
                        return new LdapIdentity((string) $name, "CN={$name},OU=Users,DC=example,DC=test");
                    }
                }

                return null;
            }
        };
        $this->app->instance(LdapAuthenticator::class, $directory);

        return $directory;
    }

    /**
     * An employee directory with these people in it: a table like the one of the HR database, in the test database.
     *
     * @param array<int, array{0: string, 1: string, 2: ?string}> $employees staff number, name, work address
     */
    protected function fakeEmployeeDirectory(array $employees): void
    {
        // Said in full, so that what .env names for the real directory plays no part in a test.
        config([
            'employee_directory.enabled' => true,
            'employee_directory.connection' => config('database.default'),
            'employee_directory.table' => 'employee_directory',
            'employee_directory.columns' => ['id' => 'employee_id', 'name' => 'name', 'email' => 'email'],
            'employee_directory.sign_in_domains' => ['merdekabattery.com'],
            'employee_directory.minimum_query_length' => 2,
            'employee_directory.limit' => 8,
        ]);

        Schema::create('employee_directory', function ($table) {
            $table->string('employee_id');
            $table->string('name');
            $table->string('email')->nullable();
            // What the search has no business reading.
            $table->string('id_card_number')->nullable();
        });
        foreach ($employees as [$id, $name, $email]) {
            DB::table('employee_directory')->insert([
                'employee_id' => $id, 'name' => $name, 'email' => $email, 'id_card_number' => '3171-' . $id,
            ]);
        }
    }
}
