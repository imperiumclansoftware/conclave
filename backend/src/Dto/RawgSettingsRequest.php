<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RawgSettingsRequest
{
    public function __construct(
        #[Assert\Regex(pattern: '/^([a-f0-9]{32})?$/', message: 'Une clé RAWG compte 32 caractères hexadécimaux.')]
        public ?string $apiKey = null,
    ) {
    }
}
