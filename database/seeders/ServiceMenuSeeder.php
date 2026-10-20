<?php

namespace Database\Seeders;

/**
 * The sidebar entry "Servis & Perbaikan" (section "Radio", below Register Radio): the radios that are away for repair.
 * Where it goes and who gets it: RadioSectionMenuSeeder.
 *   php artisan db:seed --class=ServiceMenuSeeder
 */
class ServiceMenuSeeder extends RadioSectionMenuSeeder
{
    public const ROUTE = 'radio-management.service.index';

    protected function route(): string
    {
        return self::ROUTE;
    }

    protected function title(): string
    {
        return 'Servis & Perbaikan';
    }

    protected function icon(): string
    {
        return 'tool';
    }
}
