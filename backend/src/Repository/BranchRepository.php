<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Clan;
use App\Entity\User;
use App\Enum\BranchCrest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Branch>
 */
class BranchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Branch::class);
    }

    /** @return list<Branch> by name, with their members */
    public function findByClan(Clan $clan, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.members', 'm')->addSelect('m')
            ->where('b.clan = :clan')->setParameter('clan', $clan)
            ->orderBy('LOWER(b.name)');
        if ($activeOnly) {
            $qb->andWhere('b.active = true');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<Branch> the active branches the member belongs to, by name */
    public function findForMember(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->innerJoin('b.members', 'm', 'WITH', 'm = :user')->setParameter('user', $user)
            ->where('b.active = true')
            ->orderBy('LOWER(b.name)')
            ->getQuery()->getResult();
    }

    /** Whether the member belongs to an active branch whose members publish the news. */
    public function publishesNews(User $user): bool
    {
        return (bool) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->innerJoin('b.members', 'm', 'WITH', 'm = :user')->setParameter('user', $user)
            ->andWhere('b.publishesNews = true')
            ->andWhere('b.active = true')
            ->getQuery()->getSingleScalarResult();
    }

    /** Whether the member belongs to the active branch with this coat of arms (e.g. the Joueurs). */
    public function isMemberOf(User $user, BranchCrest $crest): bool
    {
        return (bool) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->innerJoin('b.members', 'm', 'WITH', 'm = :user')->setParameter('user', $user)
            ->andWhere('b.crest = :crest')->setParameter('crest', $crest)
            ->andWhere('b.active = true')
            ->getQuery()->getSingleScalarResult();
    }

    /** Another branch of the clan with the same name, ignoring case. */
    public function findOneByName(Clan $clan, string $name, ?Branch $except = null): ?Branch
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.clan = :clan')->setParameter('clan', $clan)
            ->andWhere('LOWER(b.name) = LOWER(:name)')->setParameter('name', $name)
            ->setMaxResults(1);
        if (null !== $except) {
            $qb->andWhere('b != :except')->setParameter('except', $except);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
