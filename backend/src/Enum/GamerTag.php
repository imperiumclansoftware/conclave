<?php

namespace App\Enum;

/**
 * Gaming networks on which members can share their identifier.
 */
enum GamerTag: string
{
    case Psn = 'psn';
    case Xbox = 'xbox';
    case Steam = 'steam';
    case Nintendo = 'nintendo';
    case Epic = 'epic';
    case Battlenet = 'battlenet';

    public function label(): string
    {
        return match ($this) {
            self::Psn => 'PlayStation Network',
            self::Xbox => 'Xbox (Gamertag)',
            self::Steam => 'Steam (code ami)',
            self::Nintendo => 'Nintendo (code ami)',
            self::Epic => 'Epic Games',
            self::Battlenet => 'Battle.net (BattleTag)',
        };
    }
}
