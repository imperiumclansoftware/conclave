<?php

namespace App\Entity;

use App\Repository\VideoCallRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A video call between chosen clan members, held in a temporary private Discord voice channel.
 */
#[ORM\Entity(repositoryClass: VideoCallRepository::class)]
#[ORM\Index(columns: ['discord_channel_id'])]
class VideoCall
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'video_call_participant')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private Collection $participants;

    /** @var list<string> Discord ids of the users currently in the voice channel */
    #[ORM\Column(type: Types::JSON)]
    private array $connectedDiscordIds = [];

    /** Whether someone has joined the channel at least once */
    #[ORM\Column]
    private bool $started = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    /**
     * @param list<User> $participants including the creator
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Clan $clan,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $createdBy,
        #[ORM\Column(length: 100)]
        private string $title,
        #[ORM\Column(length: 25)]
        private string $discordChannelId,
        array $participants,
    ) {
        $this->participants = new ArrayCollection($participants);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClan(): Clan
    {
        return $this->clan;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDiscordChannelId(): string
    {
        return $this->discordChannelId;
    }

    /** Link opening the voice channel in Discord (browser or app). */
    public function getJoinUrl(): string
    {
        return \sprintf('https://discord.com/channels/%s/%s', $this->clan->getDiscordGuildId(), $this->discordChannelId);
    }

    /** @return Collection<int, User> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants->exists(static fn (int $k, User $p): bool => $p->getId() === $user->getId());
    }

    /** @return list<string> */
    public function getConnectedDiscordIds(): array
    {
        return $this->connectedDiscordIds;
    }

    /** @param list<string> $discordIds */
    public function setConnectedDiscordIds(array $discordIds): void
    {
        $this->connectedDiscordIds = array_values(array_unique(array_map(strval(...), $discordIds)));
        $this->started = $this->started || [] !== $this->connectedDiscordIds;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isEnded(): bool
    {
        return null !== $this->endedAt;
    }

    public function end(): void
    {
        $this->endedAt ??= new \DateTimeImmutable();
        $this->connectedDiscordIds = [];
    }
}
