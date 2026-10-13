<?php

namespace App\Repository;

use App\Entity\Clan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Clan>
 */
class ClanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Clan::class);
    }

    /** The clan of the app (the app serves a single clan, created by the first account). */
    public function findMain(): ?Clan
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }
}
