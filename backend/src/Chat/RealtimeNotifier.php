<?php

namespace App\Chat;

use App\Api\ApiPresenter;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\User;
use App\Entity\VideoCall;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Pushes real-time events to connected clients, on each user's private topic. The data is
 * already persisted when this runs, so a failure only delays delivery until clients reload it.
 */
final readonly class RealtimeNotifier
{
    public function __construct(
        private HubInterface $hub,
        private UserTopic $userTopic,
        private ApiPresenter $presenter,
        private LoggerInterface $logger,
    ) {
    }

    public function messageSent(Message $message): void
    {
        $this->publish(
            $message->getConversation()->getParticipants()->map(static fn (ConversationParticipant $p): User => $p->getUser()),
            ['type' => 'message', 'message' => $this->presenter->message($message)],
        );
    }

    /** Tells the clan that a member's presence (in the app or on Discord) changed. */
    public function presenceChanged(User $user): void
    {
        $this->publish($user->getClan()->getMembers(), ['type' => 'presence', 'presence' => $this->presenter->presence($user)]);
    }

    /** A member's public profile changed (name, avatar…). */
    public function memberUpdated(User $user): void
    {
        $this->publish($user->getClan()->getMembers(), ['type' => 'member', 'member' => $this->presenter->user($user)]);
    }

    /** A member joined the clan (sign-up approved, or added by a leader). */
    public function memberJoined(User $user): void
    {
        $this->publish($user->getClan()->getMembers(), ['type' => 'member-joined', 'member' => $this->presenter->user($user)]);
    }

    /**
     * A member left the clan.
     *
     * @param iterable<User> $remaining
     */
    public function memberLeft(User $user, iterable $remaining): void
    {
        $this->publish($remaining, ['type' => 'member-left', 'userId' => $user->getId()]);
    }

    /**
     * The list of sign-ups waiting for approval changed (leaders refresh their badge).
     *
     * @param iterable<User> $leaders
     */
    public function signupsChanged(iterable $leaders): void
    {
        $this->publish($leaders, ['type' => 'signups']);
    }

    /** The member's branches changed (request approved or refused): rights may have changed too. */
    public function branchesChanged(User $user): void
    {
        $this->publish([$user], ['type' => 'branches']);
    }

    /** The favourite links of one of the member's branches changed. */
    public function branchLinksChanged(User $user): void
    {
        $this->publish([$user], ['type' => 'links']);
    }

    /** A video call was created or its participants joined/left the channel. */
    public function callUpdated(VideoCall $call): void
    {
        $this->publish($call->getParticipants(), ['type' => 'call', 'call' => $this->presenter->call($call)]);
    }

    public function callEnded(VideoCall $call): void
    {
        $this->publish($call->getParticipants(), ['type' => 'call-ended', 'callId' => $call->getId()]);
    }

    /**
     * @param iterable<User>       $users
     * @param array<string, mixed> $payload
     */
    private function publish(iterable $users, array $payload): void
    {
        $topics = [];
        foreach ($users as $user) {
            $topics[] = $this->userTopic->for($user);
        }
        if ([] === $topics) {
            return;
        }

        try {
            $this->hub->publish(new Update($topics, json_encode($payload, \JSON_THROW_ON_ERROR), private: true));
        } catch (\Throwable $e) {
            $this->logger->error('Unable to publish a "{type}" event to Mercure: {error}', [
                'type' => $payload['type'],
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
