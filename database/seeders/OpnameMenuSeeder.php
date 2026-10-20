<?php

namespace Database\Seeders;

/**
 * The sidebar entry "Stock Opname" (section "Radio", below Servis & Perbaikan): counting the radios of a department on a
 * phone. Where it goes and who gets it: RadioSectionMenuSeeder.
 *   php artisan db:seed --class=OpnameMenuSeeder
 */
class OpnameMenuSeeder extends RadioSectionMenuSeeder
{
    public const ROUTE = 'radio-management.opname.index';

    protected function route(): string
    {
        return self::ROUTE;
    }

    protected function title(): string
    {
        return 'Stock Opname';
    }

    protected function icon(): string
    {
        return 'barcode';
    }
}
