<?php

namespace App\Repository;

use App\Entity\BranchLink;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BranchLink>
 */
class BranchLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BranchLink::class);
    }

    /** @return list<BranchLink> links of the member's active branches, oldest first */
    public function forMember(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.branch', 'b')->addSelect('b')
            ->innerJoin('b.members', 'm', 'WITH', 'm = :user')->setParameter('user', $user)
            ->where('b.active = true')
            ->orderBy('l.id')
            ->getQuery()->getResult();
    }
}
