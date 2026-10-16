<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Chat\RealtimeNotifier;
use App\Dto\EventMessageRequest;
use App\Entity\Event;
use App\Entity\EventMessage;
use App\Entity\User;
use App\Events\EventPermissions;
use App\Repository\EventMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Chat room of an event: read by the whole clan, written by the participants before and during
 * the event, then kept read-only.
 */
#[Route('/api/events/{id}/messages', requirements: ['id' => Requirement::DIGITS], format: 'json')]
final class EventChatController extends AbstractController
{
    private const PAGE_SIZE = 50;

    public function __construct(
        private readonly EventPermissions $permissions,
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly RealtimeNotifier $notifier,
    ) {
    }

    /** The latest messages, or those before ?before=id */
    #[Route('', name: 'api_event_messages', methods: ['GET'])]
    public function list(
        Event $event,
        #[CurrentUser] User $me,
        EventMessageRepository $messages,
        #[MapQueryParameter] ?int $before = null,
    ): JsonResponse {
        $this->assertVisible($event, $me);
        $page = $messages->findPage($event, $before, self::PAGE_SIZE + 1);
        $hasMore = \count($page) > self::PAGE_SIZE;

        return $this->json([
            'items' => array_map($this->presenter->eventMessage(...), $hasMore ? \array_slice($page, 1) : $page),
            'hasMore' => $hasMore,
            // Leaders remove any message, at any time
            'canModerate' => $me->isLeader(),
        ]);
    }

    #[Route('', name: 'api_event_message_post', methods: ['POST'])]
    public function post(Event $event, #[CurrentUser] User $me, #[MapRequestPayload] EventMessageRequest $request): JsonResponse
    {
        $this->assertVisible($event, $me);
        if (!$this->permissions->canChat($me, $event)) {
            throw new AccessDeniedHttpException($event->isChatOpen() ? 'Seuls les participants écrivent dans la discussion de l\'événement.' : 'L\'événement est terminé : sa discussion est en lecture seule.');
        }

        $message = new EventMessage($event, $me, trim($request->content));
        $this->em->persist($message);
        $this->em->flush();
        $this->notifier->eventMessagePosted($message, $me->getClan()->getMembers());

        return $this->json($this->presenter->eventMessage($message), Response::HTTP_CREATED);
    }

    #[Route('/{messageId}', name: 'api_event_message_delete', requirements: ['messageId' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(
        Event $event,
        #[MapEntity(id: 'messageId')] EventMessage $message,
        #[CurrentUser] User $me,
    ): Response {
        $this->assertVisible($event, $me);
        if ($message->getEvent() !== $event) {
            throw new NotFoundHttpException('Message introuvable.');
        }
        if (!$this->permissions->canDeleteMessage($me, $message)) {
            throw new AccessDeniedHttpException('Seul son auteur, tant que la discussion est ouverte, ou un dirigeant retire ce message.');
        }

        $id = (int) $message->getId();
        $this->em->remove($message);
        $this->em->flush();
        $this->notifier->eventMessageDeleted($event, $id, $me->getClan()->getMembers());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function assertVisible(Event $event, User $me): void
    {
        if (!$this->permissions->canSee($me, $event)) {
            throw new NotFoundHttpException('Événement introuvable.');
        }
    }
}
