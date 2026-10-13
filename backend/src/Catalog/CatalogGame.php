<?php

namespace App\Catalog;

use App\Enum\GamePlatform;

/**
 * A search result from a game catalog.
 */
final readonly class CatalogGame
{
    /**
     * @param list<GamePlatform> $platforms
     */
    public function __construct(
        public string $source,
        public string $externalId,
        public string $name,
        public ?string $coverUrl,
        public ?string $releaseDate,
        public array $platforms,
    ) {
    }
}
