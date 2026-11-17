<?php

namespace App\Entity;

use App\Repository\OAuthAccessTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Short-lived opaque access token of a machine client. Only its SHA-256 is stored: a database leak
 * gives no usable token.
 */
#[ORM\Entity(repositoryClass: OAuthAccessTokenRepository::class)]
#[ORM\Table(name: 'oauth_access_token')]
#[ORM\Index(name: 'oauth_access_token_expires', columns: ['expires_at'])]
class OAuthAccessToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Member who signed in (OpenID Connect), null for a machine token (client credentials) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * @param list<string> $scopes granted scopes (a subset of the client's)
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private OAuthClient $client,
        #[ORM\Column(length: 64, unique: true)]
        private string $tokenHash,
        #[ORM\Column(type: Types::JSON)]
        private array $scopes,
        #[ORM\Column]
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): OAuthClient
    {
        return $this->client;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function isValid(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $this->expiresAt > $now && $this->client->isActive();
    }
}
