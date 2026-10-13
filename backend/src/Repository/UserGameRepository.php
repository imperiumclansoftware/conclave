<?php

namespace App\Repository;

use App\Entity\Clan;
use App\Entity\Game;
use App\Entity\User;
use App\Entity\UserGame;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserGame>
 */
class UserGameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserGame::class);
    }

    /** @return list<UserGame> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('ug')
            ->addSelect('g')
            ->innerJoin('ug.game', 'g')
            ->where('ug.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<UserGame> every game played in the clan, with its players */
    public function findForClan(Clan $clan, ?Game $game = null): array
    {
        $qb = $this->createQueryBuilder('ug')
            ->addSelect('g', 'u')
            ->innerJoin('ug.game', 'g')
            ->innerJoin('ug.user', 'u')
            ->where('u.clan = :clan')
            ->setParameter('clan', $clan)
            ->orderBy('g.name', 'ASC')
            ->addOrderBy('u.displayName', 'ASC');

        if (null !== $game) {
            $qb->andWhere('ug.game = :game')->setParameter('game', $game);
        }

        return $qb->getQuery()->getResult();
    }

    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('ug')
            ->select('COUNT(ug.id)')
            ->where('ug.user = :user')->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();
    }
}
