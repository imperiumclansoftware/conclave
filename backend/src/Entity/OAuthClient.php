<?php

namespace App\Entity;

use App\Repository\OAuthClientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Machine client of the API (OAuth2 "client credentials" only), e.g. the clan's public portal.
 * The secret is never stored: only its Argon2id hash (see App\OAuth\ClientCredentials).
 */
#[ORM\Entity(repositoryClass: OAuthClientRepository::class)]
#[ORM\Table(name: 'oauth_client')]
class OAuthClient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    /** Last token issued to this client */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    /** @var list<string> exact redirect URIs accepted for the OpenID Connect sign-in */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $redirectUris = [];

    /**
     * @param list<string> $scopes values of App\Enum\OAuthScope
     */
    public function __construct(
        #[ORM\Column(length: 64, unique: true)]
        private string $clientId,
        #[ORM\Column(length: 255)]
        private string $secretHash,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(type: Types::JSON)]
        private array $scopes,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /** @return list<string> */
    public function getRedirectUris(): array
    {
        return $this->redirectUris;
    }

    /** @param list<string> $uris */
    public function setRedirectUris(array $uris): void
    {
        $this->redirectUris = array_values(array_unique($uris));
    }

    /** Exact comparison (no prefix, no wildcard), as OAuth 2.1 requires */
    public function allowsRedirectUri(string $uri): bool
    {
        return \in_array($uri, $this->redirectUris, true);
    }

    /** @param list<string> $scopes */
    public function setScopes(array $scopes): void
    {
        $this->scopes = array_values(array_unique($scopes));
    }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return null === $this->revokedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(): void
    {
        $this->revokedAt ??= new \DateTimeImmutable();
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
    }
}
