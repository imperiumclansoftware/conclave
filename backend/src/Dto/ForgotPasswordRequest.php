<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ForgotPasswordRequest
{
    public function __construct(
        /** Username or e-mail address */
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 180)]
        public string $identifier = '',
    ) {
    }
}
