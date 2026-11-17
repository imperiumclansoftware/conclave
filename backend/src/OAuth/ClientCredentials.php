<?php

namespace App\OAuth;

use App\Entity\OAuthAccessToken;
use App\Entity\OAuthClient;
use App\Enum\OAuthScope;
use App\Repository\OAuthAccessTokenRepository;
use App\Repository\OAuthClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * OAuth2 "client credentials" for machine clients (ADR 0001): clients with an Argon2id-hashed secret,
 * short-lived opaque access tokens stored as SHA-256.
 */
class ClientCredentials
{
    /** Lifetime of an access token, in seconds */
    public const TOKEN_TTL = 300;

    private ?string $dummyHash = null;

    public function __construct(
        private readonly OAuthClientRepository $clients,
        private readonly OAuthAccessTokenRepository $tokens,
        private readonly EntityManagerInterface $em,
        private readonly PasswordHasherFactoryInterface $hashers,
    ) {
    }

    /**
     * @param list<OAuthScope> $scopes
     *
     * @return array{OAuthClient, string} the client and its secret, shown once and never stored
     */
    public function createClient(string $name, array $scopes): array
    {
        $secret = self::random(32);
        $client = new OAuthClient(
            'cc_'.self::random(16),
            $this->hasher()->hash($secret),
            $name,
            array_values(array_unique(array_map(static fn (OAuthScope $s): string => $s->value, $scopes))),
        );
        $this->em->persist($client);
        $this->em->flush();

        return [$client, $secret];
    }

    /**
     * The active client matching these credentials. The secret is checked in constant time, and an
     * unknown client costs the same hash verification, so timing tells nothing about which part is wrong.
     */
    public function authenticate(string $clientId, string $secret): ?OAuthClient
    {
        $client = '' === $clientId ? null : $this->clients->findOneBy(['clientId' => $clientId]);
        $valid = $this->hasher()->verify($client?->getSecretHash() ?? $this->dummyHash(), $secret);

        return $valid && null !== $client && $client->isActive() ? $client : null;
    }

    /**
     * @param list<string> $scopes already checked against the client's
     *
     * @return string the access token, given to the client only
     */
    public function issueToken(OAuthClient $client, array $scopes): string
    {
        $now = new \DateTimeImmutable();
        $this->tokens->purgeExpired($now->modify('-1 day'));

        $token = self::random(32);
        $this->em->persist(new OAuthAccessToken($client, self::hash($token), $scopes, $now->modify('+'.self::TOKEN_TTL.' seconds')));
        $client->markUsed();
        $this->em->flush();

        return $token;
    }

    /** The token if it exists, has not expired and its client is still active. */
    public function findValidToken(string $token): ?OAuthAccessToken
    {
        $found = '' === $token ? null : $this->tokens->findByHash(self::hash($token));

        return null !== $found && $found->isValid() ? $found : null;
    }

    /** Revokes the client and drops its tokens at once. */
    public function revoke(OAuthClient $client): void
    {
        $client->revoke();
        $this->tokens->deleteForClient($client);
        $this->em->flush();
    }

    private function hasher(): PasswordHasherInterface
    {
        return $this->hashers->getPasswordHasher('oauth_client_secret');
    }

    private function dummyHash(): string
    {
        return $this->dummyHash ??= $this->hasher()->hash(self::random(32));
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** URL-safe random string (base64url, no padding) */
    private static function random(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
