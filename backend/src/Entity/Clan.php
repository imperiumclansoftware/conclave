<?php

namespace App\Entity;

use App\Repository\ClanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClanRepository::class)]
class Clan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $discordGuildId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $discordGuildName = null;

    /** Category holding the temporary video call channels */
    #[ORM\Column(length: 25, nullable: true)]
    private ?string $discordCallCategoryId = null;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'clan')]
    #[ORM\OrderBy(['displayName' => 'ASC'])]
    private Collection $members;

    public function __construct(
        #[ORM\Column(length: 100)]
        private string $name,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->members = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, User> active members (pending sign-ups excluded) */
    public function getMembers(): Collection
    {
        return $this->members->filter(static fn (User $user): bool => $user->isActive());
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getDiscordGuildId(): ?string
    {
        return $this->discordGuildId;
    }

    public function getDiscordGuildName(): ?string
    {
        return $this->discordGuildName;
    }

    public function connectDiscordGuild(string $id, string $name): void
    {
        $this->discordGuildId = $id;
        $this->discordGuildName = $name;
        $this->discordCallCategoryId = null;
    }

    public function disconnectDiscordGuild(): void
    {
        $this->discordGuildId = $this->discordGuildName = $this->discordCallCategoryId = null;
    }

    public function getDiscordCallCategoryId(): ?string
    {
        return $this->discordCallCategoryId;
    }

    public function setDiscordCallCategoryId(?string $id): void
    {
        $this->discordCallCategoryId = $id;
    }
}
