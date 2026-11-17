<?php

namespace App\OAuth;

use App\Enum\OAuthScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authenticates machine clients by their opaque access token (Authorization: Bearer …), with the error
 * responses of RFC 6750: 401 invalid_token, 403 insufficient_scope.
 */
final class BearerTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface, AccessDeniedHandlerInterface
{
    public function __construct(private readonly ClientCredentials $credentials)
    {
    }

    public function supports(Request $request): bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $raw = trim(substr((string) $request->headers->get('Authorization'), \strlen('Bearer ')));
        $token = $this->credentials->findValidToken($raw);
        if (null === $token) {
            throw new CustomUserMessageAuthenticationException('Jeton invalide ou expiré.');
        }

        $roles = [];
        foreach ($token->getScopes() as $scope) {
            if (null !== $known = OAuthScope::tryFrom($scope)) {
                $roles[] = $known->role();
            }
        }
        $clientId = $token->getClient()->getClientId();

        return new SelfValidatingPassport(new UserBadge($clientId, static fn (): OAuthClientUser => new OAuthClientUser($clientId, $roles)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return self::error(401, 'invalid_token', 'Jeton invalide ou expiré.');
    }

    /** No token at all */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Jeton d’accès requis.'], 401, ['WWW-Authenticate' => 'Bearer realm="Conclave"']);
    }

    /** Valid token, but without the scope this route needs */
    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return self::error(403, 'insufficient_scope', 'Ce jeton n’a pas la portée requise.');
    }

    private static function error(int $status, string $error, string $description): JsonResponse
    {
        return new JsonResponse(['error' => $error, 'error_description' => $description], $status, [
            'WWW-Authenticate' => \sprintf('Bearer realm="Conclave", error="%s"', $error),
        ]);
    }
}
