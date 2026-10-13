<?php

namespace App\Discord\Gateway;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * State of the gateway worker, shared with the web process through the database cache
 * (shown in the administration; the worker may run in another container).
 */
final readonly class GatewayStatus
{
    private const KEY = 'discord_gateway_status';
    /** Without news for this long, the worker is considered stopped. */
    private const STALE_AFTER = 120;

    public function __construct(
        #[Autowire(service: 'cache.shared')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param 'connecting'|'connected'|'waiting-config'|'error'|'stopped' $state
     */
    public function report(string $state, ?string $detail = null, ?string $botName = null, ?int $guilds = null): void
    {
        $previous = $this->read();
        $item = $this->cache->getItem(self::KEY);
        $item->set([
            'state' => $state,
            'detail' => $detail,
            'botName' => $botName ?? ('connected' === $state ? $previous['botName'] ?? null : null),
            'guilds' => $guilds ?? ('connected' === $state ? $previous['guilds'] ?? null : null),
            'at' => time(),
        ]);
        $this->cache->save($item);
    }

    /**
     * @return array{state: string, detail: ?string, botName: ?string, guilds: ?int, at: ?int}
     */
    public function read(): array
    {
        $item = $this->cache->getItem(self::KEY);
        $status = $item->isHit() ? $item->get() : null;
        if (!\is_array($status) || time() - $status['at'] > self::STALE_AFTER) {
            return ['state' => 'stopped', 'detail' => null, 'botName' => null, 'guilds' => null, 'at' => $status['at'] ?? null];
        }

        return $status;
    }
}
