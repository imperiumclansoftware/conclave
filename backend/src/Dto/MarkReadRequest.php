<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MarkReadRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $messageId = null,
    ) {
    }
}
