<?php

namespace Tests\Feature;

use App\Services\Directory\DirectoryUnavailable;
use App\Services\Directory\LdapAuthenticator;
use Exception;
use FreeDSx\Ldap\Control\Control;
use FreeDSx\Ldap\Entry\Entries;
use FreeDSx\Ldap\Entry\Entry;
use FreeDSx\Ldap\Exception\BindException;
use FreeDSx\Ldap\Exception\ConnectionException;
use FreeDSx\Ldap\Exception\OperationException;
use FreeDSx\Ldap\LdapClient;
use FreeDSx\Ldap\Operation\LdapResult;
use FreeDSx\Ldap\Operation\Request\SearchRequest;
use FreeDSx\Ldap\Operation\Response\BindResponse;
use FreeDSx\Ldap\Operation\ResultCode;
use FreeDSx\Ldap\Protocol\LdapMessageResponse;
use FreeDSx\Ldap\Search\Filter\EqualityFilter;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The check of a password against Active Directory, as far as it is this application's: what is asked of the
 * directory, in which order, over what kind of connection, and what is made of each answer.
 *
 * The directory is a stand-in that answers as Active Directory does (see wire()): no test reaches a real one, and a
 * wrong password here counts against nobody's lock-out. That the client really speaks to a domain controller was
 * tried by hand against the real one.
 */
class LdapAuthenticatorTest extends TestCase
{
    private const SERVICE = 'CN=svc-radio,OU=Service,DC=example,DC=test';

    private const BUDI = 'CN=Budi Santoso,OU=Users,DC=example,DC=test';

    /** What the stand-in directory holds, what reached it, and what it is told to do wrong. */
    private object $wire;

