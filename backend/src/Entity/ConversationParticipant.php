<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['conversation_id', 'user_id'])]
class ConversationParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Id of the last message this participant has read; later messages are unread. */
    #[ORM\Column(nullable: true)]
    private ?int $lastReadMessageId = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'participants')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Conversation $conversation,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLastReadMessageId(): ?int
    {
        return $this->lastReadMessageId;
    }

    public function markReadUpTo(int $messageId): void
    {
        // Never move the read marker backwards
        $this->lastReadMessageId = max($this->lastReadMessageId ?? 0, $messageId);
    }
}
