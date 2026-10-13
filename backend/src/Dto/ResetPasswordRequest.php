<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResetPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $token = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: UserInput::PASSWORD_MIN_LENGTH, max: 4096)]
        public string $password = '',
    ) {
    }
}