    /** What was written to the log: [level, message + context]. */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ldap.url' => 'ldaps://dc.example.test',
            'ldap.bind_dn' => self::SERVICE,
            'ldap.bind_password' => 'Sandi-Layanan-1',
            'ldap.user_search_base' => 'DC=example,DC=test',
            'ldap.user_search_filter' => '(sAMAccountName={username})',
            'ldap.timeout' => 4,
            'ldap.tls_verify' => true,
            'ldap.ca_cert' => null,
            'ldap.attributes' => ['username' => 'samaccountname', 'mail' => 'mail', 'name' => 'displayname'],
        ]);

        $this->wire = (object) [
            'people' => [
                ['name' => 'Budi.Santoso', 'dn' => self::BUDI, 'password' => 'Kata-Sandi-AD-1', 'control' => '512', 'mail' => 'budi.santoso@example.test', 'display' => 'Budi Santoso'],
                ['name' => 'citra', 'dn' => 'CN=Citra Lestari,OU=Users,DC=example,DC=test', 'password' => 'Kata-Sandi-AD-2', 'control' => '514', 'mail' => null, 'display' => 'Citra Lestari'],
            ],
            'sent' => [],
            'opened' => [],
            'fail' => [],
        ];

        Log::listen(function (MessageLogged $event) {
            $this->logged[] = [$event->level, $event->message . ' ' . json_encode($event->context)];
        });
    }

    /** The authenticator, with the stand-in in the place of the directory. */
    private function authenticator(): LdapAuthenticator
    {
        return new class($this->wire) extends LdapAuthenticator
        {
            public function __construct(private object $wire)
            {
            }

            protected function client(array $options): LdapClient
            {
                return LdapAuthenticatorTest::wire($this->wire, $options);
            }
        };
    }

    /**
     * One connection to the stand-in directory. It answers as Active Directory does: a bind with a wrong password is
     * "invalid credentials" (49), a search is for who is bound, more entries than were asked for is "size limit
     * exceeded" (4) and none of them. A filter that is not "this attribute equals this value" matches everybody:
     * what a missing escape would make of a name is then seen.
     */
    public static function wire(object $wire, array $options): LdapClient
    {
        $wire->opened[] = $options;

        return new class($wire, count($wire->opened), $options) extends LdapClient
        {
            private bool $connected = false;

            private bool $tls = false;

            private bool $bound = false;

            public function __construct(private object $wire, private int $number, array $options)
            {
                parent::__construct($options);
            }

            /** Throws what the test wants to go wrong at this step, on this connection or on any. */
            private function step(string $step): void
            {
                $fault = $this->wire->fail["{$step}#{$this->number}"] ?? $this->wire->fail[$step] ?? null;
                if ($fault instanceof Exception) {
                    throw $fault;
                }
            }

            private function connect(): void
            {
                if (! $this->connected) {
                    $this->step('connect');
                    $this->connected = true;
                }
            }

            public function startTls(): self
            {
                $this->connect();
                $this->wire->sent[] = [$this->number, 'starttls'];
                $this->step('starttls');
                $this->tls = true;

                return $this;
            }

            public function bind(string $username, string $password): LdapMessageResponse
            {
                $this->connect();
                $this->wire->sent[] = [$this->number, 'bind', $username, $password, $this->options['use_ssl'] || $this->tls ? 'encrypted' : 'in the clear'];
                $this->step('bind');

                $right = $username === LdapAuthenticatorTest::service()
                    ? 'Sandi-Layanan-1'
                    : collect($this->wire->people)->firstWhere('dn', $username)['password'] ?? null;
                if ($right === null || ! hash_equals($right, $password)) {
                    throw new BindException('Unable to bind to LDAP. 80090308: LdapErr: DSID-0C09044E, comment: AcceptSecurityContext error, data 52e, v2580', ResultCode::INVALID_CREDENTIALS);
                }
                $this->bound = true;

                return new LdapMessageResponse(1, new BindResponse(new LdapResult(ResultCode::SUCCESS)));
            }

            public function search(SearchRequest $request, Control ...$controls): Entries
            {
                $this->connect();
                $filter = $request->getFilter();
                $this->wire->sent[] = [$this->number, 'search', (string) $request->getBaseDn(), $filter->toString(), array_map(fn ($attribute) => $attribute->getName(), $request->getAttributes()), $request->getSizeLimit()];
                $this->step('search');
                if (! $this->bound) {
                    throw new OperationException('In order to perform this operation a successful bind must be completed on the connection.', ResultCode::OPERATIONS_ERROR);
                }

                $found = collect($this->wire->people)->filter(
                    fn (array $person) => ! $filter instanceof EqualityFilter
                        || (strcasecmp($filter->getAttribute(), 'sAMAccountName') === 0 && strcasecmp($filter->getValue(), $person['name']) === 0)
                );
                if ($request->getSizeLimit() > 0 && $found->count() > $request->getSizeLimit()) {
                    throw new OperationException('', ResultCode::SIZE_LIMIT_EXCEEDED);
                }

                return new Entries(...$found->map(fn (array $person) => Entry::create($person['dn'], array_filter([
                    // As the directory spells them, which is not how they are asked for.
                    'sAMAccountName' => $person['name'],
                    'userAccountControl' => $person['control'],
                    'mail' => $person['mail'],
                    'displayName' => $person['display'],
                ], fn ($value) => $value !== null)))->values()->all());
            }

            public function isConnected(): bool
            {
                return $this->connected;
            }

            public function unbind(): self
            {
                $this->wire->sent[] = [$this->number, 'unbind'];
                $this->connected = $this->bound = false;

                return $this;
            }
        };
    }

    public static function service(): string
    {
        return self::SERVICE;
    }

    /** What reached the directory, as "connection: what" lines (a bind with whom, never the password). */
    private function sent(): array
    {
        return array_map(fn (array $one) => $one[0] . ': ' . $one[1] . (isset($one[2]) && $one[1] === 'bind' ? ' ' . $one[2] : ''), $this->wire->sent);
    }

    /** The passwords that were sent to the directory, with whom and how. */
    private function binds(): array
    {
        return array_values(array_map(fn (array $one) => [$one[2], $one[3], $one[4]], array_filter($this->wire->sent, fn (array $one) => $one[1] === 'bind')));
    }

    private function logged(string $level): array
    {
        return array_column(array_filter($this->logged, fn (array $one) => $one[0] === $level), 1);
    }

    private function assertUnavailable(callable $attempt, string $said): void
    {
        try {
            $attempt();
            $this->fail('The directory was taken to have answered.');
        } catch (DirectoryUnavailable $e) {
            $this->assertSame($said, $e->getMessage());
            // The exception of the client is not passed on: its trace holds what was sent.
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_the_right_password_is_found_out_by_binding_as_that_person(): void
    {
        $identity = $this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1');

        // Who the directory says it is: its own spelling of the name, and what else was read.
        $this->assertSame(
            ['Budi.Santoso', self::BUDI, 'budi.santoso@example.test', 'Budi Santoso'],
            [$identity->username, $identity->distinguishedName, $identity->email, $identity->name]
        );

        // The service account looks the person up and is done; a second connection binds as the person.
        $this->assertSame([
            '1: bind ' . self::SERVICE,
            '1: search',
            '1: unbind',
            '2: bind ' . self::BUDI,
            '2: unbind',
        ], $this->sent());
        // Under the base and with the filter of the settings, for what is read from it, two entries at most.
        $this->assertSame(
            [1, 'search', 'DC=example,DC=test', '(sAMAccountName=budi.santoso)', ['samaccountname', 'mail', 'displayname', 'useraccountcontrol'], 2],
            $this->wire->sent[1]
        );
        $this->assertSame([[self::SERVICE, 'Sandi-Layanan-1', 'encrypted'], [self::BUDI, 'Kata-Sandi-AD-1', 'encrypted']], $this->binds());

        // Both connections: the host and the port of the address, TLS from the start, the certificate checked, and
        // no longer waited for than the settings say.
        $this->assertCount(2, $this->wire->opened);
        foreach ($this->wire->opened as $options) {
            $this->assertSame([
                'servers' => ['dc.example.test'], 'port' => 636, 'use_ssl' => true, 'ssl_validate_cert' => true,
                'ssl_ca_cert' => null, 'timeout_connect' => 4, 'timeout_read' => 4,
            ], $options);
        }
        $this->assertSame([], $this->logged);
    }

    public function test_a_wrong_password_is_no_and_nothing_more(): void
    {
        $this->assertNull($this->authenticator()->authenticate('budi.santoso', 'salah'));

        $this->assertSame(['1: bind ' . self::SERVICE, '1: search', '1: unbind', '2: bind ' . self::BUDI, '2: unbind'], $this->sent());
        // An everyday thing: not an error of the directory, and the log is not where passwords go.
        $this->assertSame([], $this->logged);
    }

    public function test_an_empty_password_or_name_never_reaches_the_directory(): void
    {
        // The directory would answer "yes" to an empty password for any name that exists.
        foreach ([['budi.santoso', ''], ['', 'Kata-Sandi-AD-1'], ["  \t", 'Kata-Sandi-AD-1']] as [$name, $password]) {
            $this->assertNull($this->authenticator()->authenticate($name, $password));
        }

        $this->assertSame([], $this->wire->opened);
        $this->assertSame([], $this->wire->sent);
    }

    public function test_a_name_the_directory_does_not_know_is_no_and_no_password_is_sent_for_it(): void
    {
        $this->assertNull($this->authenticator()->authenticate('tidak.ada', 'Kata-Sandi-AD-1'));

        $this->assertSame(['1: bind ' . self::SERVICE, '1: search', '1: unbind'], $this->sent());
        $this->assertCount(1, $this->wire->opened);
    }

    public function test_an_account_the_directory_has_disabled_is_no_and_its_password_is_not_even_tried(): void
    {
        // The right password of an account that is switched off (userAccountControl 514 = 512 + 2).
        $this->assertNull($this->authenticator()->authenticate('citra', 'Kata-Sandi-AD-2'));

        $this->assertSame(['1: bind ' . self::SERVICE, '1: search', '1: unbind'], $this->sent());
        $this->assertSame([], $this->logged);

        // Any other value with that bit set is switched off as well; without the bit it is not.
        $this->wire->people[1]['control'] = '66050';
        $this->assertNull($this->authenticator()->authenticate('citra', 'Kata-Sandi-AD-2'));
        $this->wire->people[1]['control'] = '66048';
        $this->assertSame('citra', $this->authenticator()->authenticate('citra', 'Kata-Sandi-AD-2')->username);
        $this->assertNull($this->authenticator()->authenticate('citra', 'Kata-Sandi-AD-2')->email, 'an entry without an address');
    }

    public function test_a_name_that_two_entries_answer_to_signs_nobody_in(): void
    {
        // Binding as whichever came first would sign somebody in as an account that is not theirs.
        $this->wire->people[] = ['name' => 'budi.santoso', 'dn' => 'CN=Budi Santoso (lama),OU=Arsip,DC=example,DC=test', 'password' => 'Kata-Sandi-AD-1', 'control' => '512', 'mail' => null, 'display' => null];

        $this->assertNull($this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'));
        $this->assertSame(['1: bind ' . self::SERVICE, '1: search', '1: unbind'], $this->sent());
        $this->assertCount(1, $this->logged('warning'));
        $this->assertStringContainsString('more than one entry', $this->logged('warning')[0]);

        // With three, the directory hands over none and says there were too many: the same answer.
        $this->wire->people[] = ['name' => 'BUDI.SANTOSO', 'dn' => 'CN=Budi Santoso (uji),OU=Uji,DC=example,DC=test', 'password' => 'Kata-Sandi-AD-1', 'control' => '512', 'mail' => null, 'display' => null];
        $this->wire->sent = [];
        $this->assertNull($this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'));
        $this->assertSame(['2: bind ' . self::SERVICE, '2: search', '2: unbind'], $this->sent());
        $this->assertCount(2, $this->logged('warning'));
        $this->assertSame([], $this->logged('error'));
    }

    public function test_what_a_filter_reads_as_a_sign_of_its_own_is_asked_for_as_the_letter_it_is(): void
    {
        // Each of these would, put into the filter as it is, ask for everybody (or for somebody else).
        foreach (['*', 'budi*', 'budi.santoso)(sAMAccountName=*', '*)(objectClass=*', 'a\\2a', "budi\x00"] as $name) {
            $this->wire->sent = [];
            $this->assertNull($this->authenticator()->authenticate($name, 'Kata-Sandi-AD-1'), $name);
            $this->assertCount(3, $this->wire->sent, 'looked up, and no password tried: ' . $name);
            $this->assertSame([], $this->logged('warning'), 'it matched nobody, not everybody: ' . $name);
        }

        // As the directory receives it: one comparison with the name, sign for sign.
        $this->wire->sent = [];
        $this->authenticator()->authenticate('budi)(sAMAccountName=*', 'Kata-Sandi-AD-1');
        $this->assertSame('(sAMAccountName=budi\29\28sAMAccountName=\2a)', $this->wire->sent[1][3]);

        // A filter of the settings that is more than one comparison is used as it is written.
        config(['ldap.user_search_filter' => '(&(objectClass=user)(sAMAccountName={username}))']);
        $this->wire->sent = [];
        $this->authenticator()->authenticate('budi.santoso', 'salah');
        $this->assertSame('(&(objectClass=user)(sAMAccountName=budi.santoso))', $this->wire->sent[1][3]);
    }

    public function test_a_directory_that_does_not_answer_is_not_a_wrong_password(): void
    {
        $this->wire->fail['connect'] = new ConnectionException('Unable to connect to server(s): dc.example.test', 0, new Exception('Connection timed out'));

        $this->assertUnavailable(fn () => $this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'), 'The directory could not be reached.');

        // Why, is in the log (and only there); the password is in neither.
        $this->assertCount(1, $this->logged('error'));
        $this->assertStringContainsString('could not bind as the service account: Unable to connect to server(s): dc.example.test <- Connection timed out', $this->logged('error')[0]);
        $this->assertStringNotContainsString('Kata-Sandi-AD-1', json_encode($this->logged));
        $this->assertStringNotContainsString('Sandi-Layanan-1', json_encode($this->logged));
    }

    public function test_a_service_account_the_directory_refuses_is_a_wrong_setting_not_a_wrong_password(): void
    {
        config(['ldap.bind_password' => 'sudah-diganti']);

        $this->assertUnavailable(fn () => $this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'), 'The directory could not be reached.');

        // Nothing was searched, and the password of the person went nowhere.
        $this->assertSame(['1: bind ' . self::SERVICE, '1: unbind'], $this->sent());
        $this->assertCount(1, $this->wire->opened);
        $this->assertCount(1, $this->logged('error'));
        $this->assertStringContainsString('could not bind as the service account', $this->logged('error')[0]);
        $this->assertStringNotContainsString('sudah-diganti', json_encode($this->logged));
        $this->assertStringNotContainsString('Kata-Sandi-AD-1', json_encode($this->logged));
    }

    public function test_a_search_or_a_bind_that_fails_for_another_reason_is_the_directory_not_the_password(): void
    {
        // The base of the settings is not in this directory.
        $this->wire->fail['search'] = new OperationException('0000208D: NameErr: DSID-03100245, problem 2001 (NO_OBJECT)', ResultCode::NO_SUCH_OBJECT);
        $this->assertUnavailable(fn () => $this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'), 'The directory could not be searched.');
        $this->assertSame(['1: bind ' . self::SERVICE, '1: search', '1: unbind'], $this->sent());

        // The connection is lost while the password of the person is checked.
        $this->wire->fail = ['connect#3' => new ConnectionException('The connection to the LDAP server was lost.')];
        $this->assertUnavailable(fn () => $this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'), 'The directory could not be reached.');

        // The directory will not take a bind just now.
        $this->wire->fail = ['bind#5' => new BindException('Unable to bind to LDAP. The server is busy.', ResultCode::BUSY)];
        $this->assertUnavailable(fn () => $this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'), 'The directory could not check the password.');

        $this->assertCount(3, $this->logged('error'));
        $this->assertStringNotContainsString('Kata-Sandi-AD-1', json_encode($this->logged));

        // A filter in the settings that cannot be read.
        $this->wire->fail = [];
        config(['ldap.user_search_filter' => '(sAMAccountName={username}']);
        $this->assertUnavailable(fn () => $this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'), 'The directory could not be searched.');
        $this->assertCount(1, array_filter($this->wire->sent, fn (array $one) => $one[0] === 6 && $one[1] === 'unbind'), 'the connection is closed all the same');
    }

    public function test_on_ldap_the_connection_is_encrypted_before_any_password_is_sent(): void
    {
        config(['ldap.url' => 'ldap://dc.example.test']);

        $this->assertNotNull($this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'));

        $this->assertSame([
            '1: starttls', '1: bind ' . self::SERVICE, '1: search', '1: unbind',
            '2: starttls', '2: bind ' . self::BUDI, '2: unbind',
        ], $this->sent());
        $this->assertSame(['encrypted', 'encrypted'], array_column($this->binds(), 2));
        $this->assertSame([[389, false], [389, false]], array_map(fn (array $options) => [$options['port'], $options['use_ssl']], $this->wire->opened));

        // A directory that will not encrypt gets no password, of the service account or of anybody.
        $this->wire->sent = [];
        $this->wire->fail['starttls'] = new OperationException('00000000: LdapErr: DSID-0C090F78, comment: Error initializing SSL/TLS', ResultCode::UNAVAILABLE);
        $this->assertUnavailable(fn () => $this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'), 'The directory could not be reached.');
        $this->assertSame(['3: starttls', '3: unbind'], $this->sent());
        $this->assertSame([], $this->binds());
    }

    public function test_the_address_and_the_certificate_are_taken_from_the_settings(): void
    {
        // A port of its own, the check of the certificate off, and a CA named for it.
        config(['ldap.url' => 'LDAPS://dc.example.test:3269', 'ldap.tls_verify' => false, 'ldap.ca_cert' => 'C:\\certs\\ca-internal.pem', 'ldap.timeout' => 0]);
        $this->authenticator()->authenticate('budi.santoso', 'salah');
        $this->assertSame([
            'servers' => ['dc.example.test'], 'port' => 3269, 'use_ssl' => true, 'ssl_validate_cert' => false,
            'ssl_ca_cert' => 'C:\\certs\\ca-internal.pem', 'timeout_connect' => 1, 'timeout_read' => 1,
        ], $this->wire->opened[0]);

        // What is no address of a directory is said as that, and nothing is opened for it.
        $this->wire->opened = $this->wire->sent = [];
        foreach (['dc.example.test', 'https://dc.example.test', 'ldaps://', 'ldaps:///DC=example'] as $address) {
            config(['ldap.url' => $address]);
            try {
                $this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1');
                $this->fail($address);
            } catch (DirectoryUnavailable $e) {
                $this->assertStringContainsString('LDAP_URL', $e->getMessage());
            }
        }
        $this->assertSame([], $this->wire->opened);

        // Not set up at all.
        config(['ldap.url' => null]);
        $this->assertFalse($this->authenticator()->isConfigured());
        $this->assertUnavailable(fn () => $this->authenticator()->authenticate('budi.santoso', 'Kata-Sandi-AD-1'), 'The directory is not configured (LDAP_URL, LDAP_BIND_DN).');
        config(['ldap.url' => 'ldaps://dc.example.test', 'ldap.bind_dn' => '']);
        $this->assertFalse($this->authenticator()->isConfigured());
        $this->assertSame([], $this->wire->opened);
    }
}
