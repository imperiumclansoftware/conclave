<?php

namespace App\OAuth;

use App\Entity\OAuthAuthorizationCode;
use App\Entity\OAuthClient;
use App\Entity\User;
use App\Enum\OAuthScope;
use App\Repository\BranchRepository;
use App\Repository\OAuthAuthorizationCodeRepository;
use App\Repository\OAuthClientRepository;
use App\Settings\AppSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * OpenID Connect provider (ADR 0002): authorization code flow with mandatory PKCE (S256), for the
 * registered clients (Imperium Clan portal). The member's "sub" is User::publicSub, the identifier of
 * the portal export too.
 */
class OpenIdConnect
{
    public const CODE_TTL = 60;
    public const ID_TOKEN_TTL = 300;

    public function __construct(
        private readonly OAuthClientRepository $clients,
        private readonly OAuthAuthorizationCodeRepository $codes,
        private readonly BranchRepository $branches,
        private readonly ClientCredentials $credentials,
        private readonly OidcKeys $keys,
        private readonly AppSettings $settings,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    /** Public address of Conclave, the "iss" of the ID tokens (must equal the client's CONCLAVE_URL). */
    public function issuer(string $requestBaseUrl): string
    {
        return rtrim($this->settings->get(AppSettings::APP_URL) ?: $requestBaseUrl, '/');
    }

    /**
     * Checks GET /oauth/authorize. An unknown client or redirect URI is never redirected to (the error is
     * shown to the member); the other errors go back to the client, as the specification asks.
     *
     * @param array<string, mixed> $query
     *
     * @throws AuthorizationError
     */
    public function validate(array $query): AuthorizationRequest
    {
        $clientId = self::str($query, 'client_id');
        $redirectUri = self::str($query, 'redirect_uri');
        $client = '' === $clientId ? null : $this->clients->findOneBy(['clientId' => $clientId]);
        if (null === $client || !$client->isActive()) {
            throw new AuthorizationError('invalid_client', 'Application inconnue ou révoquée.');
        }
        if ('' === $redirectUri || !$client->allowsRedirectUri($redirectUri)) {
            throw new AuthorizationError('invalid_request', 'Adresse de retour non autorisée pour cette application.');
        }

        $state = self::str($query, 'state');
        $back = static fn (string $error, string $description): AuthorizationError => new AuthorizationError($error, $description, $redirectUri, $state);
        if ('code' !== self::str($query, 'response_type')) {
            throw $back('unsupported_response_type', 'Seul response_type=code est accepté.');
        }
        $scopes = array_values(array_unique(array_filter(explode(' ', self::str($query, 'scope')))));
        if (!\in_array(OAuthScope::OpenId->value, $scopes, true)) {
            throw $back('invalid_scope', 'La portée openid est requise.');
        }
        foreach ($scopes as $scope) {
            if (!$client->hasScope($scope)) {
                throw $back('invalid_scope', 'Portée non autorisée pour cette application.');
            }
        }
        $challenge = self::str($query, 'code_challenge');
        if ('S256' !== self::str($query, 'code_challenge_method') || !preg_match('/^[A-Za-z0-9_-]{43,128}$/', $challenge)) {
            throw $back('invalid_request', 'PKCE requis (code_challenge, méthode S256).');
        }
        if ('' === $state || \strlen($state) > 500) {
            throw $back('invalid_request', 'Paramètre state requis.');
        }
        $nonce = self::str($query, 'nonce');
        if (\strlen($nonce) > 255) {
            throw $back('invalid_request', 'Paramètre nonce trop long.');
        }

        return AuthorizationRequest::create($clientId, $redirectUri, $scopes, $state, '' === $nonce ? null : $nonce, $challenge);
    }

    public function seal(AuthorizationRequest $request): string
    {
        return $request->seal($this->secret);
    }

    /**
     * The pending request and its (still active) client, or null if tampered, expired or revoked.
     *
     * @return array{AuthorizationRequest, OAuthClient}|null
     */
    public function open(string $sealed): ?array
    {
        $request = AuthorizationRequest::open($sealed, $this->secret);
        $client = null === $request ? null : $this->clients->findOneBy(['clientId' => $request->clientId]);
        if (null === $request || null === $client || !$client->isActive() || !$client->allowsRedirectUri($request->redirectUri)) {
            return null;
        }

        return [$request, $client];
    }

    /** The member approved: one-time code, back to the client. */
    public function approve(AuthorizationRequest $request, OAuthClient $client, User $user): string
    {
        $this->codes->purgeExpired(new \DateTimeImmutable('-1 day'));
        $code = ClientCredentials::random(32);
        $this->em->persist(new OAuthAuthorizationCode(
            ClientCredentials::hash($code), $client, $user, $request->redirectUri, $request->codeChallenge,
            $request->nonce, $request->scopes, new \DateTimeImmutable('+'.self::CODE_TTL.' seconds'),
        ));
        $this->em->flush();

        return self::redirect($request->redirectUri, ['code' => $code, 'state' => $request->state]);
    }

    public function deny(AuthorizationRequest $request): string
    {
        return self::redirect($request->redirectUri, ['error' => 'access_denied', 'error_description' => 'Connexion refusée par le membre.', 'state' => $request->state]);
    }

    /**
     * POST /oauth/token, grant authorization_code.
     *
     * @return array<string, mixed> token response
     *
     * @throws AuthorizationError invalid_grant
     */
    public function exchange(OAuthClient $client, string $code, string $redirectUri, string $verifier, string $issuer): array
    {
        $found = '' === $code ? null : $this->codes->findOneBy(['codeHash' => ClientCredentials::hash($code)]);
        if (null !== $found && $found->isUsed()) {
            // Replayed code (stolen?): the tokens already issued from it are revoked (RFC 6749 §4.1.2)
            $this->revokeUserTokens($found);
        }
        if (null === $found || !$found->isUsable() || $found->getClient()->getId() !== $client->getId()
            || !hash_equals($found->getRedirectUri(), $redirectUri)
            || !hash_equals($found->getCodeChallenge(), OidcKeys::b64(hash('sha256', $verifier, true)))
            || !$found->getUser()->isActive()) {
            throw new AuthorizationError('invalid_grant', 'Code invalide, expiré ou déjà utilisé.');
        }
        $found->markUsed();
        $user = $found->getUser();
        $scopes = $found->getScopes();
        $accessToken = $this->credentials->issueToken($client, $scopes, $user);

        $now = time();
        $claims = ['iss' => $issuer, 'sub' => $user->getPublicSub(), 'aud' => $client->getClientId(), 'iat' => $now, 'exp' => $now + self::ID_TOKEN_TTL];
        if (null !== $found->getNonce()) {
            $claims['nonce'] = $found->getNonce();
        }

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => ClientCredentials::TOKEN_TTL,
            'scope' => implode(' ', $scopes),
            'id_token' => $this->keys->sign($claims + $this->profileClaims($user, $scopes)),
        ];
    }

