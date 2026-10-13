<?php

namespace App\Services\Directory;

use Illuminate\Support\Facades\Log;

/**
 * Checks a password against Active Directory (config/ldap.php).
 *
 * Two steps. The service account searches for the person, because the directory knows them by a distinguished name
 * nobody types. Then a second connection binds as that person with the password that was typed. Binding is the
 * check: no hash is read, compared or kept.
 *
 * Three refusals that are easy to get wrong:
 * - An empty password binds successfully (LDAP calls it an unauthenticated bind): any username with no password
 *   would be let in as that person. It is refused before anything reaches the directory.
 * - A directory that cannot be asked is not a wrong password: it throws DirectoryUnavailable.
 * - Two entries for one username are refused, not resolved by taking the first: that would sign somebody in as an
 *   account that is not theirs. An account the directory has disabled is refused as well.
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

    /** Whether it can be asked from this server at all: set up, and the PHP ldap extension loaded. */
    public function usable(): bool
    {
        return $this->isConfigured() && extension_loaded('ldap');
    }

    /**
     * The person, when the password is theirs; null when it is not.
     *
     * @throws DirectoryUnavailable when the directory cannot be asked
     */
    public function authenticate(string $username, string $password): ?LdapIdentity
    {
        // Never sent: the directory would answer "yes" to an empty password for any username that exists.
        if (trim($username) === '' || $password === '') {
            return null;
        }

        if (! $this->isConfigured()) {
            throw new DirectoryUnavailable('The directory is not configured (LDAP_URL, LDAP_BIND_DN).');
        }

        $connection = $this->connect();

        try {
            $entry = $this->find($connection, $username);

            // The same answer as a wrong password: "that account is disabled" would tell somebody who has not
            // proved who they are that the account exists.
            if ($entry === null || $this->isDisabled($entry)) {
                return null;
            }

            // A connection of its own, so a failed attempt cannot leave the one of the service account bound as
            // somebody else.
            $asUser = $this->connect();

            try {
                if (! @ldap_bind($asUser, $entry['dn'], $password)) {
                    return null;
                }
            } finally {
                @ldap_unbind($asUser);
            }

            return new LdapIdentity(
                $this->attribute($entry, (string) config('ldap.attributes.username', 'samaccountname')) ?? $username,
                (string) $entry['dn'],
                $this->attribute($entry, (string) config('ldap.attributes.mail', 'mail')),
                $this->attribute($entry, (string) config('ldap.attributes.name', 'displayname')),
            );
        } finally {
            @ldap_unbind($connection);
        }
    }

    /**
     * A connection, bound as the service account.
     *
     * @return \LDAP\Connection
     *
     * @throws DirectoryUnavailable
     */
    private function connect()
    {
        if (! extension_loaded('ldap')) {
            throw new DirectoryUnavailable('The PHP ldap extension is not loaded on this server.');
        }

        // Set for every connection, before one exists: on a connection that is already there it is ignored, which
        // shows as "Can't contact LDAP server" against a port that is open.
        ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, config('ldap.tls_verify') ? LDAP_OPT_X_TLS_DEMAND : LDAP_OPT_X_TLS_NEVER);

        $connection = @ldap_connect((string) config('ldap.url'));
        if ($connection === false) {
            throw new DirectoryUnavailable('The address of the directory (LDAP_URL) could not be used.');
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        // A referral that is followed is a second connection that was never bound, and it hangs instead of failing.
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, max(1, (int) config('ldap.timeout', 5)));

        if (! @ldap_bind($connection, (string) config('ldap.bind_dn'), (string) config('ldap.bind_password'))) {
            // The reason goes to the log only: it can name the domain controller and the service account.
            Log::error('LDAP: could not bind as the service account: ' . ldap_error($connection));
            @ldap_unbind($connection);

            throw new DirectoryUnavailable('The directory could not be reached.');
        }

        return $connection;
    }

    /**
     * The entry of this username, or null when there is none (or more than one).
     *
     * @param \LDAP\Connection $connection
     *
     * @throws DirectoryUnavailable
     */
    private function find($connection, string $username): ?array
    {
        $filter = str_replace('{username}', ldap_escape($username, '', LDAP_ESCAPE_FILTER), (string) config('ldap.user_search_filter'));
        $attributes = array_values(array_unique(array_map('strtolower', array_merge(
            array_filter((array) config('ldap.attributes'), 'is_string'),
            ['useraccountcontrol'],
        ))));

        // Two entries at most are asked for: one is the answer, a second says the filter does not name one person.
        $search = @ldap_search($connection, (string) config('ldap.user_search_base'), $filter, $attributes, 0, 2);
        if ($search === false) {
            Log::error('LDAP: the search failed: ' . ldap_error($connection));

            throw new DirectoryUnavailable('The directory could not be searched.');
        }

        $entries = ldap_get_entries($connection, $search);
        $count = (int) ($entries['count'] ?? 0);

        if ($count > 1) {
            Log::warning('LDAP: a username matched more than one entry; nobody is signed in on that.', [
                'filter' => config('ldap.user_search_filter'),
            ]);
        }

        return $count === 1 ? $entries[0] : null;
    }

    private function isDisabled(array $entry): bool
    {
        $control = $this->attribute($entry, 'useraccountcontrol');

        return $control !== null && ((int) $control & self::ACCOUNT_DISABLED) !== 0;
    }

    private function attribute(array $entry, string $name): ?string
    {
        $value = $entry[strtolower($name)][0] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
