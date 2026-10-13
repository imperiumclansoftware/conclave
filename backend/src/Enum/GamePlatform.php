<?php

namespace App\Enum;

enum GamePlatform: string
{
    case Pc = 'pc';
    case Ps5 = 'ps5';
    case Ps4 = 'ps4';
    case XboxSeries = 'xbox_series';
    case XboxOne = 'xbox_one';
    case Switch2 = 'switch_2';
    case Switch = 'switch';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::Pc => 'PC',
            self::Ps5 => 'PS5',
            self::Ps4 => 'PS4',
            self::XboxSeries => 'Xbox Series X|S',
            self::XboxOne => 'Xbox One',
            self::Switch2 => 'Switch 2',
            self::Switch => 'Switch',
            self::Mobile => 'Mobile',
        };
    }

    /**
     * @param array<string> $values
     *
     * @return list<self> known platforms, in the canonical order
     */
    public static function fromValues(array $values): array
    {
        return array_values(array_filter(self::cases(), static fn (self $p): bool => \in_array($p->value, $values, true)));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
