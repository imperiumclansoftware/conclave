<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TmdbSettingsRequest
{
    public function __construct(
        /** API key (v3, 32 hexadecimal characters) or API read access token (v4); "" removes it */
        #[Assert\Regex(pattern: '/^([a-f0-9]{32}|eyJ[A-Za-z0-9._-]{20,})?$/', message: 'Saisissez la « clé d\'API » TMDB (32 caractères) ou le « jeton d\'accès en lecture ».')]
        #[Assert\Length(max: 1000)]
        public ?string $apiKey = null,
    ) {
    }
}
