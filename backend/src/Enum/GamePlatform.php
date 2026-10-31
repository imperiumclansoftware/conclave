<?php

namespace App\Enum;

/**
 * Platforms a member plays on: the current ones first, then the retro ones (older consoles and
 * handhelds), by maker.
 */
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

    // Retro: Nintendo
    case WiiU = 'wii_u';
    case Wii = 'wii';
    case GameCube = 'gamecube';
    case Nintendo64 = 'n64';
    case Snes = 'snes';
    case Nes = 'nes';
    case Nintendo3ds = '3ds';
    case NintendoDs = 'ds';
    case GameBoyAdvance = 'gba';
    case GameBoyColor = 'gbc';
    case GameBoy = 'gb';
    // Retro: Sony
    case Ps3 = 'ps3';
    case Ps2 = 'ps2';
    case Ps1 = 'ps1';
    case PsVita = 'ps_vita';
    case Psp = 'psp';
    // Retro: Microsoft
    case Xbox360 = 'xbox_360';
    case Xbox = 'xbox';
    // Retro: Sega and others
    case Dreamcast = 'dreamcast';
    case Saturn = 'saturn';
    case MegaDrive = 'mega_drive';
    case MasterSystem = 'master_system';
    case GameGear = 'game_gear';
    case NeoGeo = 'neo_geo';
    case Atari = 'atari';

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
            self::WiiU => 'Wii U',
            self::Wii => 'Wii',
            self::GameCube => 'GameCube',
            self::Nintendo64 => 'Nintendo 64',
            self::Snes => 'Super Nintendo',
            self::Nes => 'NES',
            self::Nintendo3ds => 'Nintendo 3DS',
            self::NintendoDs => 'Nintendo DS',
            self::GameBoyAdvance => 'Game Boy Advance',
            self::GameBoyColor => 'Game Boy Color',
            self::GameBoy => 'Game Boy',
            self::Ps3 => 'PS3',
            self::Ps2 => 'PS2',
            self::Ps1 => 'PlayStation',
            self::PsVita => 'PS Vita',
            self::Psp => 'PSP',
            self::Xbox360 => 'Xbox 360',
            self::Xbox => 'Xbox',
            self::Dreamcast => 'Dreamcast',
            self::Saturn => 'Saturn',
            self::MegaDrive => 'Mega Drive',
            self::MasterSystem => 'Master System',
            self::GameGear => 'Game Gear',
            self::NeoGeo => 'Neo Geo',
            self::Atari => 'Atari',
        };
    }

    /** Older consoles and handhelds, no longer sold */
    public function isRetro(): bool
    {
        return !\in_array($this, [self::Pc, self::Ps5, self::Ps4, self::XboxSeries, self::XboxOne, self::Switch2, self::Switch, self::Mobile], true);
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
