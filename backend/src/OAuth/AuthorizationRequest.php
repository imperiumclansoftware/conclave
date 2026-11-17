<?php

namespace App\OAuth;

/**
 * An OpenID Connect authorization request, checked by GET /oauth/authorize then carried to the
 * consent page (signed with APP_SECRET, valid 10 minutes): no server-side state before consent.
 */
final readonly class AuthorizationRequest
{
    private const TTL = 600;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public string $clientId,
        public string $redirectUri,
        public array $scopes,
        public string $state,
        public ?string $nonce,
        public string $codeChallenge,
        public int $expiresAt,
    ) {
    }

    /** @param list<string> $scopes */
    public static function create(string $clientId, string $redirectUri, array $scopes, string $state, ?string $nonce, string $codeChallenge): self
    {
        return new self($clientId, $redirectUri, $scopes, $state, $nonce, $codeChallenge, time() + self::TTL);
    }

    public function seal(string $secret): string
    {
        $payload = OidcKeys::b64(json_encode([
            'c' => $this->clientId, 'r' => $this->redirectUri, 's' => $this->scopes, 'st' => $this->state,
            'n' => $this->nonce, 'cc' => $this->codeChallenge, 'e' => $this->expiresAt,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));

        return $payload.'.'.OidcKeys::b64(hash_hmac('sha256', $payload, self::key($secret), true));
    }

    /** null: tampered, malformed or expired */
    public static function open(string $sealed, string $secret): ?self
    {
        $parts = explode('.', $sealed);
        if (2 !== \count($parts)) {
            return null;
        }
        [$payload, $mac] = $parts;
        if (!hash_equals(OidcKeys::b64(hash_hmac('sha256', $payload, self::key($secret), true)), $mac)) {
            return null;
        }
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        if (!\is_array($data) || !\is_int($data['e'] ?? null) || $data['e'] < time()) {
            return null;
        }

        return new self(
            (string) ($data['c'] ?? ''), (string) ($data['r'] ?? ''), array_values(array_map('strval', (array) ($data['s'] ?? []))),
            (string) ($data['st'] ?? ''), isset($data['n']) ? (string) $data['n'] : null, (string) ($data['cc'] ?? ''), $data['e'],
        );
    }

    private static function key(string $secret): string
    {
        return hash_hmac('sha256', 'oidc-authorization-request', $secret, true);
    }
}
