<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class HeartbeatRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['online', 'away', 'offline'])]
        public string $state = 'online',
    ) {
    }
}
