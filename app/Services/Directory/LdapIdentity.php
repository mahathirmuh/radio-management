<?php

namespace App\Services\Directory;

/**
 * Who the directory says somebody is, once they have proved it. Only made after a bind as that person succeeded:
 * holding one means the password was checked, not merely that an account was found.
 */
final class LdapIdentity
{
    public function __construct(
        /** The name the directory knows the account by, e.g. "budi.santoso". */
        public readonly string $username,
        public readonly string $distinguishedName,
        public readonly ?string $email = null,
        public readonly ?string $name = null,
    ) {
    }
}
