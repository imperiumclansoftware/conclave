<?php

namespace App\Enum;

/**
 * Theme of the interface: the Assassin Brotherhood (light) or the Templar Order (dark).
 */
enum Faction: string
{
    case Assassin = 'assassin';
    case Templar = 'templar';
}
