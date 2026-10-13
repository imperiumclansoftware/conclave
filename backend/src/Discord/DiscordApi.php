<?php

namespace App\Discord;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Discord REST API, as the bot (or with a user's OAuth access token where noted).
 */
class DiscordApi
{
    public const CHANNEL_VOICE = 2;
    public const CHANNEL_CATEGORY = 4;
    public const OVERWRITE_ROLE = 0;
    public const OVERWRITE_MEMBER = 1;

    /** @var array{id: string, username: string}|null */
    private ?array $botUser = null;

    public function __construct(
        #[Autowire(service: 'discord.client')]
        private readonly HttpClientInterface $client,
        private readonly DiscordConfig $config,
    ) {
    }

    // ---- OAuth2 (user tokens) ----

    /** @return array{access_token: string, guild?: array{id: string, name: string}} */
    public function exchangeCode(string $code): array
    {
        return $this->decode($this->client->request('POST', 'oauth2/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->config->redirectUri(),
                'client_id' => $this->config->clientId(),
                'client_secret' => $this->config->clientSecret(),
            ],
        ]));
    }

    /** @return array{id: string, username: string, global_name?: ?string, avatar?: ?string} */
    public function currentUser(string $accessToken): array
    {
        return $this->decode($this->client->request('GET', 'users/@me', ['auth_bearer' => $accessToken]));
    }

    /** Adds the user to the server (requires the "guilds.join" scope). */
    public function addGuildMember(string $guildId, string $userId, string $accessToken): void
    {
        $this->bot('PUT', "guilds/$guildId/members/$userId", ['json' => ['access_token' => $accessToken]]);
    }

    // ---- Bot ----

    /** @return array{id: string, username: string} */
    public function botUser(): array
    {
        return $this->botUser ??= $this->bot('GET', 'users/@me');
    }

    /**
     * The application owning the bot (flags tell which privileged intents are enabled).
     *
     * @return array<string, mixed>
     */
    public function application(): array
    {
        return $this->bot('GET', 'oauth2/applications/@me');
    }

    /** @return array{id: string, name: string} */
    public function guild(string $guildId): array
    {
        return $this->bot('GET', "guilds/$guildId");
    }

    public function isGuildMember(string $guildId, string $userId): bool
    {
        return null !== $this->bot('GET', "guilds/$guildId/members/$userId", notFoundAsNull: true);
    }

    /** @return array<string, mixed>|null */
    public function channel(string $channelId): ?array
    {
        return $this->bot('GET', "channels/$channelId", notFoundAsNull: true);
    }

    /**
     * @param array<string, mixed> $channel
     *
     * @return array{id: string}
     */
    public function createChannel(string $guildId, array $channel): array
    {
        return $this->bot('POST', "guilds/$guildId/channels", ['json' => $channel]);
    }

    public function deleteChannel(string $channelId): void
    {
        $this->bot('DELETE', "channels/$channelId", notFoundAsNull: true);
    }

    public function leaveGuild(string $guildId): void
    {
        $this->bot('DELETE', "users/@me/guilds/$guildId", notFoundAsNull: true);
    }

    /** Sends a direct message with a link button. */
    public function sendDirectMessage(string $userId, string $content, string $buttonLabel, string $buttonUrl): void
    {
        $dm = $this->bot('POST', 'users/@me/channels', ['json' => ['recipient_id' => $userId]]);
        $this->bot('POST', "channels/{$dm['id']}/messages", ['json' => [
            'content' => $content,
            'components' => [['type' => 1, 'components' => [
                ['type' => 2, 'style' => 5, 'label' => $buttonLabel, 'url' => $buttonUrl],
            ]]],
        ]]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    private function bot(string $method, string $path, array $options = [], bool $notFoundAsNull = false): ?array
    {
        $options['headers']['Authorization'] = 'Bot '.$this->config->botToken();

        return $this->decode($this->client->request($method, $path, $options), $notFoundAsNull);
    }

    /** @return array<string, mixed>|null */
    private function decode(ResponseInterface $response, bool $notFoundAsNull = false): ?array
    {
        try {
            $status = $response->getStatusCode();
            if ($notFoundAsNull && 404 === $status) {
                return null;
            }
            if ($status >= 400) {
                throw new DiscordApiException(\sprintf('Discord API error %d: %s', $status, $response->getContent(false)), $status);
            }

            return 204 === $status ? [] : $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new DiscordApiException('Discord API unreachable: '.$e->getMessage(), 0, $e);
        }
    }
}
