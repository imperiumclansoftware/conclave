<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OpenConversationRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $userId = null,
    ) {
    }
}
