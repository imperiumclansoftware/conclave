<?php

namespace App\Presence;

use App\Entity\User;

/**
 * Presence of a member in the app, derived from the heartbeats sent by open tabs.
 */
enum AppStatus: string
{
    case Online = 'online';
    case Away = 'away';
    case Offline = 'offline';

    /** A tab sends a heartbeat every 30 s: without one for this long, the member left. */
    public const TIMEOUT_SECONDS = 75;

    public static function of(User $user, \DateTimeImmutable $now = new \DateTimeImmutable()): self
    {
        $seen = $user->getLastSeenAt();
        if (null === $seen || $now->getTimestamp() - $seen->getTimestamp() > self::TIMEOUT_SECONDS) {
            return self::Offline;
        }

        return $user->isAppAway() ? self::Away : self::Online;
    }
}
