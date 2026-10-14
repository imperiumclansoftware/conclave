<?php

namespace App\Films;

use App\Entity\User;
use App\Enum\BranchCrest;
use App\Repository\BranchRepository;

/**
 * Film search and fiches belong to the Cinéphiles branch: only its members use them, leaders
 * included only if they joined it. Everybody sees the film of an event, though.
 */
final readonly class FilmsAccess
{
    public function __construct(
        private BranchRepository $branches,
    ) {
    }

    public function allowed(User $user): bool
    {
        return $user->isActive() && $this->branches->isMemberOf($user, BranchCrest::Cinephiles);
    }
}
