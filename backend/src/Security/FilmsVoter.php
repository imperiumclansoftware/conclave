<?php

namespace App\Security;

use App\Entity\User;
use App\Films\FilmsAccess;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, null>
 */
final class FilmsVoter extends Voter
{
    public const ACCESS = 'FILMS_ACCESS';

    public function __construct(
        private readonly FilmsAccess $access,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCESS === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $this->access->allowed($user);
    }
}
