<?php

namespace App\Films;

use App\Settings\AppSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Movie Database (https://www.themoviedb.org), in French. The key is saved in the
 * administration: an API key (v3) or an API read access token (v4) both work.
 */
final readonly class TmdbClient
{
    public const IMAGE_BASE = 'https://image.tmdb.org/t/p/';

    public function __construct(
        #[Autowire(service: 'tmdb.client')]
        private HttpClientInterface $client,
        private AppSettings $settings,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->settings->get(AppSettings::TMDB_API_KEY);
    }

    /**
     * @return list<array{tmdbId: int, title: string, originalTitle: ?string, year: ?string, posterUrl: ?string}>
     *
     * @throws FilmCatalogUnavailableException
     */
    public function search(string $query): array
    {
        $results = $this->get('search/movie', ['query' => $query, 'include_adult' => 'false'])['results'] ?? [];

        return array_values(array_map(static fn (array $film): array => [
            'tmdbId' => (int) $film['id'],
            'title' => (string) $film['title'],
            'originalTitle' => ($film['original_title'] ?? null) !== $film['title'] ? ($film['original_title'] ?? null) : null,
            'year' => self::year($film['release_date'] ?? null),
            'posterUrl' => self::image($film['poster_path'] ?? null, 'w185'),
        ], \array_slice(\is_array($results) ? $results : [], 0, 12)));
    }

    /**
     * @return array<string, mixed>|null null when the film does not exist
     *
     * @throws FilmCatalogUnavailableException
     */
    public function details(int $tmdbId): ?array
    {
        try {
            return $this->get("movie/$tmdbId", ['append_to_response' => 'credits']);
        } catch (FilmCatalogUnavailableException $e) {
            if (404 === $e->getCode()) {
                return null;
            }
            throw $e;
        }
    }

    /** Checks the key (administration). */
    public function test(): bool
    {
        try {
            $this->get('authentication', []);

            return true;
        } catch (FilmCatalogUnavailableException $e) {
            if (401 === $e->getCode()) {
                return false;
            }
            throw $e;
        }
    }

    public static function image(?string $path, string $size): ?string
    {
        return null !== $path && '' !== $path ? self::IMAGE_BASE.$size.$path : null;
    }

    public static function year(?string $date): ?string
    {
        return null !== $date && preg_match('/^\d{4}/', $date, $m) ? $m[0] : null;
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        $key = $this->settings->get(AppSettings::TMDB_API_KEY)
            ?? throw new FilmCatalogUnavailableException('Aucune clé TMDB n\'est configurée.');
        $options = ['query' => $query + ['language' => 'fr-FR']];
        // A read access token (v4) is a JWT; otherwise it is an API key (v3)
        if (str_starts_with($key, 'eyJ')) {
            $options['headers'] = ['Authorization' => 'Bearer '.$key];
        } else {
            $options['query']['api_key'] = $key;
        }

        try {
            $response = $this->client->request('GET', $path, $options);
            $status = $response->getStatusCode();
            if (200 !== $status) {
                throw new FilmCatalogUnavailableException("TMDB answered $status.", $status);
            }

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new FilmCatalogUnavailableException('TMDB est injoignable.', 0, $e);
        }
    }
}
