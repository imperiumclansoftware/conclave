<?php

namespace App\Catalog;

interface GameCatalogInterface
{
    /** Identifier stored with each game ("steam", "rawg"). */
    public function source(): string;

    /**
     * @return list<CatalogGame>
     *
     * @throws CatalogUnavailableException
     */
    public function search(string $query): array;

    /**
     * @return CatalogGameDetails|null null when the game does not exist
     *
     * @throws CatalogUnavailableException
     */
    public function details(string $externalId): ?CatalogGameDetails;
}
