<?php

namespace App\Enum;

/** Where a watch party is watched. */
enum FilmPlatform: string
{
    case Netflix = 'netflix';
    case PrimeVideo = 'prime_video';
    case DisneyPlus = 'disney_plus';
    case CanalPlus = 'canal_plus';
    case AppleTv = 'apple_tv';
    case Max = 'max';
    case ParamountPlus = 'paramount_plus';
    case Crunchyroll = 'crunchyroll';
    case MediaServer = 'media_server';
    case Discord = 'discord';
    case Cinema = 'cinema';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Netflix => 'Netflix',
            self::PrimeVideo => 'Prime Video',
            self::DisneyPlus => 'Disney+',
            self::CanalPlus => 'Canal+',
            self::AppleTv => 'Apple TV+',
            self::Max => 'Max',
            self::ParamountPlus => 'Paramount+',
            self::Crunchyroll => 'Crunchyroll',
            self::MediaServer => 'Plex / Jellyfin',
            self::Discord => 'Partage d\'écran Discord',
            self::Cinema => 'Au cinéma',
            self::Other => 'Autre',
        };
    }
}
