<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Films\FilmCatalogUnavailableException;
use App\Films\FilmLibrary;
use App\Films\TmdbClient;
use App\Security\FilmsVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Film search and fiches (TMDB): the part of the Cinéphiles branch (see FilmsAccess).
 */
#[Route('/api/films', format: 'json')]
#[IsGranted(FilmsVoter::ACCESS, message: 'Les fiches de films sont réservées aux membres de la branche Cinéphiles.')]
final class FilmController extends AbstractController
{
    private const UNAVAILABLE = 'La recherche de films est indisponible : TMDB est injoignable ou sa clé n\'est pas configurée.';

    #[Route('/search', name: 'api_films_search', methods: ['GET'])]
    public function search(TmdbClient $tmdb, #[MapQueryParameter] string $q = ''): JsonResponse
    {
        if (mb_strlen(trim($q)) < 2) {
            return $this->json([]);
        }

        try {
            return $this->json($tmdb->search(trim($q)));
        } catch (FilmCatalogUnavailableException $e) {
            throw new ServiceUnavailableHttpException(null, self::UNAVAILABLE, $e);
        }
    }

    #[Route('/{tmdbId}', name: 'api_film_show', requirements: ['tmdbId' => Requirement::DIGITS], methods: ['GET'])]
    public function show(int $tmdbId, FilmLibrary $films, EntityManagerInterface $em, ApiPresenter $presenter): JsonResponse
    {
        try {
            $film = $films->findOrFetch($tmdbId);
            $em->flush();
        } catch (\DomainException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (FilmCatalogUnavailableException $e) {
            throw new ServiceUnavailableHttpException(null, self::UNAVAILABLE, $e);
        }

        return $this->json($presenter->filmDetails($film));
    }
}
