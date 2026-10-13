<?php

namespace App\Catalog;

use App\Enum\GamePlatform;

/**
 * Full description of a game from a catalog.
 */
final readonly class CatalogGameDetails
{
    /**
     * @param list<array{thumbnail: string, full: string}> $screenshots
     * @param list<string>                                 $genres
     * @param list<string>                                 $developers
     * @param list<GamePlatform>                           $platforms
     */
    public function __construct(
        public string $name,
        public ?string $coverUrl,
        public ?string $description,
        public array $screenshots,
        public array $genres,
        public array $developers,
        public array $platforms,
        public ?string $releaseDate,
        public ?string $website,
    ) {
    }
}
