<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Installation: the clan and its first leader. */
final readonly class SetupRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Saisissez le code d\'installation.')]
        public string $code = '',
        #[Assert\NotBlank(message: 'Donnez un nom à votre clan.', normalizer: 'trim')]
        #[Assert\Length(max: 100, normalizer: 'trim')]
        public string $clanName = '',
        /** Public address, for the links of the e-mails */
        #[Assert\Url(message: 'Adresse invalide : elle commence par https:// ou http://.', protocols: ['http', 'https'], requireTld: false, normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public ?string $appUrl = null,
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
