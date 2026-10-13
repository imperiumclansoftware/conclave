<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Dto\HeartbeatRequest;
use App\Entity\User;
use App\Presence\PresenceTracker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api', format: 'json')]
final class PresenceController extends AbstractController
{
    /**
     * Heartbeat sent by open tabs ("away" when in the background, "offline" when closing).
     */
    #[Route('/presence', name: 'api_presence_heartbeat', methods: ['POST'])]
    public function heartbeat(#[CurrentUser] User $user, #[MapRequestPayload] HeartbeatRequest $request, PresenceTracker $tracker): Response
    {
        if ('offline' === $request->state) {
            $tracker->leave($user);
        } else {
            $tracker->heartbeat($user, 'away' === $request->state);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Presence of every clan member, in the app and on Discord.
     */
    #[Route('/clan/presence', name: 'api_clan_presence', methods: ['GET'])]
    public function clan(#[CurrentUser] User $user, ApiPresenter $presenter): JsonResponse
    {
        return $this->json(array_map($presenter->presence(...), $user->getClan()->getMembers()->getValues()));
    }
}
