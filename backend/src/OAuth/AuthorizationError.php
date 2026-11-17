<?php

namespace App\OAuth;

/**
 * OAuth / OpenID Connect error. With a redirect URI, it goes back to the client (error, state);
 * without, it is shown to the member (unknown client or redirect URI: never redirect there).
 */
final class AuthorizationError extends \RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly ?string $redirectUri = null,
        public readonly string $state = '',
    ) {
        parent::__construct($description);
    }

    public function redirectUrl(): ?string
    {
        if (null === $this->redirectUri) {
            return null;
        }
        $params = ['error' => $this->error, 'error_description' => $this->description];
        if ('' !== $this->state) {
            $params['state'] = $this->state;
        }

        return $this->redirectUri.(str_contains($this->redirectUri, '?') ? '&' : '?').http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }
}
