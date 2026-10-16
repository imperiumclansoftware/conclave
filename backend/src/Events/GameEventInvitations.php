<?php

namespace App\Events;

use App\Chat\RealtimeNotifier;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserGame;
use App\Mail\AppMailer;
use App\Repository\UserGameRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tells the members who play the game of a new event, on its platform, that it is organised:
 * a browser notification (pushed in real time) and an e-mail if they want one. The e-mails leave
 * once the response is sent, so that the organiser does not wait for the mail server.
 */
final class GameEventInvitations
{
    /** @var list<array{Event, list<User>}> e-mails waiting for the end of the request */
    private array $pending = [];

    public function __construct(
        private readonly UserGameRepository $userGames,
        private readonly RealtimeNotifier $notifier,
        private readonly AppMailer $mailer,
    ) {
    }

    /**
     * Members to invite: in the branch of the event, active, owning its game on its platform,
     * except the organiser.
     *
     * @return list<User>
     */
    public function recipients(Event $event): array
    {
        $game = $event->getGame();
        $platform = $event->getGamePlatform();
        if (null === $game || null === $platform || $event->isOver()) {
            return [];
        }

        $owners = array_filter(
            $this->userGames->findForClan($event->getBranch()->getClan(), $game),
            static fn (UserGame $owned): bool => \in_array($platform, $owned->getPlatforms(), true),
        );
        $users = array_map(static fn (UserGame $owned): User => $owned->getUser(), $owners);

        return array_values(array_filter(
            $users,
            static fn (User $user): bool => $user->isActive() && $user !== $event->getCreatedBy() && $event->getBranch()->hasMember($user),
        ));
    }

    /** Notifies the players of a new event's game */
    public function invite(Event $event): void
    {
        $recipients = $this->recipients($event);
        if ([] === $recipients) {
            return;
        }

        $this->notifier->gameEventOrganised($event, $recipients);
        $byEmail = array_values(array_filter($recipients, static fn (User $user): bool => $user->wantsGameEventEmails()));
        if ([] !== $byEmail) {
            $this->pending[] = [$event, $byEmail];
        }
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    public function sendEmails(): void
    {
        $pending = $this->pending;
        $this->pending = [];
        foreach ($pending as [$event, $users]) {
            $this->mailer->gameEventOrganised($event, $users);
        }
    }
}
