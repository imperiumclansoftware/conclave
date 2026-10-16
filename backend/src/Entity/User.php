<?php

namespace App\Entity;

use App\Enum\AvatarSource;
use App\Enum\ClanRole;
use App\Enum\Faction;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['username'], message: 'Cet identifiant est déjà utilisé.')]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse e-mail est déjà utilisée.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Hashed; always set before the account is saved */
    #[ORM\Column]
    private string $password;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 10, enumType: UserStatus::class, options: ['default' => 'active'])]
    private UserStatus $status = UserStatus::Active;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    // ---- Profile ----

    /** File name of the uploaded avatar (see AvatarStorage) */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $avatarFile = null;

    /** SHA-256 of the Gravatar e-mail address (the address itself is not kept) */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $gravatarHash = null;

    /** Chosen avatar source; null: the first available one (upload, Gravatar, Discord) */
    #[ORM\Column(length: 10, nullable: true, enumType: AvatarSource::class)]
    private ?AvatarSource $avatarSource = null;

    #[ORM\Column(length: 280, nullable: true)]
    private ?string $bio = null;

    /** Chosen theme; null: follow the device's light/dark setting */
    #[ORM\Column(length: 10, nullable: true, enumType: Faction::class)]
    private ?Faction $faction = null;

    /** @var array<string, string> platform => gamer tag (see GamerTag) */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $gamerTags = [];

    // ---- Linked Discord account ----

    #[ORM\Column(length: 25, unique: true, nullable: true)]
    private ?string $discordId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $discordUsername = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $discordAvatar = null;

    // ---- Presence in the app (heartbeats) ----

    /** E-mail when an event is organised on one of the member's games */
    #[ORM\Column(options: ['default' => true])]
    private bool $gameEventEmails = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $appAway = false;

    // ---- Presence on Discord (reported by the gateway worker) ----

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $discordStatus = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discordActivity = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'members')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Clan $clan,
        #[ORM\Column(length: 30, unique: true)]
        private string $username,
        #[ORM\Column(length: 50)]
        private string $displayName,
        #[ORM\Column(enumType: ClanRole::class)]
        private ClanRole $clanRole,
    ) {
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

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getClanRole(): ClanRole
    {
        return $this->clanRole;
    }

    public function isLeader(): bool
    {
        return ClanRole::Leader === $this->clanRole;
    }

    public function belongsToSameClanAs(self $other): bool
    {
        return $this->clan->getId() === $other->getClan()->getId();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = null !== $email && '' !== trim($email) ? mb_strtolower(trim($email)) : null;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return UserStatus::Active === $this->status;
    }

    /** Account created by signing up: a leader must approve it. */
    public function markPending(): void
    {
        $this->status = UserStatus::Pending;
        $this->approvedAt = null;
    }

    public function approve(): void
    {
        $this->status = UserStatus::Active;
        $this->approvedAt = new \DateTimeImmutable();
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setClanRole(ClanRole $clanRole): void
    {
        $this->clanRole = $clanRole;
    }

    public function getAvatarFile(): ?string
    {
        return $this->avatarFile;
    }

    public function setAvatarFile(?string $avatarFile): void
    {
        $this->avatarFile = $avatarFile;
    }

    public function getGravatarHash(): ?string
    {
        return $this->gravatarHash;
    }

    public function setGravatarHash(?string $gravatarHash): void
    {
        $this->gravatarHash = $gravatarHash;
    }

    public function getAvatarSource(): ?AvatarSource
    {
        return $this->avatarSource;
    }

    public function setAvatarSource(?AvatarSource $avatarSource): void
    {
        $this->avatarSource = $avatarSource;
    }

    public function wantsGameEventEmails(): bool
    {
        return $this->gameEventEmails;
    }

    public function setGameEventEmails(bool $wanted): void
    {
        $this->gameEventEmails = $wanted;
    }

    public function getFaction(): ?Faction
    {
        return $this->faction;
    }

    public function setFaction(?Faction $faction): void
    {
        $this->faction = $faction;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }

    /** @return array<string, string> */
    public function getGamerTags(): array
    {
        return $this->gamerTags;
    }

    /** @param array<string, string> $gamerTags */
    public function setGamerTags(array $gamerTags): void
    {
        $this->gamerTags = $gamerTags;
    }

    public function getDiscordId(): ?string
    {
        return $this->discordId;
    }

    public function getDiscordUsername(): ?string
    {
        return $this->discordUsername;
    }

    public function getDiscordAvatarUrl(): ?string
    {
        return $this->discordId && $this->discordAvatar
            ? \sprintf('https://cdn.discordapp.com/avatars/%s/%s.png?size=64', $this->discordId, $this->discordAvatar)
            : null;
    }

    public function linkDiscord(string $id, string $username, ?string $avatar): void
    {
        $this->discordId = $id;
        $this->discordUsername = $username;
        $this->discordAvatar = $avatar;
    }

    public function unlinkDiscord(): void
    {
        $this->discordId = $this->discordUsername = $this->discordAvatar = null;
        $this->discordStatus = $this->discordActivity = null;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function isAppAway(): bool
    {
        return $this->appAway;
    }

    public function seen(bool $away, \DateTimeImmutable $at = new \DateTimeImmutable()): void
    {
        $this->lastSeenAt = $at;
        $this->appAway = $away;
    }

    public function leftApp(): void
    {
        $this->lastSeenAt = null;
        $this->appAway = false;
    }

    public function getDiscordStatus(): ?string
    {
        return $this->discordStatus;
    }

    public function getDiscordActivity(): ?string
    {
        return $this->discordActivity;
    }

    public function setDiscordPresence(?string $status, ?string $activity): void
    {
        $this->discordStatus = $status;
        $this->discordActivity = null !== $activity ? mb_substr($activity, 0, 255) : null;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER', $this->clanRole->securityRole()];
    }

    public function getPassword(): ?string
    {
        return $this->password ?? null;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }
}
