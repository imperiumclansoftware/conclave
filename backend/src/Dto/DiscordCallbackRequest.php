<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DiscordCallbackRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $code = '',
        #[Assert\NotBlank]
        public string $state = '',
    ) {
    }
}
