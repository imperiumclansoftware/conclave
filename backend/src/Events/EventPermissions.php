<?php

namespace App\Events;

use App\Entity\Branch;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventType;
use App\Repository\BranchRepository;

/**
 * Every member sees the events of the active branches; the members of a branch create its
 * events and join them. The creator edits an event while in the branch; leaders moderate.
 */
final readonly class EventPermissions
{
    public function __construct(
        private BranchRepository $branches,
    ) {
    }

    /** The active branch of the clan holding this type of events, if the member is in it */
    public function branchFor(User $user, EventType $type): ?Branch
    {
        foreach ($this->branches->findForMember($user) as $branch) {
            if ($branch->getCrest() === $type->branch() && $branch->getClan()->getId() === $user->getClan()->getId()) {
                return $branch;
            }
        }

        return null;
    }

    public function canSee(User $user, Event $event): bool
    {
        return $event->getBranch()->getClan()->getId() === $user->getClan()->getId() && $event->getBranch()->isActive();
    }

    public function canParticipate(User $user, Event $event): bool
    {
        return $this->canSee($user, $event) && $user->isActive() && $event->getBranch()->hasMember($user) && !$event->isOver();
    }

    public function canEdit(User $user, Event $event): bool
    {
        return $this->canSee($user, $event)
            && ($user->isLeader() || ($event->getCreatedBy() === $user && $event->getBranch()->hasMember($user)));
    }

    public function canDelete(User $user, Event $event): bool
    {
        return $this->canSee($user, $event) && ($user->isLeader() || $event->getCreatedBy() === $user);
    }
}
