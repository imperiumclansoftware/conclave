<?php

namespace App\Repository;

use App\Entity\Clan;
use App\Entity\News;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<News>
 */
class NewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }

    /**
     * Most recent first.
     *
     * @return list<News>
     */
    public function page(Clan $clan, ?int $before, int $limit): array
    {
        $qb = $this->createQueryBuilder('n')
            ->leftJoin('n.author', 'a')->addSelect('a')
            ->where('n.clan = :clan')->setParameter('clan', $clan)
            ->orderBy('n.id', 'DESC')
            ->setMaxResults($limit);
        if (null !== $before) {
            $qb->andWhere('n.id < :before')->setParameter('before', $before);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByAuthor(User $user): int
    {
        return $this->count(['author' => $user]);
    }
}
