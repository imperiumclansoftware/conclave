<?php

namespace App\Controller;

use App\Enum\OAuthScope;
use App\OAuth\AuthorizationError;
use App\OAuth\ClientCredentials;
use App\OAuth\OpenIdConnect;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OAuth2 token endpoint, "client_credentials" grant only (RFC 6749 §4.4), for machine clients such as
 * the clan's public portal. Public, stateless, outside the JWT firewall; errors follow RFC 6749 §5.2.
 */
final class OAuthTokenController extends AbstractController
{
    public function __construct(
        private readonly ClientCredentials $credentials,
        private readonly OpenIdConnect $oidc,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/oauth/token', name: 'oauth_token', methods: ['POST'])]
    public function token(Request $request, RateLimiterFactoryInterface $oauthTokenLimiter): JsonResponse
    {
        if (!$oauthTokenLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return self::error(429, 'temporarily_unavailable', 'Trop de demandes, réessayez dans une minute.');
        }

        // Client authentication: HTTP Basic only (RFC 6749 §2.3.1), never in the body
        [$clientId, $secret] = self::basicCredentials($request);
        if (null === $clientId) {
            return self::error(401, 'invalid_client', 'Authentification du client requise (HTTP Basic).');
        }
        $client = $this->credentials->authenticate($clientId, $secret);
        if (null === $client) {
            $this->logger->warning('OAuth token refused: invalid client credentials.');

            return self::error(401, 'invalid_client', 'Client inconnu, révoqué ou secret incorrect.');
        }

        $grantType = $request->request->getString('grant_type');
        if ('' === $grantType) {
            return self::error(400, 'invalid_request', 'Paramètre grant_type manquant.');
        }
        if ('authorization_code' === $grantType) {
            return $this->authorizationCode($request, $client);
        }
        if ('client_credentials' !== $grantType) {
            return self::error(400, 'unsupported_grant_type', 'Flux accepté : client_credentials ou authorization_code.');
        }

        // No scope asked: every scope of the client (RFC 6749 §3.3)
        $asked = array_values(array_filter(explode(' ', $request->request->getString('scope'))));
        $scopes = [] === $asked ? $client->getScopes() : array_values(array_unique($asked));
        if ([] !== array_diff($scopes, $client->getScopes())) {
            return self::error(400, 'invalid_scope', 'Portée non autorisée pour ce client.');
        }

        $token = $this->credentials->issueToken($client, $scopes);

        return new JsonResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => ClientCredentials::TOKEN_TTL,
            'scope' => implode(' ', $scopes),
        ], 200, self::noStore());
    }

    /** OpenID Connect: one-time code + PKCE verifier → access token and ID token. */
    private function authorizationCode(Request $request, \App\Entity\OAuthClient $client): JsonResponse
    {
        if (!$client->hasScope(OAuthScope::OpenId->value)) {
            return self::error(400, 'unauthorized_client', 'Ce client n’est pas autorisé à connecter des membres.');
        }
        try {
            $response = $this->oidc->exchange(
                $client,
                $request->request->getString('code'),
                $request->request->getString('redirect_uri'),
                $request->request->getString('code_verifier'),
                $this->oidc->issuer($request->getSchemeAndHttpHost()),
            );
        } catch (AuthorizationError $e) {
            return self::error(400, $e->error, $e->description);
        }

        return new JsonResponse($response, 200, self::noStore());
    }

    /** @return array{?string, string} client id (null when absent) and secret */
    private static function basicCredentials(Request $request): array
    {
        $header = (string) $request->headers->get('Authorization');
        if (!preg_match('/^Basic\s+([A-Za-z0-9+\/=]+)$/', $header, $m)) {
            return [null, ''];
        }
        $decoded = base64_decode($m[1], true);
        if (false === $decoded || !str_contains($decoded, ':')) {
            return [null, ''];
        }
        [$id, $secret] = explode(':', $decoded, 2);

        // RFC 6749 §2.3.1: both parts are form-urlencoded before the Basic encoding
        return [urldecode($id), urldecode($secret)];
    }

    private static function error(int $status, string $error, string $description): JsonResponse
    {
        $headers = self::noStore();
        if (401 === $status) {
            $headers['WWW-Authenticate'] = 'Basic realm="Conclave"';
        }

        return new JsonResponse(['error' => $error, 'error_description' => $description], $status, $headers);
    }

    /** @return array<string, string> RFC 6749 §5.1: token responses are never cached */
    private static function noStore(): array
    {
        return ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache'];
    }
}
