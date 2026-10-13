<?php

namespace App\Chat;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ChatService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConversationRepository $conversations,
        private RealtimeNotifier $notifier,
    ) {
    }

    /**
     * Returns the one-to-one conversation between two members of the same clan, creating it if needed.
     */
    public function openDirect(User $me, User $other): Conversation
    {
        if ($me->getId() === $other->getId()) {
            throw new \InvalidArgumentException('Impossible de démarrer une conversation avec soi-même.');
        }
        if (!$me->belongsToSameClanAs($other) || !$other->isActive()) {
            throw new \InvalidArgumentException('Ce membre ne fait pas partie de votre clan.');
        }

        if ($conversation = $this->conversations->findDirect($me, $other)) {
            return $conversation;
        }

        $conversation = Conversation::direct($me, $other);
        $this->em->persist($conversation);
        $this->em->flush();

        return $conversation;
    }

    public function send(Conversation $conversation, User $sender, string $content): Message
    {
        $message = new Message($conversation, $sender, trim($content));
        $this->em->persist($message);
        $this->em->flush();

        $conversation->setLastMessage($message);
        // The sender has obviously read their own message
        $conversation->getParticipant($sender)?->markReadUpTo($message->getId());
        $this->em->flush();

        $this->notifier->messageSent($message);

        return $message;
    }

    public function markRead(Conversation $conversation, User $user, int $messageId): void
    {
        $lastId = $conversation->getLastMessage()?->getId() ?? 0;
        $conversation->getParticipant($user)?->markReadUpTo(min($messageId, $lastId));
        $this->em->flush();
    }
}
