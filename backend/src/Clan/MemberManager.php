<?php

namespace App\Clan;

use App\Chat\RealtimeNotifier;
use App\Entity\Clan;
use App\Entity\User;
use App\Enum\ClanRole;
use App\Mail\AppMailer;
use App\Profile\AvatarStorage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Membership decisions taken by leaders: sign-up approval, roles, removal.
 */
final readonly class MemberManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private AppMailer $mailer,
        private RealtimeNotifier $notifier,
        private AvatarStorage $avatars,
    ) {
    }

    /** @return list<User> sign-ups waiting for approval, oldest first */
    public function pending(Clan $clan): array
    {
        return $this->users->findBy(['clan' => $clan, 'status' => 'pending'], ['createdAt' => 'ASC']);
    }

    public function approve(User $user): void
    {
        $this->ensurePending($user);
        $user->approve();
        $this->em->flush();

        $this->mailer->accountApproved($user);
        $this->notifier->memberJoined($user);
        $this->notifier->signupsChanged($this->leaders($user->getClan()));
    }

    /** Refuses a sign-up: the applicant is told, and the account deleted. */
    public function reject(User $user): void
    {
        $this->ensurePending($user);
        $this->mailer->accountRejected($user);
        $clan = $user->getClan();
        $this->em->remove($user);
        $this->em->flush();

        $this->notifier->signupsChanged($this->leaders($clan));
    }

    /**
     * @throws \DomainException when the clan would be left without a leader
     */
    public function changeRole(User $user, ClanRole $role): void
    {
        if (ClanRole::Leader !== $role && $user->isLeader() && 1 === \count($this->leaders($user->getClan()))) {
            throw new \DomainException('Le clan doit garder au moins un dirigeant : nommez-en un autre d\'abord.');
        }

        $user->setClanRole($role);
        $this->em->flush();
        $this->notifier->memberUpdated($user);
    }

    /**
     * @throws \DomainException when removing oneself or the last leader
     */
    public function remove(User $user, User $by): void
    {
        if ($user->getId() === $by->getId()) {
            throw new \DomainException('Vous ne pouvez pas vous retirer vous-même du clan.');
        }
        if ($user->isLeader() && 1 === \count($this->leaders($user->getClan()))) {
            throw new \DomainException('Le clan doit garder au moins un dirigeant.');
        }

        $clan = $user->getClan();
        $this->avatars->remove($user);
        $this->em->remove($user);
        $this->em->flush();
        $this->notifier->memberLeft($user, $clan->getMembers()->filter(static fn (User $u): bool => $u !== $user));
    }

    /** Member added directly by a leader: active right away. */
    public function added(User $user): void
    {
        $this->notifier->memberJoined($user);
    }

    /** @return list<User> */
    public function leaders(Clan $clan): array
    {
        return $clan->getMembers()->filter(static fn (User $u): bool => $u->isLeader())->getValues();
    }

    private function ensurePending(User $user): void
    {
        if ($user->isActive()) {
            throw new \DomainException('Ce compte est déjà validé.');
        }
    }
}
