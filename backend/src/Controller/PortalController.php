<?php

namespace App\Controller;

use App\Portal\PortalAvatars;
use App\Portal\PortalMembers;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Export for the clan's public portal. The member list needs an access token with the scope
 * portal:members (firewall "portal", BearerTokenAuthenticator); the avatars are public under signed URLs.
 */
#[Route('/api/portal')]
final class PortalController extends AbstractController
{
    #[Route('/members', name: 'api_portal_members', methods: ['GET'], format: 'json')]
    public function members(Request $request, PortalMembers $members): JsonResponse
    {
        return new JsonResponse(['items' => $members->export($request->getSchemeAndHttpHost())], 200, [
            'Cache-Control' => 'no-store',
        ]);
    }

    #[Route(
        '/avatars/{sub}/{signature}.webp',
        name: 'api_portal_avatar',
        requirements: ['sub' => PortalAvatars::SUB_PATTERN, 'signature' => PortalAvatars::SIGNATURE_PATTERN],
        methods: ['GET'],
        format: 'html',
    )]
    public function avatar(string $sub, string $signature, UserRepository $users, PortalAvatars $avatars): BinaryFileResponse
    {
        $member = $users->findOneForPublicSite($sub);
        $path = null === $member ? null : $avatars->file($member, $signature);
        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'image/webp']);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
