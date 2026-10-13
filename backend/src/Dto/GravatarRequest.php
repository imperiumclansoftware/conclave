<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GravatarRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Saisissez l\'adresse e-mail de votre compte Gravatar.', normalizer: 'trim')]
        #[Assert\Email(message: 'Adresse e-mail invalide.', normalizer: 'trim')]
        #[Assert\Length(max: 254)]
        public string $email = '',
    ) {
    }
}