    /**
     * Claims of the member: display name and crests of their active branches (scope profile).
     *
     * @param list<string> $scopes
     *
     * @return array<string, mixed>
     */
    public function profileClaims(User $user, array $scopes): array
    {
        if (!\in_array(OAuthScope::Profile->value, $scopes, true)) {
            return [];
        }
        $crests = $this->branches->crestsByMember([$user])[$user->getId()] ?? [];

        return ['name' => $user->getDisplayName(), 'branches' => $crests];
    }

    /** @return array<string, mixed> */
    public function discovery(string $issuer): array
    {
        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'userinfo_endpoint' => $issuer.'/oauth/userinfo',
            'jwks_uri' => $issuer.'/.well-known/jwks.json',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'client_credentials'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => [OAuthScope::OpenId->value, OAuthScope::Profile->value],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'nonce', 'name', 'branches'],
        ];
    }

    private function revokeUserTokens(OAuthAuthorizationCode $code): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\OAuthAccessToken t WHERE t.client = :client AND t.user = :user')
            ->setParameter('client', $code->getClient())->setParameter('user', $code->getUser())
            ->execute();
    }

    /** @param array<string, string> $params */
    private static function redirect(string $uri, array $params): string
    {
        return $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    /** @param array<string, mixed> $query */
    private static function str(array $query, string $key): string
    {
        return \is_string($query[$key] ?? null) ? $query[$key] : '';
    }
}
