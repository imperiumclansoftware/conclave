<?php

namespace App\Catalog;

use App\Enum\GamePlatform;
use App\Settings\AppSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * RAWG video games database (https://rawg.io/apidocs): all platforms, requires a free API key.
 */
final readonly class RawgCatalog implements GameCatalogInterface
{
    public const SOURCE = 'rawg';

    /** RAWG platform slugs => our platforms */
    private const PLATFORMS = [
        'pc' => GamePlatform::Pc,
        'macos' => GamePlatform::Pc,
        'linux' => GamePlatform::Pc,
        'playstation5' => GamePlatform::Ps5,
        'playstation4' => GamePlatform::Ps4,
        'xbox-series-x' => GamePlatform::XboxSeries,
        'xbox-one' => GamePlatform::XboxOne,
        'nintendo-switch-2' => GamePlatform::Switch2,
        'nintendo-switch' => GamePlatform::Switch,
        'ios' => GamePlatform::Mobile,
        'android' => GamePlatform::Mobile,
    ];

    public function __construct(
        #[Autowire(service: 'rawg.client')]
        private HttpClientInterface $client,
        private AppSettings $settings,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->settings->get(AppSettings::RAWG_API_KEY);
    }

    public function source(): string
    {
        return self::SOURCE;
    }

    public function search(string $query): array
    {
        $data = $this->get('games', ['search' => $query, 'page_size' => 12, 'search_precise' => 'true']);

        return array_values(array_map(
            fn (array $game): CatalogGame => new CatalogGame(
                self::SOURCE,
                (string) $game['id'],
                $game['name'],
                $game['background_image'] ?? null,
                $game['released'] ?? null,
                $this->platforms($game['platforms'] ?? []),
            ),
            array_filter($data['results'] ?? [], static fn (array $g): bool => isset($g['id'], $g['name'])),
        ));
    }

    public function details(string $externalId): ?CatalogGameDetails
    {
        try {
            $game = $this->get("games/$externalId", []);
        } catch (CatalogUnavailableException $e) {
            if (404 === $e->getCode()) {
                return null;
            }
            throw $e;
        }
        $screenshots = $this->get("games/$externalId/screenshots", ['page_size' => 12]);

        return new CatalogGameDetails(
            name: $game['name'],
            coverUrl: $game['background_image'] ?? null,
            description: TextCleaner::plain($game['description_raw'] ?? $game['description'] ?? null),
            screenshots: array_values(array_map(
                static fn (array $s): array => ['thumbnail' => self::resized($s['image']), 'full' => $s['image']],
                array_filter($screenshots['results'] ?? [], static fn (array $s): bool => isset($s['image'])),
            )),
            genres: array_column($game['genres'] ?? [], 'name'),
            developers: array_column($game['developers'] ?? [], 'name'),
            platforms: $this->platforms($game['platforms'] ?? []),
            releaseDate: $game['released'] ?? null,
            website: ($game['website'] ?? '') ?: null,
        );
    }

    /**
     * @param list<array<string, mixed>> $platforms
     *
     * @return list<GamePlatform>
     */
    private function platforms(array $platforms): array
    {
        $values = [];
        foreach ($platforms as $entry) {
            $platform = self::PLATFORMS[$entry['platform']['slug'] ?? ''] ?? null;
            if (null !== $platform) {
                $values[] = $platform->value;
            }
        }

        return GamePlatform::fromValues($values);
    }

    /** RAWG serves resized images under /media/resize/{width}/-/ */
    private static function resized(string $url): string
    {
        return str_replace('/media/screenshots/', '/media/resize/640/-/screenshots/', $url);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        try {
            $response = $this->client->request('GET', $path, ['query' => $query + ['key' => $this->settings->get(AppSettings::RAWG_API_KEY)]]);
            if (404 === $response->getStatusCode()) {
                throw new CatalogUnavailableException('RAWG: not found', 404);
            }

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new CatalogUnavailableException('RAWG API error: '.$e->getMessage(), previous: $e);
        }
    }
}
