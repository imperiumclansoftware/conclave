<?php

namespace App\Security;

use App\Entity\Conversation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Conversation>
 */
final class ConversationVoter extends Voter
{
    public const ACCESS = 'CONVERSATION_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCESS === $attribute && $subject instanceof Conversation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject->hasParticipant($user);
    }
}
