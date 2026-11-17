<?php

namespace App\Controller;

use App\Dto\OAuthDecisionRequest;
use App\Entity\OAuthClient;
use App\Entity\User;
use App\OAuth\AuthorizationRequest;
use App\OAuth\OpenIdConnect;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Consent of the signed-in member to an OpenID Connect request (page /autoriser of the app).
 */
#[Route('/api/oauth/authorization', format: 'json')]
final class OAuthConsentController extends AbstractController
{
    public function __construct(private readonly OpenIdConnect $oidc)
    {
    }

    #[Route('', name: 'api_oauth_authorization', methods: ['GET'])]
    public function show(#[MapQueryParameter] string $demande, #[CurrentUser] User $user): JsonResponse
    {
        [$request, $client] = $this->open($demande);

        return $this->json([
            'client' => $client->getName(),
            'host' => parse_url($request->redirectUri, \PHP_URL_HOST),
            'scopes' => $request->scopes,
            'claims' => ['name' => $user->getDisplayName()] + $this->oidc->profileClaims($user, $request->scopes),
        ]);
    }

    #[Route('', name: 'api_oauth_authorization_decide', methods: ['POST'])]
    public function decide(#[CurrentUser] User $user, #[MapRequestPayload] OAuthDecisionRequest $decision): JsonResponse
    {
        [$request, $client] = $this->open($decision->demande);

        return $this->json(['redirect' => $decision->approve ? $this->oidc->approve($request, $client, $user) : $this->oidc->deny($request)]);
    }

    /** @return array{AuthorizationRequest, OAuthClient} */
    private function open(string $demande): array
    {
        $opened = $this->oidc->open($demande);
        if (null === $opened) {
            throw new BadRequestHttpException('Demande de connexion expirée ou invalide : recommencez depuis l’application.');
        }

        return $opened;
    }
}
