<?php

namespace App\Services\Directory;

use RuntimeException;

/**
 * The directory could not be asked: it is not configured, the PHP ldap extension is not loaded, or it does not
 * answer. Not the same as a wrong password, and never reported as one: that would send people to reset a password
 * that was right, and hide a directory that is down behind a login form that seems to work.
 */
class DirectoryUnavailable extends RuntimeException
{
}
