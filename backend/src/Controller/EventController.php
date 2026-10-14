<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Chat\RealtimeNotifier;
use App\Dto\EventRequest;
use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\User;
use App\Enum\EventType;
use App\Enum\FilmPlatform;
use App\Enum\StreamPlatform;
use App\Events\EventEditor;
use App\Events\EventPermissions;
use App\Repository\BranchRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Events of the branches: seen by the whole clan, created and joined by the members of the branch.
 */
#[Route('/api/events', format: 'json')]
final class EventController extends AbstractController
{
    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EventPermissions $permissions,
        private readonly EntityManagerInterface $em,
        private readonly RealtimeNotifier $notifier,
    ) {
    }

    /** Types of events of the active branches, and those the member can create. */
    #[Route('/types', name: 'api_event_types', methods: ['GET'])]
    public function types(#[CurrentUser] User $me, BranchRepository $branches): JsonResponse
    {
        $types = [];
        foreach ($branches->findByClan($me->getClan()) as $branch) {
            if (null === $branch->getCrest()) {
                continue;
            }
            foreach (EventType::forBranch($branch->getCrest()) as $type) {
                $types[] = [
                    'type' => $type->value,
                    'label' => $type->label(),
                    'branch' => ['id' => $branch->getId(), 'name' => $branch->getName(), 'crest' => $branch->getCrest()->value],
                    'fields' => array_map(static fn (string $name, bool $required): array => ['name' => $name, 'required' => $required], array_keys($type->fields()), $type->fields()),
                    'canCreate' => $branch->hasMember($me),
                ];
            }
        }

        return $this->json([
            'types' => $types,
            'filmPlatforms' => array_map(static fn (FilmPlatform $p): array => ['value' => $p->value, 'label' => $p->label()], FilmPlatform::cases()),
            'streamPlatforms' => array_map(static fn (StreamPlatform $p): array => ['value' => $p->value, 'label' => $p->label()], StreamPlatform::cases()),
        ]);
    }

    /** Longest range of the planning: a month view with its overflowing weeks */
    private const MAX_RANGE_DAYS = 45;

    /**
     * Upcoming events (and those in progress), the past ones with ?period=past, or those between two
     * instants with ?from=…&to=… (planning).
     */
    #[Route('', name: 'api_events', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $me,
        EventRepository $events,
        BranchRepository $branches,
        #[MapQueryParameter] string $period = 'upcoming',
        #[MapQueryParameter] ?int $branch = null,
        #[MapQueryParameter] ?string $from = null,
        #[MapQueryParameter] ?string $to = null,
    ): JsonResponse {
        $filter = null !== $branch ? $branches->find($branch) : null;

        if (null !== $from || null !== $to) {
            try {
                $start = new \DateTimeImmutable((string) $from);
                $end = new \DateTimeImmutable((string) $to);
            } catch (\Exception $e) {
                throw new BadRequestHttpException('Dates invalides : utilisez le format ISO 8601.', $e);
            }
            if (null === $from || null === $to || $end <= $start || $start->diff($end)->days > self::MAX_RANGE_DAYS) {
                throw new BadRequestHttpException(\sprintf('Indiquez « from » et « to », sur %d jours au plus.', self::MAX_RANGE_DAYS));
            }
            $found = $events->between($me->getClan(), $start, $end, $filter);
        } else {
            $found = $events->forClan($me->getClan(), 'past' === $period, $filter);
        }

        return $this->json(array_map(fn (Event $event): array => $this->present($event, $me), $found));
    }

    #[Route('/{id}', name: 'api_event_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(Event $event, #[CurrentUser] User $me): JsonResponse
    {
        $this->assertVisible($event, $me);

        return $this->json($this->present($event, $me));
    }

    #[Route('', name: 'api_event_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] EventRequest $request, #[CurrentUser] User $me, EventEditor $editor): JsonResponse
    {
        $event = $editor->create($me, $request);
        $this->em->persist($event);
        // The author comes, of course
        $participant = new EventParticipant($event, $me);
        $event->getParticipants()->add($participant);
        $this->em->persist($participant);
        $this->em->flush();
        $this->changed();

        return $this->json($this->present($event, $me), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_event_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function update(Event $event, #[MapRequestPayload] EventRequest $request, #[CurrentUser] User $me, EventEditor $editor): JsonResponse
    {
        $this->assertVisible($event, $me);
        if (!$this->permissions->canEdit($me, $event)) {
            throw new AccessDeniedHttpException('Seul l\'organisateur, ou un dirigeant, modifie cet événement.');
        }
        $editor->update($event, $request);
        $this->em->flush();
        $this->changed();

        return $this->json($this->present($event, $me));
    }

    #[Route('/{id}', name: 'api_event_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(Event $event, #[CurrentUser] User $me): Response
    {
        $this->assertVisible($event, $me);
        if (!$this->permissions->canDelete($me, $event)) {
            throw new AccessDeniedHttpException('Seul l\'organisateur, ou un dirigeant, supprime cet événement.');
        }
        $this->em->remove($event);
        $this->em->flush();
        $this->changed();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/participation', name: 'api_event_join', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function join(Event $event, #[CurrentUser] User $me): JsonResponse
    {
        $this->assertVisible($event, $me);
        if (null === $event->participantOf($me)) {
            if (!$this->permissions->canParticipate($me, $event)) {
                throw new AccessDeniedHttpException($event->isOver() ? 'Cet événement est terminé.' : 'Seuls les membres de la branche participent à cet événement.');
            }
            if ($event->isFull()) {
                throw new ConflictHttpException('Cet événement est complet.');
            }
            $participant = new EventParticipant($event, $me);
            $event->getParticipants()->add($participant);
            $this->em->persist($participant);
            $this->em->flush();
            $this->changed();
        }

        return $this->json($this->present($event, $me));
    }

    #[Route('/{id}/participation', name: 'api_event_leave', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function leave(Event $event, #[CurrentUser] User $me): JsonResponse
    {
        $this->assertVisible($event, $me);
        if (null !== $participant = $event->participantOf($me)) {
            $event->getParticipants()->removeElement($participant);
            $this->em->remove($participant);
            $this->em->flush();
            $this->changed();
        }

        return $this->json($this->present($event, $me));
    }

    /** @return array<string, mixed> */
    private function present(Event $event, User $me): array
    {
        return $this->presenter->event($event, $me, [
            'participate' => null === $event->participantOf($me) && $this->permissions->canParticipate($me, $event) && !$event->isFull(),
            'edit' => $this->permissions->canEdit($me, $event),
            'delete' => $this->permissions->canDelete($me, $event),
        ]);
    }

    private function assertVisible(Event $event, User $me): void
    {
        if (!$this->permissions->canSee($me, $event)) {
            throw new NotFoundHttpException('Événement introuvable.');
        }
    }

    /** Everybody's list of events follows */
    private function changed(): void
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $this->notifier->eventsChanged($user->getClan()->getMembers());
        }
    }
}
