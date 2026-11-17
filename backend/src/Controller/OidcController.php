<?php

namespace App\Controller;

use App\Enum\OAuthScope;
use App\OAuth\AuthorizationError;
use App\OAuth\ClientCredentials;
use App\OAuth\OidcKeys;
use App\OAuth\OpenIdConnect;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OpenID Connect provider endpoints (ADR 0002). Public and stateless, outside the members' JWT
 * firewall; the member's consent goes through the app (page /autoriser, OAuthConsentController).
 */
final class OidcController extends AbstractController
{
    public function __construct(private readonly OpenIdConnect $oidc)
    {
    }

    #[Route('/.well-known/openid-configuration', name: 'oidc_discovery', methods: ['GET'])]
    public function discovery(Request $request): JsonResponse
    {
        return $this->publicJson($this->oidc->discovery($this->oidc->issuer($request->getSchemeAndHttpHost())));
    }

    #[Route('/.well-known/jwks.json', name: 'oidc_jwks', methods: ['GET'])]
    public function jwks(OidcKeys $keys): JsonResponse
    {
        return $this->publicJson($keys->jwks());
    }

    /** Checks the request, then hands it (signed) to the consent page of the app. */
    #[Route('/oauth/authorize', name: 'oidc_authorize', methods: ['GET'])]
    public function authorize(Request $request): RedirectResponse
    {
        $app = $this->oidc->issuer($request->getSchemeAndHttpHost()).'/autoriser';
        try {
            $sealed = $this->oidc->seal($this->oidc->validate($request->query->all()));
        } catch (AuthorizationError $e) {
            return new RedirectResponse($e->redirectUrl() ?? $app.'?'.http_build_query(['erreur' => $e->description]));
        }

        return new RedirectResponse($app.'?'.http_build_query(['demande' => $sealed]));
    }

    #[Route('/oauth/userinfo', name: 'oidc_userinfo', methods: ['GET', 'POST'])]
    public function userinfo(Request $request, ClientCredentials $credentials): JsonResponse
    {
        $header = (string) $request->headers->get('Authorization');
        $token = str_starts_with($header, 'Bearer ') ? $credentials->findValidToken(trim(substr($header, 7))) : null;
        $user = $token?->getUser();
        if (null === $token || null === $user || !$user->isActive()) {
            return new JsonResponse(['error' => 'invalid_token'], 401, ['WWW-Authenticate' => 'Bearer error="invalid_token"']);
        }
        if (!\in_array(OAuthScope::OpenId->value, $token->getScopes(), true)) {
            return new JsonResponse(['error' => 'insufficient_scope'], 403, ['WWW-Authenticate' => 'Bearer error="insufficient_scope"']);
        }

        return new JsonResponse(['sub' => $user->getPublicSub()] + $this->oidc->profileClaims($user, $token->getScopes()), 200, ['Cache-Control' => 'no-store']);
    }

    /** @param array<string, mixed> $data */
    private function publicJson(array $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
