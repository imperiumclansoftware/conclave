<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\BranchRequest;
use App\Entity\Clan;
use App\Entity\User;
use App\Enum\UserStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BranchRequest>
 */
class BranchRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BranchRequest::class);
    }

    /** @return list<BranchRequest> requests of active members for active branches, oldest first */
    public function pending(Clan $clan): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.branch', 'b')->addSelect('b')
            ->innerJoin('r.user', 'u')->addSelect('u')
            ->where('b.clan = :clan')->setParameter('clan', $clan)
            ->andWhere('b.active = true')
            ->andWhere('u.status = :active')->setParameter('active', UserStatus::Active)
            ->orderBy('r.id')
            ->getQuery()->getResult();
    }

    public function findOneFor(Branch $branch, User $user): ?BranchRequest
    {
        return $this->findOneBy(['branch' => $branch, 'user' => $user]);
    }
}
