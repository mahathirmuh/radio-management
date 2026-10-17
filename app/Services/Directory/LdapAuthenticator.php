<?php

namespace App\Services\Directory;

use Exception;
use FreeDSx\Ldap\Controls;
use FreeDSx\Ldap\Entry\Entry;
use FreeDSx\Ldap\Exception\BindException;
use FreeDSx\Ldap\Exception\OperationException;
use FreeDSx\Ldap\LdapClient;
use FreeDSx\Ldap\Operation\ResultCode;
use FreeDSx\Ldap\Operations;
use FreeDSx\Ldap\Search\Filters;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

/**
 * Checks a password against Active Directory (config/ldap.php).
 *
 * Two steps. The service account searches for the person, because the directory knows them by a distinguished name
 * nobody types. Then a second connection binds as that person with the password that was typed. Binding is the
 * check: no hash is read, compared or kept.
 *
 * The directory is spoken to with freedsx/ldap, a client written in PHP, so the PHP ldap extension is not needed.
 *
 * Refusals that are easy to get wrong:
 * - An empty password binds successfully (LDAP calls it an unauthenticated bind): any username with no password
 *   would be let in as that person. It is refused before anything reaches the directory.
 * - A directory that cannot be asked is not a wrong password: it throws DirectoryUnavailable. So does a service
 *   account the directory refuses: that is a wrong setting here, not a wrong password of whoever signs in.
 * - Two entries for one username are refused, not resolved by taking the first: that would sign somebody in as an
 *   account that is not theirs. An account the directory has disabled is refused as well.
 * - No password travels unencrypted: ldaps:// is TLS from the start, and on ldap:// StartTLS comes before any bind.
 *
 * For User Management it also looks people up, as the service account, never with anybody's password: search() for
 * "Find employee" (with LDAP chosen), lookup() to check a directory username before an account is saved with it.
 */
class LdapAuthenticator
{
    /** Bit 2 of userAccountControl: ACCOUNTDISABLE. */
    private const ACCOUNT_DISABLED = 0x0002;

    /** Whether the directory is set up here (User Management offers LDAP only then). */
    public function isConfigured(): bool
    {
        return filled(config('ldap.url')) && filled(config('ldap.bind_dn'));
    }

