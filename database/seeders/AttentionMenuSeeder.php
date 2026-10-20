<?php

namespace Database\Seeders;

/**
 * The sidebar entry "Perlu Perhatian" (section "Radio", right below Register Radio): the results of the register's checks
 * as items to fix, set aside or give to somebody. Where it goes and who gets it: RadioSectionMenuSeeder.
 *   php artisan db:seed --class=AttentionMenuSeeder
 */
class AttentionMenuSeeder extends RadioSectionMenuSeeder
{
    public const ROUTE = 'radio-management.attention.index';

    protected function route(): string
    {
        return self::ROUTE;
    }

    protected function title(): string
    {
        return 'Perlu Perhatian';
    }

    protected function icon(): string
    {
        return 'alert-triangle';
    }
}
