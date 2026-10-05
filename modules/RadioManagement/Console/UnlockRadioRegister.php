<?php

namespace Modules\RadioManagement\Console;

use Illuminate\Console\Command;
use Modules\RadioManagement\Services\RegisterLock;

/**
 * The way out when an import was cut off (server restart, time limit, Ctrl+C): its lock on
 * the register would otherwise keep every save answering "Register sedang sibuk" until it
 * expires by itself, up to 10 minutes later.
 */
class UnlockRadioRegister extends Command
{
    protected $signature = 'radio:unlock';

    protected $description = 'Release the register write lock left behind by an import that was cut off';

    public function handle(RegisterLock $lock): int
    {
        $lock->forceRelease();
        $this->info('Kunci register dilepas. Pakai hanya bila tidak ada impor yang masih berjalan.');

        return self::SUCCESS;
    }
}
