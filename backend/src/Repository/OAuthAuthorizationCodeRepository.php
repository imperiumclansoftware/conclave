<?php

namespace App\Repository;

use App\Entity\OAuthAuthorizationCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OAuthAuthorizationCode>
 */
class OAuthAuthorizationCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OAuthAuthorizationCode::class);
    }

    public function purgeExpired(\DateTimeImmutable $before): void
    {
        $this->createQueryBuilder('c')->delete()
            ->where('c.expiresAt < :before')->setParameter('before', $before)
            ->getQuery()->execute();
    }
}
