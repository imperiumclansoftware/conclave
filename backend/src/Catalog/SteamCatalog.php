<?php

namespace App\Catalog;

use App\Enum\GamePlatform;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Steam store public API: no key needed, French descriptions, but PC games only.
 */
final readonly class SteamCatalog implements GameCatalogInterface
{
    public const SOURCE = 'steam';

    /** Store items that are not games (Steam search does not tell them apart). */
    private const NOT_A_GAME = '/\b(soundtrack|OST|artbook|art book|wallpapers?)\b/i';

    public function __construct(
        #[Autowire(service: 'steam.client')]
        private HttpClientInterface $client,
    ) {
    }

    public function source(): string
    {
        return self::SOURCE;
    }

    public function search(string $query): array
    {
        $data = $this->get('/api/storesearch/', ['term' => $query, 'l' => 'french', 'cc' => 'FR']);

        $games = [];
        foreach ($data['items'] ?? [] as $item) {
            if ('app' !== ($item['type'] ?? null) || !isset($item['id'], $item['name']) || preg_match(self::NOT_A_GAME, $item['name'])) {
                continue;
            }
            $games[] = new CatalogGame(
                self::SOURCE,
                (string) $item['id'],
                $item['name'],
                $item['tiny_image'] ?? null,
                null,
                [GamePlatform::Pc],
            );
        }

        return $games;
    }

    public function details(string $externalId): ?CatalogGameDetails
    {
        $data = $this->get('/api/appdetails', ['appids' => $externalId, 'l' => 'french', 'cc' => 'FR']);
        $entry = $data[$externalId] ?? null;
        if (!($entry['success'] ?? false) || !isset($entry['data']['name'])) {
            return null;
        }
        $game = $entry['data'];

        return new CatalogGameDetails(
            name: $game['name'],
            coverUrl: $game['header_image'] ?? null,
            description: TextCleaner::plain($game['short_description'] ?? null),
            screenshots: array_values(array_map(
                static fn (array $s): array => ['thumbnail' => $s['path_thumbnail'], 'full' => $s['path_full']],
                array_filter($game['screenshots'] ?? [], static fn (array $s): bool => isset($s['path_thumbnail'], $s['path_full'])),
            )),
            genres: array_column($game['genres'] ?? [], 'description'),
            developers: array_values($game['developers'] ?? []),
            platforms: [GamePlatform::Pc],
            releaseDate: $game['release_date']['date'] ?? null ?: null,
            website: $game['website'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        try {
            return $this->client->request('GET', $path, ['query' => $query])->toArray();
        } catch (ExceptionInterface $e) {
            throw new CatalogUnavailableException('Steam store API error: '.$e->getMessage(), previous: $e);
        }
    }
}
