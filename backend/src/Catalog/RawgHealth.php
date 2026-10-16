<?php

namespace App\Catalog;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Last failure of RAWG (key refused, service down), for the leaders' dashboard: the search then
 * falls back to Steam without telling the members.
 */
final readonly class RawgHealth
{
    private const KEY = 'rawg_last_failure';

    public function __construct(
        #[Autowire(service: 'cache.shared')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function failed(string $reason): void
    {
        $item = $this->cache->getItem(self::KEY);
        $item->set(['reason' => $reason, 'at' => time()]);
        $this->cache->save($item);
    }

    public function succeeded(): void
    {
        $this->cache->deleteItem(self::KEY);
    }

    /** @return array{reason: string, at: int}|null */
    public function lastFailure(): ?array
    {
        $item = $this->cache->getItem(self::KEY);

        return $item->isHit() && \is_array($item->get()) ? $item->get() : null;
    }
}
