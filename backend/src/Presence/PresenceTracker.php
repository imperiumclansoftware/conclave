<?php

namespace App\Presence;

use App\Chat\RealtimeNotifier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Records presence changes and broadcasts them to the clan.
 */
final readonly class PresenceTracker
{
    public function __construct(
        private EntityManagerInterface $em,
        private RealtimeNotifier $notifier,
    ) {
    }

    /** Heartbeat from an open tab (away: the tab is in the background). */
    public function heartbeat(User $user, bool $away): void
    {
        $before = AppStatus::of($user);
        $user->seen($away);
        $this->em->flush();

        // Only status changes are broadcast; clients expire stale statuses by themselves
        if (AppStatus::of($user) !== $before) {
            $this->notifier->presenceChanged($user);
        }
    }

    /** The last tab was closed, or the user logged out. */
    public function leave(User $user): void
    {
        $before = AppStatus::of($user);
        $user->leftApp();
        $this->em->flush();

        if (AppStatus::Offline !== $before) {
            $this->notifier->presenceChanged($user);
        }
    }

    /** Presence reported by Discord; returns whether it changed. */
    public function discordPresence(User $user, ?string $status, ?string $activity): bool
    {
        if ($user->getDiscordStatus() === $status && $user->getDiscordActivity() === $activity) {
            return false;
        }

        $user->setDiscordPresence($status, $activity);
        $this->em->flush();
        $this->notifier->presenceChanged($user);

        return true;
    }
}
