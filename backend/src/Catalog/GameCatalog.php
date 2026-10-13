<?php

namespace App\Catalog;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Entry point to the external game catalogs: searches RAWG when an API key is configured
 * (all platforms), Steam otherwise (PC only, no key needed).
 */
final readonly class GameCatalog
{
    private const SEARCH_CACHE_TTL = 3600;

    public function __construct(
        private SteamCatalog $steam,
        private RawgCatalog $rawg,
        private CacheInterface $cache,
    ) {
    }

    public function preferred(): GameCatalogInterface
    {
        return $this->rawg->isConfigured() ? $this->rawg : $this->steam;
    }

    /**
     * @return list<CatalogGame>
     *
     * @throws CatalogUnavailableException
     */
    public function search(string $query): array
    {
        $catalog = $this->preferred();
        $key = \sprintf('catalog_search_%s_%s', $catalog->source(), md5(mb_strtolower(trim($query))));

        return $this->cache->get($key, function (ItemInterface $item) use ($catalog, $query): array {
            $item->expiresAfter(self::SEARCH_CACHE_TTL);

            return $catalog->search($query);
        });
    }

    /**
     * @throws CatalogUnavailableException
     * @throws \InvalidArgumentException   for an unknown source
     */
    public function details(string $source, string $externalId): ?CatalogGameDetails
    {
        return match ($source) {
            SteamCatalog::SOURCE => $this->steam->details($externalId),
            RawgCatalog::SOURCE => $this->rawg->isConfigured() ? $this->rawg->details($externalId) : throw new CatalogUnavailableException('RAWG is not configured.'),
            default => throw new \InvalidArgumentException(\sprintf('Unknown catalog "%s".', $source)),
        };
    }
}
