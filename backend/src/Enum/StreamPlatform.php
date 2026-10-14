<?php

namespace App\Enum;

/** Where a stream is broadcast. */
enum StreamPlatform: string
{
    case Twitch = 'twitch';
    case YouTube = 'youtube';
    case Kick = 'kick';
    case Discord = 'discord';
    case TikTok = 'tiktok';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Twitch => 'Twitch',
            self::YouTube => 'YouTube',
            self::Kick => 'Kick',
            self::Discord => 'Discord',
            self::TikTok => 'TikTok',
            self::Other => 'Autre',
        };
    }
}
