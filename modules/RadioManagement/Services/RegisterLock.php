<?php

namespace Modules\RadioManagement\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * One writer at a time on the radio register.
 *
 * "Is this Radio ID / SN still free?" cannot be a unique index (empty and released IDs
 * repeat), so the check and the write that depends on it run under an application lock.
 */
class RegisterLock
{
    private const NAME = 'radio-register-write';

    /**
     * Runs the callback in a database transaction while holding the lock.
     *
     * @param int $seconds how long the lock may be held before it expires by itself, so a crashed
     *                     request cannot block the register. Longer than the write must take.
     * @param int $wait    how long to wait for another writer before giving up
     *
     * @throws LockTimeoutException when another write still holds the lock after $wait seconds
     */
    public function run(callable $callback, int $seconds = 10, int $wait = 5): mixed
    {
        return Cache::lock(self::NAME, $seconds)->block($wait, fn () => DB::transaction($callback));
    }

    /** Drops the lock whoever holds it (php artisan radio:unlock, after an import that was cut off). */
    public function forceRelease(): void
    {
        Cache::lock(self::NAME)->forceRelease();
    }
}
