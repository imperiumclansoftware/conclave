<?php

namespace App\Enum;

/**
 * Coat of arms of a branch, drawn by the app in the colours of each faction.
 */
enum BranchCrest: string
{
    case Players = 'players';
    case Developers = 'developers';
    case Communicants = 'communicants';
    case Cinephiles = 'cinephiles';
}
