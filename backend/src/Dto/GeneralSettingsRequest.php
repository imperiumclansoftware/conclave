<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GeneralSettingsRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 100)]
        public string $clanName = '',
        /** Public URL of the app, used in the links of the e-mails */
        #[Assert\Url(requireTld: false)]
        #[Assert\Length(max: 500)]
        public ?string $appUrl = null,
    ) {
    }
}
