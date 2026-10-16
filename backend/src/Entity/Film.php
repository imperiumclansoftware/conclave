<?php

namespace App\Entity;

use App\Enum\FilmKind;
use App\Films\TmdbClient;
use App\Repository\FilmRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fiche of a film or a TV series from TMDB, kept for the events (watch parties, ciné-clubs)
 * and refreshed now and then. TMDB numbers films and series separately: a fiche is identified
 * by its kind and its TMDB identifier.
 */
#[ORM\Entity(repositoryClass: FilmRepository::class)]
#[ORM\UniqueConstraint(name: 'film_kind_tmdb', columns: ['kind', 'tmdb_id'])]
class Film
{
    /** Fiches older than this are refreshed when used again */
    private const STALE_AFTER = '-30 days';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalTitle = null;

    /** Release date of a film, first air date of a series */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $releaseDate = null;

    /** Series: last air date once it has ended, null while it goes on */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $endDate = null;

    /** Series: still running (new seasons to come) */
    #[ORM\Column(nullable: true)]
    private ?bool $ongoing = null;

    #[ORM\Column(nullable: true)]
    private ?int $seasonCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $episodeCount = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $posterPath = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $backdropPath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $overview = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $genres = [];

    /** Minutes: the film, or an episode of the series */
    #[ORM\Column(nullable: true)]
    private ?int $runtime = null;

    /** @var list<string> directors of a film, creators of a series */
    #[ORM\Column(type: Types::JSON)]
    private array $directors = [];

    /** @var list<array{name: string, character: ?string}> ("cast" is a reserved SQL word) */
    #[ORM\Column(name: 'film_cast', type: Types::JSON)]
    private array $cast = [];

    #[ORM\Column]
    private \DateTimeImmutable $fetchedAt;

    /**
     * @param array<string, mixed> $details TMDB "movie" or "tv" payload, with its credits
     */
    public function __construct(
        #[ORM\Column(length: 10, enumType: FilmKind::class, options: ['default' => 'movie'])]
        private FilmKind $kind,
        #[ORM\Column]
        private int $tmdbId,
        array $details,
    ) {
        $this->refresh($details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public function refresh(array $details): void
    {
        // Series have a name and air dates where films have a title and a release date
        $series = FilmKind::Tv === $this->kind;
        $title = (string) ($series ? $details['name'] : $details['title']);
        $original = $details[$series ? 'original_name' : 'original_title'] ?? null;
        $this->title = mb_substr($title, 0, 255);
        $this->originalTitle = \is_string($original) && $original !== $title ? mb_substr($original, 0, 255) : null;
        $this->releaseDate = self::date($details[$series ? 'first_air_date' : 'release_date'] ?? null);
        $this->posterPath = $details['poster_path'] ?? null;
        $this->backdropPath = $details['backdrop_path'] ?? null;
        $this->overview = ($details['overview'] ?? '') ?: null;
        $this->genres = array_column($details['genres'] ?? [], 'name');
        $this->cast = array_map(
            static fn (array $c): array => ['name' => (string) $c['name'], 'character' => ($c['character'] ?? '') ?: null],
            \array_slice($details['credits']['cast'] ?? [], 0, 8),
        );

        if ($series) {
            $this->runtime = ($details['episode_run_time'][0] ?? $details['last_episode_to_air']['runtime'] ?? 0) ?: null;
            $this->directors = array_values(array_unique(array_column($details['created_by'] ?? [], 'name')));
            $this->ongoing = (bool) ($details['in_production'] ?? false);
            $this->endDate = $this->ongoing ? null : self::date($details['last_air_date'] ?? null);
            $this->seasonCount = ($details['number_of_seasons'] ?? 0) ?: null;
            $this->episodeCount = ($details['number_of_episodes'] ?? 0) ?: null;
        } else {
            $this->runtime = ($details['runtime'] ?? 0) ?: null;
            $crew = $details['credits']['crew'] ?? [];
            $this->directors = array_values(array_unique(array_column(array_filter($crew, static fn (array $c): bool => 'Director' === ($c['job'] ?? null)), 'name')));
        }
        $this->fetchedAt = new \DateTimeImmutable();
    }

    private static function date(mixed $date): ?string
    {
        return \is_string($date) && '' !== $date ? substr($date, 0, 10) : null;
    }

    public function isStale(): bool
    {
        return $this->fetchedAt < new \DateTimeImmutable(self::STALE_AFTER);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKind(): FilmKind
    {
        return $this->kind;
    }

    public function getTmdbId(): int
    {
        return $this->tmdbId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getOriginalTitle(): ?string
    {
        return $this->originalTitle;
    }

    public function getReleaseDate(): ?string
    {
        return $this->releaseDate;
    }

    public function getYear(): ?string
    {
        return TmdbClient::year($this->releaseDate);
    }

    /** Series: year of the last episode once it has ended */
    public function getEndYear(): ?string
    {
        return TmdbClient::year($this->endDate);
    }

    public function isOngoing(): ?bool
    {
        return $this->ongoing;
    }

    public function getSeasonCount(): ?int
    {
        return $this->seasonCount;
    }

    public function getEpisodeCount(): ?int
    {
        return $this->episodeCount;
    }

    public function getPosterUrl(string $size = 'w342'): ?string
    {
        return TmdbClient::image($this->posterPath, $size);
    }

    public function getBackdropUrl(): ?string
    {
        return TmdbClient::image($this->backdropPath, 'w1280');
    }

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    /** @return list<string> */
    public function getGenres(): array
    {
        return $this->genres;
    }

    public function getRuntime(): ?int
    {
        return $this->runtime;
    }

    /** @return list<string> */
    public function getDirectors(): array
    {
        return $this->directors;
    }

    /** @return list<array{name: string, character: ?string}> */
    public function getCast(): array
    {
        return $this->cast;
    }
}
