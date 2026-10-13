<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Identifies a one-to-one conversation ("<smallest user id>-<largest user id>")
     * so that two members always share a single thread.
     */
    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $directKey = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Message $lastMessage = null;

    /** @var Collection<int, ConversationParticipant> */
    #[ORM\OneToMany(targetEntity: ConversationParticipant::class, mappedBy: 'conversation', cascade: ['persist'])]
    private Collection $participants;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->participants = new ArrayCollection();
    }

    public static function direct(User $a, User $b): self
    {
        $conversation = new self();
        $conversation->directKey = self::directKeyFor($a, $b);
        $conversation->addParticipant($a);
        $conversation->addParticipant($b);

        return $conversation;
    }

    public static function directKeyFor(User $a, User $b): string
    {
        $ids = [$a->getId(), $b->getId()];
        sort($ids);

        return implode('-', $ids);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastMessage(): ?Message
    {
        return $this->lastMessage;
    }

    public function setLastMessage(Message $message): void
    {
        $this->lastMessage = $message;
    }

    /** @return Collection<int, ConversationParticipant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(User $user): void
    {
        $this->participants->add(new ConversationParticipant($this, $user));
    }

    public function getParticipant(User $user): ?ConversationParticipant
    {
        return $this->participants->findFirst(
            static fn (int $key, ConversationParticipant $p): bool => $p->getUser()->getId() === $user->getId(),
        );
    }

    public function hasParticipant(User $user): bool
    {
        return null !== $this->getParticipant($user);
    }
}
