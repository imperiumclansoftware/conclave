<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Catalog\CatalogGame;
use App\Catalog\CatalogUnavailableException;
use App\Catalog\GameCatalog;
use App\Catalog\GameLibrary;
use App\Catalog\GameNotFoundException;
use App\Dto\AddGameRequest;
use App\Dto\UpdateGamePlatformsRequest;
use App\Entity\Game;
use App\Entity\User;
use App\Entity\UserGame;
use App\Enum\GamePlatform;
use App\Repository\UserGameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api', format: 'json')]
final class GameController extends AbstractController
{
    private const CATALOG_UNAVAILABLE = 'Le service d\'informations sur les jeux est indisponible, réessayez plus tard.';

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly UserGameRepository $userGames,
    ) {
    }

    #[Route('/games/platforms', name: 'api_game_platforms', methods: ['GET'])]
    public function platforms(): JsonResponse
    {
        return $this->json(array_map(
            static fn (GamePlatform $p): array => ['value' => $p->value, 'label' => $p->label()],
            GamePlatform::cases(),
        ));
    }

    /**
     * Searches the external catalog (Steam, or RAWG when configured).
     */
    #[Route('/games/search', name: 'api_game_search', methods: ['GET'])]
    public function search(#[CurrentUser] User $user, GameCatalog $catalog, #[MapQueryParameter] string $q = ''): JsonResponse
    {
        $query = trim($q);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
            return $this->json([]);
        }

        try {
            $results = $catalog->search($query);
        } catch (CatalogUnavailableException $e) {
            throw new ServiceUnavailableHttpException(null, self::CATALOG_UNAVAILABLE, $e);
        }

        $owned = [];
        foreach ($this->userGames->findForUser($user) as $userGame) {
            $owned[$userGame->getGame()->getSource().':'.$userGame->getGame()->getExternalId()] = true;
        }

        return $this->json(array_map(
            fn (CatalogGame $game): array => $this->presenter->catalogGame($game, isset($owned[$game->source.':'.$game->externalId])),
            $results,
        ));
    }

    /**
     * Game details, with the clan members who play it.
     */
    #[Route('/games/{id}', name: 'api_game_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(Game $game, #[CurrentUser] User $user): JsonResponse
    {
        $players = $this->userGames->findForClan($user->getClan(), $game);
        $mine = array_find($players, static fn (UserGame $ug): bool => $ug->getUser()->getId() === $user->getId());

        return $this->json($this->presenter->gameDetails($game) + [
            'players' => array_map($this->presenter->player(...), $players),
            'mine' => $mine ? $this->presenter->userGame($mine) : null,
        ]);
    }

    #[Route('/me/games', name: 'api_my_games', methods: ['GET'])]
    public function myGames(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(array_map($this->presenter->userGame(...), $this->userGames->findForUser($user)));
    }

    #[Route('/me/games', name: 'api_my_games_add', methods: ['POST'])]
    public function add(#[CurrentUser] User $user, #[MapRequestPayload] AddGameRequest $request, GameLibrary $library): JsonResponse
    {
        try {
            $userGame = $library->add($user, $request->source, $request->externalId, self::platformsFrom($request->platforms));
        } catch (CatalogUnavailableException $e) {
            throw new ServiceUnavailableHttpException(null, self::CATALOG_UNAVAILABLE, $e);
        } catch (GameNotFoundException $e) {
            throw new UnprocessableEntityHttpException('Ce jeu est introuvable.', $e);
        } catch (\DomainException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return $this->json($this->presenter->userGame($userGame), Response::HTTP_CREATED);
    }

    #[Route('/me/games/{id}', name: 'api_my_games_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function update(
        int $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] UpdateGamePlatformsRequest $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $userGame = $this->findMine($id, $user);
        $userGame->setPlatforms(self::platformsFrom($request->platforms));
        $em->flush();

        return $this->json($this->presenter->userGame($userGame));
    }

    #[Route('/me/games/{id}', name: 'api_my_games_remove', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function remove(int $id, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $em->remove($this->findMine($id, $user));
        $em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * The clan's library: every game played in the clan, with its players.
     */
    #[Route('/clan/games', name: 'api_clan_games', methods: ['GET'])]
    public function clanGames(#[CurrentUser] User $user): JsonResponse
    {
        $library = [];
        foreach ($this->userGames->findForClan($user->getClan()) as $userGame) {
            $gameId = $userGame->getGame()->getId();
            $library[$gameId] ??= ['game' => $this->presenter->game($userGame->getGame()), 'players' => []];
            $library[$gameId]['players'][] = $this->presenter->player($userGame);
        }

        // Most played games first
        usort($library, static fn (array $a, array $b): int => [\count($b['players']), $a['game']['name']] <=> [\count($a['players']), $b['game']['name']]);

        return $this->json($library);
    }

    private function findMine(int $id, User $user): UserGame
    {
        return $this->userGames->findOneBy(['id' => $id, 'user' => $user])
            ?? throw $this->createNotFoundException('Jeu introuvable.');
    }

    /**
     * @param list<string> $values validated GamePlatform values
     *
     * @return list<GamePlatform>
     */
    private static function platformsFrom(array $values): array
    {
        return GamePlatform::fromValues($values);
    }
}
