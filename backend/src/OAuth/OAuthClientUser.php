<?php

namespace App\OAuth;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security identity of a request authenticated by a machine client's access token: its roles are the
 * token's scopes (App\Enum\OAuthScope::role()), nothing else.
 */
final readonly class OAuthClientUser implements UserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $clientId,
        private array $roles,
    ) {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return 'oauth-client:'.$this->clientId;
    }
}
