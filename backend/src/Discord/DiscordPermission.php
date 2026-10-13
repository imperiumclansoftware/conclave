<?php

namespace App\Discord;

/**
 * Discord permission bits (https://discord.com/developers/docs/topics/permissions).
 */
final class DiscordPermission
{
    public const VIEW_CHANNEL = 1 << 10;
    public const MANAGE_CHANNELS = 1 << 4;
    public const CONNECT = 1 << 20;
    public const SPEAK = 1 << 21;
    public const STREAM = 1 << 9; // video and screen sharing
    public const USE_VAD = 1 << 25;

    /** What a call participant can do in the call channel. */
    public const CALL_PARTICIPANT = self::VIEW_CHANNEL | self::CONNECT | self::SPEAK | self::STREAM | self::USE_VAD;

    /** Permissions requested when the bot is added to a server. */
    public const BOT = self::MANAGE_CHANNELS | self::CALL_PARTICIPANT;
}
