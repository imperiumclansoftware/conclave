<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Discord\DiscordApiException;
use App\Discord\VideoCallService;
use App\Dto\CreateCallRequest;
use App\Entity\User;
use App\Entity\VideoCall;
use App\Repository\UserRepository;
use App\Repository\VideoCallRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/calls', format: 'json')]
final class VideoCallController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
    ) {
    }

    /** Ongoing calls the user is invited to. */
    #[Route('', name: 'api_calls', methods: ['GET'])]
    public function list(#[CurrentUser] User $user, VideoCallRepository $calls): JsonResponse
    {
        return $this->json(array_map($this->presenter->call(...), $calls->findActiveFor($user)));
    }

    /** Creates a video call on Discord with the chosen members. */
    #[Route('', name: 'api_calls_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreateCallRequest $request,
        UserRepository $users,
        VideoCallService $videoCalls,
    ): JsonResponse {
        $invitees = $users->findBy(['id' => $request->userIds, 'clan' => $user->getClan(), 'status' => 'active']);
        if (\count($invitees) !== \count(array_unique($request->userIds))) {
            throw new UnprocessableEntityHttpException('Seuls les membres du clan peuvent être invités.');
        }

        try {
            $call = $videoCalls->create($user, $invitees, $request->title);
        } catch (\DomainException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        } catch (DiscordApiException $e) {
            throw new ServiceUnavailableHttpException(null, 'Discord n\'a pas pu créer le salon de la visio.', $e);
        }

        return $this->json($this->presenter->call($call), Response::HTTP_CREATED);
    }

    /** Ends a call (its creator or a leader). */
    #[Route('/{id}', name: 'api_calls_end', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function end(VideoCall $call, #[CurrentUser] User $user, VideoCallService $videoCalls): Response
    {
        $sameClan = $call->getClan()->getId() === $user->getClan()->getId();
        $allowed = $call->getCreatedBy()?->getId() === $user->getId() || ($sameClan && $user->isLeader());
        if (!$allowed) {
            throw new AccessDeniedHttpException('Seul le créateur de la visio ou un dirigeant peut y mettre fin.');
        }

        $videoCalls->end($call);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
