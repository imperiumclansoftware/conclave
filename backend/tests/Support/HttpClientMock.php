<?php

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Stands in for every outgoing HTTP call in tests (see "framework.http_client.mock_response_factory"):
 * records Mercure updates and serves Steam store fixtures.
 */
final class HttpClientMock
{
    /** @var list<array<string, string|list<string>>> */
    public static array $published = [];

    /** Simulates a catalog outage. */
    public static bool $catalogDown = false;

    /** @var list<array{method: string, path: string, body: mixed}> requests sent to the Discord API */
    public static array $discord = [];

    /** @var array<string, true> Discord user ids that are members of the clan's server */
    public static array $guildMembers = [];

    /** E-mail addresses having a Gravatar */
    public const GRAVATAR_EMAILS = ['ezio@example.com'];
    public static bool $gravatarDown = false;

    /** Whether the fake Discord application has the presence intent enabled. */
    public static bool $presenceIntent = true;

    /** @var array<string, true> existing Discord channels */
    private static array $channels = [];
    private static int $nextChannelId = 5000;

    public static function reset(): void
    {
        self::$published = [];
        self::$catalogDown = false;
        self::$discord = [];
        self::$guildMembers = [];
        self::$channels = [];
        self::$presenceIntent = true;
        self::$gravatarDown = false;
    }

    /** @return list<array{method: string, path: string, body: mixed}> */
    public static function discordRequests(string $method, string $pathPattern): array
    {
        return array_values(array_filter(self::$discord, static fn (array $r): bool => $r['method'] === $method && preg_match($pathPattern, $r['path'])));
    }

    /** @return list<array> payloads of the Mercure updates of the given type */
    public static function publishedOfType(string $type): array
    {
        return array_values(array_filter(
            array_map(static fn (array $u): array => json_decode($u['data'], true) + ['_topics' => $u['topic']], self::$published),
            static fn (array $data): bool => $data['type'] === $type,
        ));
    }

    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        if (str_contains($url, '/.well-known/mercure')) {
            parse_str(preg_replace('/(^|&)topic=/', '$1topic[]=', $options['body'] ?? ''), $update);
            self::$published[] = $update;

            return new MockResponse('urn:uuid:'.bin2hex(random_bytes(8)));
        }

        if (str_starts_with($url, 'https://discord.com/api/v10/')) {
            return $this->discord($method, substr((string) parse_url($url, \PHP_URL_PATH), \strlen('/api/v10/')), $options);
        }

        if (str_starts_with($url, 'https://gravatar.com/avatar/')) {
            if (self::$gravatarDown) {
                return new MockResponse('', ['http_code' => 503]);
            }
            $hash = basename((string) parse_url($url, \PHP_URL_PATH));
            $known = array_map(static fn (string $e): string => hash('sha256', $e), self::GRAVATAR_EMAILS);

            return new MockResponse('', ['http_code' => \in_array($hash, $known, true) ? 200 : 404]);
        }

        if (str_starts_with($url, 'https://store.steampowered.com/')) {
            return self::$catalogDown ? new MockResponse('', ['http_code' => 503]) : $this->steam($url);
        }

        if (str_starts_with($url, 'https://api.themoviedb.org/3/')) {
            return self::$catalogDown ? new MockResponse('', ['http_code' => 503]) : $this->tmdb($url, $options);
        }

