<?php

namespace App\Films;

use App\Entity\Film;
use App\Repository\FilmRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/** Film fiches kept locally: fetched from TMDB the first time, refreshed when stale. */
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
     * @throws \DomainException                when TMDB does not know this film
     */
    public function findOrFetch(int $tmdbId): Film
    {
        $film = $this->films->findOneBy(['tmdbId' => $tmdbId]);
        if (null === $film) {
            $details = $this->tmdb->details($tmdbId) ?? throw new \DomainException('Ce film est introuvable sur TMDB.');
            $film = new Film($tmdbId, $details);
            $this->em->persist($film);

            return $film;
        }

        if ($film->isStale()) {
            try {
                if ($details = $this->tmdb->details($tmdbId)) {
                    $film->refresh($details);
                }
            } catch (FilmCatalogUnavailableException $e) {
                // Keep the cached fiche
                $this->logger->warning('Unable to refresh film {id}: {error}', ['id' => $tmdbId, 'error' => $e->getMessage()]);
            }
        }

        return $film;
    }
}
