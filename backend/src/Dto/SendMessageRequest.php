<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SendMessageRequest
{
    public const MAX_LENGTH = 4000;

    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: self::MAX_LENGTH)]
        public string $content = '',
    ) {
    }
}
