<?php

namespace App\Dto;

use App\Enum\ClanRole;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateMemberRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: UserInput::USERNAME_PATTERN, message: UserInput::USERNAME_MESSAGE)]
        public string $username = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 50)]
        public string $displayName = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: UserInput::PASSWORD_MIN_LENGTH, max: 4096)]
        public string $password = '',
        #[Assert\NotNull]
        public ?ClanRole $role = null,
        #[Assert\Email(normalizer: 'trim')]
        #[Assert\Length(max: 180)]
        public ?string $email = null,
    ) {
    }
}
