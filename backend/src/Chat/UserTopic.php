<?php

namespace App\Chat;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Each user listens to a single private Mercure topic carrying all their real-time events.
 */
final readonly class UserTopic
{
    public function __construct(
        #[Autowire(env: 'MERCURE_TOPIC_PREFIX')]
        private string $prefix,
    ) {
    }

    public function for(User $user): string
    {
        return \sprintf('%s/users/%d', rtrim($this->prefix, '/'), $user->getId());
    }
}
