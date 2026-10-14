<?php

namespace App\Games;

use App\Entity\User;
use App\Enum\BranchCrest;
use App\Repository\BranchRepository;

/**
 * The games part (games of each member, clan library) belongs to the Joueurs branch: only its
 * members see it, leaders included only if they joined it.
 */
final readonly class GamesAccess
{
    public function __construct(
        private BranchRepository $branches,
    ) {
    }

    public function allowed(User $user): bool
    {
        return $user->isActive() && $this->branches->isMemberOf($user, BranchCrest::Players);
    }
}
