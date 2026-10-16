<?php

namespace App\Entity;

use App\Enum\EventType;
use App\Enum\FilmPlatform;
use App\Enum\GamePlatform;
use App\Enum\StreamPlatform;
use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Event of a branch (game session, watch party…): seen by the whole clan, created and joined by
 * the members of the branch. Its type tells which specific fields it has.
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Index(name: 'event_starts_at', columns: ['starts_at'])]
class Event
{
    /** How long the chat room stays open after the start of an event without an end */
    private const CHAT_OPEN_WITHOUT_END = '+4 hours';

    public const TITLE_MAX_LENGTH = 120;
    public const DESCRIPTION_MAX_LENGTH = 5000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: self::TITLE_MAX_LENGTH)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** With their time zone: the same instant for every member */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    /** null: no limit */
    #[ORM\Column(nullable: true)]
    private ?int $maxParticipants = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Game $game = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Film $film = null;

    #[ORM\Column(length: 20, nullable: true, enumType: GamePlatform::class)]
    private ?GamePlatform $gamePlatform = null;

    #[ORM\Column(length: 20, nullable: true, enumType: FilmPlatform::class)]
    private ?FilmPlatform $filmPlatform = null;

    #[ORM\Column(length: 20, nullable: true, enumType: StreamPlatform::class)]
    private ?StreamPlatform $streamPlatform = null;

    /** Video call, stream or game jam page */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;

    /** Theme of a game jam */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $theme = null;

    /** @var Collection<int, EventParticipant> */
    #[ORM\OneToMany(targetEntity: EventParticipant::class, mappedBy: 'event')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $participants;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Branch $branch,
        #[ORM\Column(length: 30, enumType: EventType::class)]
        private EventType $type,
        /** Null once the member has left the clan */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $createdBy,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBranch(): Branch
    {
        return $this->branch;
    }

    public function getType(): EventType
    {
        return $this->type;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    /** Over: after its end, or after its start when it has no end */
    public function isOver(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return ($this->endsAt ?? $this->startsAt) < $now;
    }

    /**
     * The chat room accepts messages until the end of the event; without an end, for a few hours
     * after its start.
     */
    public function getChatClosesAt(): \DateTimeImmutable
    {
        return $this->endsAt ?? $this->startsAt->modify(self::CHAT_OPEN_WITHOUT_END);
    }

    public function isChatOpen(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $now < $this->getChatClosesAt();
    }

    public function getMaxParticipants(): ?int
    {
        return $this->maxParticipants;
    }

    public function isFull(): bool
    {
        return null !== $this->maxParticipants && $this->participants->count() >= $this->maxParticipants;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function getFilm(): ?Film
    {
        return $this->film;
    }

    public function getGamePlatform(): ?GamePlatform
    {
        return $this->gamePlatform;
    }

    public function getFilmPlatform(): ?FilmPlatform
    {
        return $this->filmPlatform;
    }

    public function getStreamPlatform(): ?StreamPlatform
    {
        return $this->streamPlatform;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    /** @return Collection<int, EventParticipant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function participantOf(User $user): ?EventParticipant
    {
        foreach ($this->participants as $participant) {
            if ($participant->getUser() === $user) {
                return $participant;
            }
        }

        return null;
    }

    public function describe(string $title, ?string $description, \DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt, ?int $maxParticipants): void
    {
        if (null !== $this->id) {
            $this->updatedAt = new \DateTimeImmutable();
        }
        $this->title = $title;
        $this->description = $description;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->maxParticipants = $maxParticipants;
    }

    /** Specific fields: only those of the type are kept */
    public function specify(?Game $game, ?GamePlatform $gamePlatform, ?Film $film, ?FilmPlatform $filmPlatform, ?StreamPlatform $streamPlatform, ?string $link, ?string $theme): void
    {
        $fields = $this->type->fields();
        $this->game = isset($fields['game']) ? $game : null;
        $this->gamePlatform = isset($fields['gamePlatform']) ? $gamePlatform : null;
        $this->film = isset($fields['film']) ? $film : null;
        $this->filmPlatform = isset($fields['filmPlatform']) ? $filmPlatform : null;
        $this->streamPlatform = isset($fields['streamPlatform']) ? $streamPlatform : null;
        $this->link = isset($fields['link']) ? $link : null;
        $this->theme = isset($fields['theme']) ? $theme : null;
    }
}
