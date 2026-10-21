<?php

namespace Modules\RadioManagement\Enums;

/** Physical condition of the radio ("Kondisi"). */
enum Condition: string
{
    case Layak = 'layak';
    case PerluService = 'perlu_service';
    case Rusak = 'rusak';
    case BelumDicek = 'belum_dicek';

    /** The name as the register keeps it and the files and the API show it (Indonesian). */
    public function label(): string
    {
        return match ($this) {
            self::Layak => 'Layak',
            self::PerluService => 'Perlu Service',
            self::Rusak => 'Rusak',
            self::BelumDicek => 'Belum dicek',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Layak => 'green',
            self::PerluService => 'yellow',
            self::Rusak => 'red',
            self::BelumDicek => 'secondary',
        };
    }

    /** The name in the language of the page (or of $locale), for what is shown. */
    public function display(?string $locale = null): string
    {
        return __('RadioManagement::common.conditions.' . $this->value, [], $locale);
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
