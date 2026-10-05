<?php

namespace Modules\RadioManagement\Enums;

/** Where the physical radio is right now ("Posisi"). */
enum Placement: string
{
    case DiUser = 'di_user';
    case StokIt = 'stok_it';
    case Service = 'service';
    case Hilang = 'hilang';

    public function label(): string
    {
        return match ($this) {
            self::DiUser => 'Di User',
            self::StokIt => 'Stok IT',
            self::Service => 'Service',
            self::Hilang => 'Hilang',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DiUser => 'blue',
            self::StokIt => 'green',
            self::Service => 'orange',
            self::Hilang => 'red',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }
}
