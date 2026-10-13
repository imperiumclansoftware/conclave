<?php

namespace App\Discord;

use App\Entity\User;
use Psr\Cache\CacheItemPoolInterface;

/**
 * OAuth2 authorization flows: linking a member's account, and adding the bot to the clan's server.
 */
final readonly class DiscordOAuth
{
    public const LINK = 'link';
    public const INSTALL = 'install';

    private const STATE_TTL = 600;

    public function __construct(
        private DiscordConfig $config,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function authorizeUrl(User $user, string $purpose): string
    {
        $state = bin2hex(random_bytes(16));
        $item = $this->cache->getItem($this->key($state));
        $item->set(['userId' => $user->getId(), 'purpose' => $purpose])->expiresAfter(self::STATE_TTL);
        $this->cache->save($item);

        $query = [
            'client_id' => $this->config->clientId(),
            'response_type' => 'code',
            'redirect_uri' => $this->config->redirectUri(),
            'state' => $state,
            'scope' => self::INSTALL === $purpose ? 'bot identify guilds.join' : 'identify guilds.join',
        ];
        if (self::INSTALL === $purpose) {
            $query['permissions'] = (string) DiscordPermission::BOT;
        } else {
            $query['prompt'] = 'none';
        }

        return 'https://discord.com/oauth2/authorize?'.http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * Checks the state returned by Discord (single use, bound to the user who started the flow).
     *
     * @return string|null the purpose of the flow, or null when the state is invalid
     */
    public function consumeState(User $user, string $state): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $state)) {
            return null;
        }

        $item = $this->cache->getItem($this->key($state));
        $data = $item->isHit() ? $item->get() : null;
        $this->cache->deleteItem($this->key($state));

        return \is_array($data) && $data['userId'] === $user->getId() ? $data['purpose'] : null;
    }

    private function key(string $state): string
    {
        return 'discord_oauth_'.$state;
    }
}
