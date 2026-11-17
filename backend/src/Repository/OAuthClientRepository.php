<?php

namespace App\Repository;

use App\Entity\OAuthClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OAuthClient>
 */
class OAuthClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OAuthClient::class);
    }

    /** By database id or by client_id */
    public function findByReference(string $reference): ?OAuthClient
    {
        if (ctype_digit($reference)) {
            return $this->find((int) $reference);
        }

        return $this->findOneBy(['clientId' => $reference]);
    }
}