        throw new \LogicException(\sprintf('Unexpected HTTP request in tests: %s %s', $method, $url));
    }

    /** API key accepted by the fake TMDB (v3 format) */
    public const TMDB_KEY = '0123456789abcdef0123456789abcdef';

    /** @param array<string, mixed> $options */
    private function tmdb(string $url, array $options): ResponseInterface
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        if (self::TMDB_KEY !== ($query['api_key'] ?? null)) {
            return new JsonMockResponse(['status_message' => 'Invalid API key'], ['http_code' => 401]);
        }
        $path = substr((string) parse_url($url, \PHP_URL_PATH), \strlen('/3/'));

        return match (true) {
            'authentication' === $path => new JsonMockResponse(['success' => true]),
            'search/movie' === $path => new JsonMockResponse(['results' => str_contains(strtolower((string) ($query['query'] ?? '')), 'dune') ? [
                ['id' => 438631, 'title' => 'Dune', 'original_title' => 'Dune', 'release_date' => '2021-09-15', 'poster_path' => '/dune.jpg'],
                ['id' => 693134, 'title' => 'Dune : Deuxième partie', 'original_title' => 'Dune: Part Two', 'release_date' => '2024-02-27', 'poster_path' => null],
            ] : []]),
            'movie/438631' === $path => new JsonMockResponse([
                'id' => 438631, 'title' => 'Dune', 'original_title' => 'Dune', 'release_date' => '2021-09-15',
                'poster_path' => '/dune.jpg', 'backdrop_path' => '/dune-bg.jpg', 'overview' => 'Paul Atréides…', 'runtime' => 155,
                'genres' => [['id' => 878, 'name' => 'Science-Fiction'], ['id' => 12, 'name' => 'Aventure']],
                'credits' => [
                    'cast' => [['name' => 'Timothée Chalamet', 'character' => 'Paul Atréides'], ['name' => 'Zendaya', 'character' => 'Chani']],
                    'crew' => [['name' => 'Denis Villeneuve', 'job' => 'Director'], ['name' => 'Hans Zimmer', 'job' => 'Original Music Composer']],
                ],
            ]),
            default => new JsonMockResponse(['status_message' => 'Not found'], ['http_code' => 404]),
        };
    }

    private function steam(string $url): ResponseInterface
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

        if (str_contains($url, '/api/storesearch/')) {
            $items = str_contains(mb_strtolower($query['term'] ?? ''), 'elden') ? [
                ['type' => 'app', 'id' => 1245620, 'name' => 'ELDEN RING', 'tiny_image' => 'https://cdn.test/elden-small.jpg'],
                ['type' => 'app', 'id' => 2622380, 'name' => 'ELDEN RING NIGHTREIGN', 'tiny_image' => 'https://cdn.test/nightreign-small.jpg'],
                ['type' => 'app', 'id' => 2778590, 'name' => 'ELDEN RING - Official Soundtrack'],
                ['type' => 'bundle', 'id' => 1, 'name' => 'Not a game'],
            ] : [];

            return new JsonMockResponse(['total' => \count($items), 'items' => $items]);
        }

        $id = $query['appids'] ?? '';
        if ('1245620' !== $id) {
            return new JsonMockResponse([$id => ['success' => false]]);
        }

        return new JsonMockResponse([$id => ['success' => true, 'data' => [
            'name' => 'ELDEN RING',
            'short_description' => 'LE RPG D&#39;ACTION <b>FANTASTIQUE</b> acclamé.',
            'header_image' => 'https://cdn.test/elden-header.jpg',
            'screenshots' => [
                ['id' => 0, 'path_thumbnail' => 'https://cdn.test/ss1.600x338.jpg', 'path_full' => 'https://cdn.test/ss1.1920x1080.jpg'],
                ['id' => 1, 'path_thumbnail' => 'https://cdn.test/ss2.600x338.jpg', 'path_full' => 'https://cdn.test/ss2.1920x1080.jpg'],
            ],
            'genres' => [['id' => '1', 'description' => 'Action'], ['id' => '3', 'description' => 'RPG']],
            'developers' => ['FromSoftware, Inc.'],
            'release_date' => ['coming_soon' => false, 'date' => '24 févr. 2022'],
            'website' => null,
        ]]]);
    }

    /**
     * Fake Discord API. OAuth codes encode the Discord user: "code-<id>" links an account,
     * "install-<id>" also adds the bot to the server 900.
     */
    private function discord(string $method, string $path, array $options): ResponseInterface
    {
        $body = $options['body'] ?? null;
        if (\is_string($body) && str_starts_with($body, '{')) {
            $body = json_decode($body, true);
        } elseif (\is_string($body)) {
            parse_str($body, $body);
        }
        self::$discord[] = ['method' => $method, 'path' => $path, 'body' => $body];
        $auth = implode(' ', $options['normalized_headers']['authorization'] ?? []);

        switch (true) {
            case 'POST' === $method && 'oauth2/token' === $path:
                if (!preg_match('/^(code|install)-(\d+)$/', $body['code'] ?? '', $m)) {
                    return new JsonMockResponse(['error' => 'invalid_grant'], ['http_code' => 400]);
                }
                $token = ['access_token' => 'token-'.$m[2], 'token_type' => 'Bearer'];
                if ('install' === $m[1]) {
                    $token['guild'] = ['id' => '900', 'name' => 'Serveur du clan'];
                }

                return new JsonMockResponse($token);
            case 'GET' === $method && 'users/@me' === $path:
                if (str_contains($auth, 'Bot ')) {
                    return new JsonMockResponse(['id' => '42', 'username' => 'ClanBot']);
                }
                preg_match('/token-(\d+)/', $auth, $m);

                return new JsonMockResponse(['id' => $m[1], 'username' => 'user'.$m[1], 'global_name' => 'Discord '.$m[1], 'avatar' => 'abc']);
            case 'GET' === $method && 'oauth2/applications/@me' === $path:
                return new JsonMockResponse([
                    'id' => '123456789',
                    'name' => 'Clan App',
                    'flags' => self::$presenceIntent ? 1 << 12 : 0,
                    'redirect_uris' => ['https://app.test/discord/callback'],
                ]);
            case 'PUT' === $method && preg_match('#^guilds/\d+/members/(\d+)$#', $path, $m):
                self::$guildMembers[$m[1]] = true;

                return new MockResponse('', ['http_code' => 201]);
            case 'GET' === $method && preg_match('#^guilds/\d+/members/(\d+)$#', $path, $m):
                return isset(self::$guildMembers[$m[1]])
                    ? new JsonMockResponse(['user' => ['id' => $m[1]]])
                    : new JsonMockResponse(['message' => 'Unknown Member'], ['http_code' => 404]);
            case 'GET' === $method && preg_match('#^channels/(\d+)$#', $path, $m):
                return isset(self::$channels[$m[1]])
                    ? new JsonMockResponse(['id' => $m[1]])
                    : new JsonMockResponse(['message' => 'Unknown Channel'], ['http_code' => 404]);
            case 'POST' === $method && preg_match('#^guilds/\d+/channels$#', $path):
                $id = (string) self::$nextChannelId++;
                self::$channels[$id] = true;

                return new JsonMockResponse(['id' => $id] + $body, ['http_code' => 201]);
            case 'DELETE' === $method && preg_match('#^channels/(\d+)$#', $path, $m):
                unset(self::$channels[$m[1]]);

                return new JsonMockResponse(['id' => $m[1]]);
            case 'POST' === $method && 'users/@me/channels' === $path:
                return new JsonMockResponse(['id' => 'dm-'.$body['recipient_id']]);
            case 'POST' === $method && preg_match('#^channels/dm-\d+/messages$#', $path):
                return new JsonMockResponse(['id' => '1']);
            case 'DELETE' === $method && preg_match('#^users/@me/guilds/\d+$#', $path):
                return new MockResponse('', ['http_code' => 204]);
        }

        throw new \LogicException(\sprintf('Unexpected Discord request in tests: %s %s', $method, $path));
    }
}
