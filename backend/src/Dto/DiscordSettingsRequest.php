<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Discord settings from the administration. Secrets left null are kept unchanged;
 * an empty string removes a value.
 */
final readonly class DiscordSettingsRequest
{
    public function __construct(
        #[Assert\Regex(pattern: '/^(\d{15,22})?$/', message: 'L\'identifiant d\'application est un nombre de 15 à 22 chiffres.')]
        public ?string $clientId = null,
        #[Assert\Length(max: 200)]
        public ?string $clientSecret = null,
        #[Assert\Length(max: 200)]
        #[Assert\Regex(pattern: '/^[\w.\-]*$/', message: 'Jeton de bot invalide.')]
        public ?string $botToken = null,
        #[Assert\Url(requireTld: false)]
        #[Assert\Length(max: 500)]
        public ?string $redirectUri = null,
    ) {
    }
}
