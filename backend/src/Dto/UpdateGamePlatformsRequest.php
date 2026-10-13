<?php

namespace App\Dto;

use App\Enum\GamePlatform;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateGamePlatformsRequest
{
    /**
     * @param list<string> $platforms
     */
    public function __construct(
        #[Assert\Count(min: 1, minMessage: 'Choisissez au moins une plateforme.')]
        #[Assert\All([new Assert\Choice(callback: [GamePlatform::class, 'values'])])]
        public array $platforms = [],
    ) {
    }
}
