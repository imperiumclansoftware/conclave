<?php

namespace App\Films;

use App\Entity\Film;
use App\Enum\FilmKind;
use App\Repository\FilmRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/** Fiches of films and series kept locally: fetched from TMDB the first time, refreshed when stale. */
final readonly class FilmLibrary
{
    public function __construct(
        private TmdbClient $tmdb,
        private FilmRepository $films,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws FilmCatalogUnavailableException
     * @throws \DomainException                when TMDB does not know this film or series
     */
    public function findOrFetch(FilmKind $kind, int $tmdbId): Film
    {
        $film = $this->films->findOneBy(['kind' => $kind, 'tmdbId' => $tmdbId]);
        if (null === $film) {
            $details = $this->tmdb->details($kind, $tmdbId)
                ?? throw new \DomainException(FilmKind::Tv === $kind ? 'Cette série est introuvable sur TMDB.' : 'Ce film est introuvable sur TMDB.');
            $film = new Film($kind, $tmdbId, $details);
            $this->em->persist($film);

            return $film;
        }

        if ($film->isStale()) {
            try {
                if ($details = $this->tmdb->details($kind, $tmdbId)) {
                    $film->refresh($details);
                }
            } catch (FilmCatalogUnavailableException $e) {
                // Keep the cached fiche
                $this->logger->warning('Unable to refresh {kind} {id}: {error}', ['kind' => $kind->value, 'id' => $tmdbId, 'error' => $e->getMessage()]);
            }
        }

        return $film;
    }
}
