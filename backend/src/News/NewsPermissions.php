<?php

namespace App\News;

use App\Entity\News;
use App\Entity\User;
use App\Repository\BranchRepository;

/**
 * Who writes the news: only the members of a branch allowed to publish (the Communicants), leaders
 * included only if they joined it. Authors edit their own news while they keep that right; leaders
 * can delete any news (moderation).
 */
final readonly class NewsPermissions
{
    public function __construct(
        private BranchRepository $branches,
    ) {
    }

    public function canPublish(User $user): bool
    {
        return $user->isActive() && $this->branches->publishesNews($user);
    }

    public function canEdit(User $user, News $news): bool
    {
        return $this->sameClan($user, $news) && $news->getAuthor() === $user && $this->canPublish($user);
    }

    public function canDelete(User $user, News $news): bool
    {
        return $this->sameClan($user, $news) && ($user->isLeader() || $news->getAuthor() === $user);
    }

    private function sameClan(User $user, News $news): bool
    {
        return $news->getClan()->getId() === $user->getClan()->getId();
    }
}