    /**
     * The person, when the password is theirs; null when it is not.
     *
     * @throws DirectoryUnavailable when the directory cannot be asked
     */
    public function authenticate(string $username, #[SensitiveParameter] string $password): ?LdapIdentity
    {
        // Never sent: the directory would answer "yes" to an empty password for any username that exists.
        if (trim($username) === '' || $password === '') {
            return null;
        }

        if (! $this->isConfigured()) {
            throw new DirectoryUnavailable('The directory is not configured (LDAP_URL, LDAP_BIND_DN).');
        }

        $entry = $this->find($username);

        // The same answer as a wrong password: "that account is disabled" would tell somebody who has not
        // proved who they are that the account exists.
        if ($entry === null || $this->isDisabled($entry)) {
            return null;
        }

        $dn = $entry->getDn()->toString();
        if (! $this->accepts($dn, $password)) {
            return null;
        }

        return new LdapIdentity(
            $this->attribute($entry, (string) config('ldap.attributes.username', 'samaccountname')) ?? $username,
            $dn,
            $this->attribute($entry, (string) config('ldap.attributes.mail', 'mail')),
            $this->attribute($entry, (string) config('ldap.attributes.name', 'displayname')),
        );
    }

    /**
     * The people of the directory whose name, username or address holds $term (config ldap.people_search_filter: people
     * only, disabled accounts left out), for "Find employee" of User Management: at most $limit of them, sorted by name,
     * and whether the directory has more. Looked up as the service account.
     *
     * @return array{people: array<int, array{username: string, name: ?string, email: ?string}>, more: bool}
     *
     * @throws DirectoryUnavailable when the directory cannot be asked (or is not set up)
     */
    public function search(string $term, int $limit = 8): array
    {
        $term = trim($term);
        if ($term === '') {
            return ['people' => [], 'more' => false];
        }
        if (! $this->isConfigured()) {
            throw new DirectoryUnavailable('The directory is not configured (LDAP_URL, LDAP_BIND_DN).');
        }
        $limit = max(1, $limit);
        $client = $this->asService();

        try {
            $filter = str_replace('{term}', $this->escape($term), (string) config('ldap.people_search_filter'));

            // One page of one more than are shown: whether there are more is known, and the directory does not answer
            // "size limit exceeded" (which hands over none of them) when many people match.
            $found = $client->search(
                Operations::search(Filters::raw($filter), ...$this->attributes(['userprincipalname']))
                    ->base((string) config('ldap.user_search_base')),
                Controls::paging($limit + 1)
            )->toArray();
        } catch (Exception $e) {
            throw $this->unavailable('the search for people failed', $e, 'The directory could not be searched.');
        } finally {
            $this->close($client);
        }

        $people = [];
        foreach ($found as $entry) {
            $person = $this->person($entry);
            // (The filter leaves out disabled accounts; a filter set otherwise in .env may not.)
            if ($person !== null && ! $this->isDisabled($entry)) {
                $people[] = $person;
            }
        }
        usort($people, fn (array $a, array $b) => strnatcasecmp((string) ($a['name'] ?? $a['username']), (string) ($b['name'] ?? $b['username'])));

        return ['people' => array_slice($people, 0, $limit), 'more' => count($found) > $limit];
    }

    /**
     * The account of a directory username as the directory has it, looked up as the service account: null when there is
     * none (or the username names more than one entry). A disabled account is told as such.
     *
     * @return array{username: string, name: ?string, email: ?string, disabled: bool}|null
     *
     * @throws DirectoryUnavailable when the directory cannot be asked (or is not set up)
     */
    public function lookup(string $username): ?array
    {
        if (trim($username) === '') {
            return null;
        }
        if (! $this->isConfigured()) {
            throw new DirectoryUnavailable('The directory is not configured (LDAP_URL, LDAP_BIND_DN).');
        }

        $entry = $this->find($username);
        $person = $entry === null ? null : $this->person($entry);

        return $person === null ? null : $person + ['disabled' => $this->isDisabled($entry)];
    }

    /**
     * The entry of this username, looked up as the service account; null when there is none (or more than one).
     *
     * @throws DirectoryUnavailable
     */
    private function find(string $username): ?Entry
    {
        $client = $this->asService();

        $found = [];
        $tooMany = false;

        try {
            $filter = str_replace('{username}', $this->escape($username), (string) config('ldap.user_search_filter'));

            // Two entries at most are asked for: one is the answer, a second says the filter does not name one person.
            $found = $client->search(
                Operations::search(Filters::raw($filter), ...$this->attributes())
                    ->base((string) config('ldap.user_search_base'))
                    ->sizeLimit(2)
            )->toArray();
        } catch (OperationException $e) {
            // With more than were asked for, the directory says so instead of handing over the first ones.
            $tooMany = $e->getCode() === ResultCode::SIZE_LIMIT_EXCEEDED;
            if (! $tooMany) {
                throw $this->unavailable('the search failed', $e, 'The directory could not be searched.');
            }
        } catch (Exception $e) {
            throw $this->unavailable('the search failed', $e, 'The directory could not be searched.');
        } finally {
            $this->close($client);
        }

        if ($tooMany || count($found) > 1) {
            Log::warning('LDAP: a username matched more than one entry; nobody is signed in on that.', [
                'filter' => config('ldap.user_search_filter'),
            ]);

            return null;
        }

        return $found[0] ?? null;
    }

    /**
     * Whether the directory takes $password for the entry $dn: a bind as that person. On a connection of its own,
     * so the one of the service account is never left bound as somebody else.
     *
     * @throws DirectoryUnavailable
     */
    private function accepts(string $dn, #[SensitiveParameter] string $password): bool
    {
        $client = $this->open();

        try {
            $client->bind($dn, $password);

            return true;
        } catch (BindException $e) {
            // What the directory answers to a wrong password, and to an account that is locked or has expired.
            if ($e->getCode() === ResultCode::INVALID_CREDENTIALS) {
                return false;
            }

            throw $this->unavailable('a bind was refused for another reason than the password', $e, 'The directory could not check the password.');
        } catch (Exception $e) {
            throw $this->unavailable('a password could not be checked', $e, 'The directory could not be reached.');
        } finally {
            $this->close($client);
        }
    }

    /**
     * A client bound as the service account, the one that looks people up.
     *
     * @throws DirectoryUnavailable
     */
    private function asService(): LdapClient
    {
        $client = $this->open();

        try {
            $client->bind((string) config('ldap.bind_dn'), (string) config('ldap.bind_password'));
        } catch (Exception $e) {
            $this->close($client);

            throw $this->unavailable('could not bind as the service account', $e, 'The directory could not be reached.');
        }

        return $client;
    }

    /**
     * An entry as the form of a user takes it: the username, the name, and the address (the mail attribute, else the
     * userPrincipalName, which Microsoft signs in with). Null for an entry without a username.
     *
     * @return array{username: string, name: ?string, email: ?string}|null
     */
    private function person(Entry $entry): ?array
    {
        $username = $this->attribute($entry, (string) config('ldap.attributes.username', 'samaccountname'));
        if ($username === null) {
            return null;
        }

        return [
            'username' => $username,
            'name' => $this->attribute($entry, (string) config('ldap.attributes.name', 'displayname')),
            'email' => $this->attribute($entry, (string) config('ldap.attributes.mail', 'mail')) ?? $this->attribute($entry, 'userprincipalname'),
        ];
    }

    /**
     * A client for the directory, on an encrypted connection. (The connection itself is made by the first request.)
     *
     * @throws DirectoryUnavailable
     */
    private function open(): LdapClient
    {
        $url = parse_url((string) config('ldap.url')) ?: [];
        $scheme = strtolower((string) ($url['scheme'] ?? ''));
        if (! in_array($scheme, ['ldap', 'ldaps'], true) || blank($url['host'] ?? null)) {
            throw new DirectoryUnavailable('The address of the directory (LDAP_URL) could not be used: it reads ldaps://host or ldap://host.');
        }

        $timeout = max(1, (int) config('ldap.timeout', 5));
        $client = $this->client([
            'servers' => [$url['host']],
            'port' => (int) ($url['port'] ?? ($scheme === 'ldaps' ? 636 : 389)),
            'use_ssl' => $scheme === 'ldaps',
            'ssl_validate_cert' => (bool) config('ldap.tls_verify'),
            'ssl_ca_cert' => config('ldap.ca_cert') ?: null,
            'timeout_connect' => $timeout,
            'timeout_read' => $timeout,
        ]);

        // ldap:// starts in the clear, and a bind carries a password: the connection is encrypted first.
        if ($scheme === 'ldap') {
            try {
                $client->startTls();
            } catch (Exception $e) {
                $this->close($client);

                throw $this->unavailable('the connection could not be encrypted (StartTLS)', $e, 'The directory could not be reached.');
            }
        }

        return $client;
    }

    /**
     * The client itself. (A method of its own, so that a test can put a stand-in in the place of the directory.)
     *
     * @throws DirectoryUnavailable
     */
    protected function client(array $options): LdapClient
    {
        // Without it this would end in an error page; the login form has an answer for a directory that is not there.
        if (! class_exists(LdapClient::class)) {
            throw new DirectoryUnavailable('The LDAP client (freedsx/ldap) is not installed: run "composer install".');
        }

        return new LdapClient($options);
    }

    private function close(LdapClient $client): void
    {
        try {
            if ($client->isConnected()) {
                $client->unbind();
            }
        } catch (Exception) {
            // It was being closed anyway.
        }
    }

    /**
     * What went wrong goes to the log only (it can name the domain controller and the service account); the caller
     * gets a sentence. The exception itself is not passed on: its trace holds what was sent.
     */
    private function unavailable(string $what, Exception $e, string $said): DirectoryUnavailable
    {
        $reasons = [];
        for ($at = $e; $at !== null; $at = $at->getPrevious()) {
            $reasons[] = $at->getMessage();
        }
        Log::error("LDAP: {$what}: " . implode(' <- ', array_unique(array_filter($reasons))));

        return new DirectoryUnavailable($said);
    }

    /** $value as it stands in a search filter (RFC 4515): what a filter reads as a sign of its own is written as its code. */
    private function escape(string $value): string
    {
        return strtr($value, ['\\' => '\5c', '*' => '\2a', '(' => '\28', ')' => '\29', "\x00" => '\00']);
    }

    /** The attributes that are asked for: those config/ldap.php names, the one that says an account is disabled, and $more. */
    private function attributes(array $more = []): array
    {
        return array_values(array_unique(array_map('strtolower', array_merge(
            array_filter((array) config('ldap.attributes'), 'is_string'),
            ['useraccountcontrol'],
            $more,
        ))));
    }

    private function isDisabled(Entry $entry): bool
    {
        $control = $this->attribute($entry, 'useraccountcontrol');

        return $control !== null && ((int) $control & self::ACCOUNT_DISABLED) !== 0;
    }

    private function attribute(Entry $entry, string $name): ?string
    {
        $value = $entry->get($name)?->firstValue();

        return is_string($value) && $value !== '' ? $value : null;
    }
}
