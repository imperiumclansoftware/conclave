<?php

namespace App\Repository;

use App\Entity\OAuthAccessToken;
use App\Entity\OAuthClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OAuthAccessToken>
 */
class OAuthAccessTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OAuthAccessToken::class);
    }

    public function findByHash(string $hash): ?OAuthAccessToken
    {
        return $this->createQueryBuilder('t')
            ->addSelect('c')
            ->innerJoin('t.client', 'c')
            ->where('t.tokenHash = :hash')->setParameter('hash', $hash)
            ->getQuery()->getOneOrNullResult();
    }

    /** Forgets the tokens expired for a while (kept a little for troubleshooting). */
    public function purgeExpired(\DateTimeImmutable $before): void
    {
        $this->createQueryBuilder('t')->delete()
            ->where('t.expiresAt < :before')->setParameter('before', $before)
            ->getQuery()->execute();
    }

    public function deleteForClient(OAuthClient $client): void
    {
        $this->createQueryBuilder('t')->delete()
            ->where('t.client = :client')->setParameter('client', $client)
            ->getQuery()->execute();
    }
}
