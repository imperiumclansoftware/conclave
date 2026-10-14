<?php

namespace App\Entity;

use App\Films\TmdbClient;
use App\Repository\FilmRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Film fiche from TMDB, kept for the events (watch parties) and refreshed now and then.
 */
#[ORM\Entity(repositoryClass: FilmRepository::class)]
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

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $releaseDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $posterPath = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $backdropPath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $overview = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $genres = [];

    #[ORM\Column(nullable: true)]
    private ?int $runtime = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $directors = [];

    /** @var list<array{name: string, character: ?string}> ("cast" is a reserved SQL word) */
    #[ORM\Column(name: 'film_cast', type: Types::JSON)]
    private array $cast = [];

    #[ORM\Column]
    private \DateTimeImmutable $fetchedAt;

    /**
     * @param array<string, mixed> $details TMDB "movie" payload, with its credits
     */
    public function __construct(
        #[ORM\Column(unique: true)]
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
        $this->title = mb_substr((string) $details['title'], 0, 255);
        $original = $details['original_title'] ?? null;
        $this->originalTitle = \is_string($original) && $original !== $details['title'] ? mb_substr($original, 0, 255) : null;
        $this->releaseDate = \is_string($details['release_date'] ?? null) && '' !== $details['release_date'] ? substr($details['release_date'], 0, 10) : null;
        $this->posterPath = $details['poster_path'] ?? null;
        $this->backdropPath = $details['backdrop_path'] ?? null;
        $this->overview = ($details['overview'] ?? '') ?: null;
        $this->genres = array_column($details['genres'] ?? [], 'name');
        $this->runtime = ($details['runtime'] ?? 0) ?: null;
        $crew = $details['credits']['crew'] ?? [];
        $this->directors = array_values(array_unique(array_column(array_filter($crew, static fn (array $c): bool => 'Director' === ($c['job'] ?? null)), 'name')));
        $this->cast = array_map(
            static fn (array $c): array => ['name' => (string) $c['name'], 'character' => ($c['character'] ?? '') ?: null],
            \array_slice($details['credits']['cast'] ?? [], 0, 8),
        );
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function isStale(): bool
    {
        return $this->fetchedAt < new \DateTimeImmutable(self::STALE_AFTER);
    }

    public function getId(): ?int
    {
        return $this->id;
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
