<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: UserInput::USERNAME_PATTERN, message: UserInput::USERNAME_MESSAGE)]
        public string $username = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 50)]
        public string $displayName = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email(normalizer: 'trim')]
        #[Assert\Length(max: 180)]
        public string $email = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: UserInput::PASSWORD_MIN_LENGTH, max: 4096)]
        public string $password = '',
    ) {
    }
}
