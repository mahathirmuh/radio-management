<?php

namespace Modules\Api\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A token of the API (page "API Tokens"). Sanctum's token, kept as a SHA-256 hash, with this application's rules:
 *
 *  - it reads (GET), or reads and writes: the abilities READ and WRITE;
 *  - it always ends (expires_at), and is refused once its account is inactive or deleted;
 *  - where it was last used from is kept next to when (last_used_ip).
 */
class ApiToken extends PersonalAccessToken
{
    /** Whether the table is there is asked at most once a minute: the code is live before its migration runs. */
    public const READY_CACHE_KEY = 'personal-access-tokens-table';

    public const READ = 'read';

    public const WRITE = 'write';

    /** How long a new token may live, in days: what the form offers. */
    public const LIFETIMES = [30, 90, 180, 365];

    protected $table = 'personal_access_tokens';

    /** Whether the table of the tokens exists (its migration has run). Until then the API answers 503. */
    public static function ready(): bool
    {
        return (bool) Cache::remember(self::READY_CACHE_KEY, 60, fn () => Schema::hasTable('personal_access_tokens'));
    }

    public function writes(): bool
    {
        return $this->can(self::WRITE);
    }

    public function expired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** "Read only" or "Read & write", as the page shows it. */
    public function accessLabel(): string
    {
        return $this->writes() ? 'Read & write' : 'Read only';
    }
}
