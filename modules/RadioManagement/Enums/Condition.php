<?php

namespace Modules\RadioManagement\Enums;

/** Physical condition of the radio ("Kondisi"). */
enum Condition: string
{
    case Layak = 'layak';
    case PerluService = 'perlu_service';
    case Rusak = 'rusak';
    case BelumDicek = 'belum_dicek';

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

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }
}
