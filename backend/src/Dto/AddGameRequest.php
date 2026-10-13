<?php

namespace App\Dto;

use App\Catalog\RawgCatalog;
use App\Catalog\SteamCatalog;
use App\Enum\GamePlatform;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddGameRequest
{
    /**
     * @param list<string> $platforms
     */
    public function __construct(
        #[Assert\Choice(choices: [SteamCatalog::SOURCE, RawgCatalog::SOURCE])]
        public string $source = '',
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{1,20}$/')]
        public string $externalId = '',
        #[Assert\Count(min: 1, minMessage: 'Choisissez au moins une plateforme.')]
        #[Assert\All([new Assert\Choice(callback: [GamePlatform::class, 'values'])])]
        public array $platforms = [],
    ) {
    }
}
