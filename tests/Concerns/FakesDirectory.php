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
     * @param array<string, string> $passwords directory username => password (accounts that can sign in)
     * @param bool $down the directory cannot be reached
     * @param array<string, array{name?: ?string, email?: ?string, disabled?: bool}> $people more accounts, as "Find employee"
     *     and the check of a directory username see them (those of $passwords are there too, with no name or address)
     * @return LdapAuthenticator&object{asked: string[], searched: string[]}
     */
    protected function fakeDirectory(array $passwords = [], bool $down = false, array $people = []): LdapAuthenticator
    {
        config(['ldap.url' => 'ldaps://dc.example.test', 'ldap.bind_dn' => 'CN=svc-radio,DC=example,DC=test']);

        $directory = new class($passwords, $down, $people) extends LdapAuthenticator
        {
            /** The usernames the directory was asked about, in order. */
            public array $asked = [];

            /** What was searched for, in order. */
            public array $searched = [];

            public function __construct(private array $passwords, private bool $down, private array $people)
            {
            }

            /** Every account: username => [username, name, email, disabled]. */
            private function accounts(): array
            {
                $all = [];
                foreach (array_keys($this->passwords) as $name) {
                    $all[strtolower((string) $name)] = ['username' => (string) $name, 'name' => null, 'email' => null, 'disabled' => false];
                }
                foreach ($this->people as $name => $person) {
                    $all[strtolower((string) $name)] = ['username' => (string) $name, 'name' => $person['name'] ?? null,
                        'email' => $person['email'] ?? null, 'disabled' => (bool) ($person['disabled'] ?? false)];
                }

                return $all;
            }

            public function search(string $term, int $limit = 8): array
            {
                $this->searched[] = $term;
                if ($this->down) {
                    throw new DirectoryUnavailable('The directory could not be searched.');
                }
                // As the filter of config ldap.people_search_filter: in the name, the username or the address; not disabled.
                $found = array_values(array_filter($this->accounts(), fn (array $one) => ! $one['disabled'] && (
                    str_contains(mb_strtolower($one['username']), mb_strtolower(trim($term)))
                    || str_contains(mb_strtolower((string) $one['name']), mb_strtolower(trim($term)))
                    || str_contains(mb_strtolower((string) $one['email']), mb_strtolower(trim($term))))));
                $people = array_map(fn (array $one) => ['username' => $one['username'], 'name' => $one['name'], 'email' => $one['email']], $found);

                return ['people' => array_slice($people, 0, $limit), 'more' => count($people) > $limit];
            }

            public function lookup(string $username): ?array
            {
                $this->asked[] = $username;
                if ($this->down) {
                    throw new DirectoryUnavailable('The directory could not be reached.');
                }

                return $this->accounts()[strtolower(trim($username))] ?? null;
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
