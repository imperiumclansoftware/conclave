<?php

namespace App\Catalog;

use App\Entity\Game;
use App\Entity\User;
use App\Entity\UserGame;
use App\Enum\GamePlatform;
use App\Repository\GameRepository;
use App\Repository\UserGameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages the games played by members, fetching their description from the catalogs.
 */
final readonly class GameLibrary
{
    public function __construct(
        private GameCatalog $catalog,
        private GameRepository $games,
        private UserGameRepository $userGames,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<GamePlatform> $platforms
     *
     * @throws CatalogUnavailableException
     * @throws GameNotFoundException
     * @throws \DomainException            when the user already has this game
     */
    public function add(User $user, string $source, string $externalId, array $platforms): UserGame
    {
        $game = $this->findOrFetch($source, $externalId);

        if ($this->userGames->findOneBy(['user' => $user, 'game' => $game])) {
            throw new \DomainException(\sprintf('« %s » est déjà dans vos jeux.', $game->getName()));
        }

        $userGame = new UserGame($user, $game, $platforms);
        $this->em->persist($userGame);
        $this->em->flush();

        return $userGame;
    }

    private function findOrFetch(string $source, string $externalId): Game
    {
        $game = $this->games->findBySource($source, $externalId);

        if (null === $game) {
            $details = $this->catalog->details($source, $externalId) ?? throw new GameNotFoundException();
            $game = new Game($source, $externalId, $details);
            $this->em->persist($game);

            return $game;
        }

        if ($game->isStale()) {
            try {
                if ($details = $this->catalog->details($source, $externalId)) {
                    $game->refresh($details);
                }
            } catch (CatalogUnavailableException $e) {
                // Keep the cached description
                $this->logger->warning('Unable to refresh game {id}: {error}', ['id' => $game->getId(), 'error' => $e->getMessage()]);
            }
        }

        return $game;
    }
}
