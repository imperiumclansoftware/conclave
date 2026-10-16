<?php

namespace App\Films;

use App\Enum\FilmKind;
use App\Settings\AppSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Movie Database (https://www.themoviedb.org), in French: films and TV series. The key is saved in the
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
     * Films and series matching the query, in TMDB's order of relevance (people are left out).
     *
     * @return list<array{kind: string, tmdbId: int, title: string, originalTitle: ?string, year: ?string, posterUrl: ?string}>
     *
     * @throws FilmCatalogUnavailableException
     */
    public function search(string $query): array
    {
        $results = $this->get('search/multi', ['query' => $query, 'include_adult' => 'false'])['results'] ?? [];
        $results = array_filter(\is_array($results) ? $results : [], static fn (mixed $r): bool => \is_array($r) && null !== FilmKind::tryFrom((string) ($r['media_type'] ?? '')));

        return array_values(array_map(static function (array $result): array {
            $series = FilmKind::Tv->value === $result['media_type'];
            $title = (string) ($series ? $result['name'] : $result['title']);
            $original = $result[$series ? 'original_name' : 'original_title'] ?? null;

            return [
                'kind' => (string) $result['media_type'],
                'tmdbId' => (int) $result['id'],
                'title' => $title,
                'originalTitle' => $original !== $title ? $original : null,
                'year' => self::year($result[$series ? 'first_air_date' : 'release_date'] ?? null),
                'posterUrl' => self::image($result['poster_path'] ?? null, 'w185'),
            ];
        }, \array_slice($results, 0, 12)));
    }

    /**
     * @return array<string, mixed>|null null when the film or the series does not exist
     *
     * @throws FilmCatalogUnavailableException
     */
    public function details(FilmKind $kind, int $tmdbId): ?array
    {
        try {
            return $this->get("{$kind->value}/$tmdbId", ['append_to_response' => 'credits']);
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
