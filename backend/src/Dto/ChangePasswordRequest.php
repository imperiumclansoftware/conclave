<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangePasswordRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $currentPassword = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: UserInput::PASSWORD_MIN_LENGTH, max: 4096)]
        #[Assert\NotEqualTo(propertyPath: 'currentPassword', message: 'Le nouveau mot de passe doit être différent de l\'actuel.')]
        public string $newPassword = '',
    ) {
    }
}
