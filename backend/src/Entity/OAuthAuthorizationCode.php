<?php

namespace App\Entity;

use App\Repository\OAuthAuthorizationCodeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One-time OpenID Connect authorization code (60 s), bound to the client, the member, the redirect
 * URI and the PKCE challenge. Only its SHA-256 is stored.
 */
#[ORM\Entity(repositoryClass: OAuthAuthorizationCodeRepository::class)]
#[ORM\Table(name: 'oauth_authorization_code')]
#[ORM\Index(name: 'oauth_authorization_code_expires', columns: ['expires_at'])]
class OAuthAuthorizationCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        #[ORM\Column(length: 64, unique: true)]
        private string $codeHash,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private OAuthClient $client,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 500)]
        private string $redirectUri,
        #[ORM\Column(length: 128)]
        private string $codeChallenge,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $nonce,
        #[ORM\Column(type: Types::JSON)]
        private array $scopes,
        #[ORM\Column]
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public function getClient(): OAuthClient
    {
        return $this->client;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function getCodeChallenge(): string
    {
        return $this->codeChallenge;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function isUsable(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return null === $this->usedAt && $this->expiresAt > $now && $this->client->isActive();
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function markUsed(): void
    {
        $this->usedAt = new \DateTimeImmutable();
    }
}
