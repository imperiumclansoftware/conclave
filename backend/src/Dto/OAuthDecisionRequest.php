<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OAuthDecisionRequest
{
    public function __construct(
        /** Signed request received by the page /autoriser */
        #[Assert\NotBlank]
        #[Assert\Length(max: 4000)]
        public string $demande = '',
        public bool $approve = false,
    ) {
    }
}
