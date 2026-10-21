<?php

namespace Modules\RadioManagement\Enums;

/** Where the physical radio is right now ("Posisi"). */
enum Placement: string
{
    case DiUser = 'di_user';
    case StokIt = 'stok_it';
    case Service = 'service';
    case Hilang = 'hilang';

    /** The name as the register keeps it and the files and the API show it (Indonesian). */
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

    /** The name in the language of the page (or of $locale), for what is shown. */
    public function display(?string $locale = null): string
    {
        return __('RadioManagement::common.placements.' . $this->value, [], $locale);
    }

    /** For the choices of a page: the names in the language of the page. */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->display(),
            'color' => $case->color(),
        ], self::cases());
    }
}
