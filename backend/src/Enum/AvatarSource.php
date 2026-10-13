<?php

namespace App\Enum;

/**
 * Where a member's avatar comes from.
 */
enum AvatarSource: string
{
    case Upload = 'upload';
    case Gravatar = 'gravatar';
    case Discord = 'discord';
    case Initials = 'initials';
}
