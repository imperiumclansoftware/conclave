<?php

namespace App\Security;

use App\Entity\News;
use App\Entity\User;
use App\News\NewsPermissions;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, News|null>
 */
final class NewsVoter extends Voter
{
    public const PUBLISH = 'NEWS_PUBLISH';
    public const EDIT = 'NEWS_EDIT';
    public const DELETE = 'NEWS_DELETE';

    public function __construct(
        private readonly NewsPermissions $permissions,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::PUBLISH === $attribute || (\in_array($attribute, [self::EDIT, self::DELETE], true) && $subject instanceof News);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::PUBLISH => $this->permissions->canPublish($user),
            self::EDIT => $this->permissions->canEdit($user, $subject),
            default => $this->permissions->canDelete($user, $subject),
        };
    }
}
