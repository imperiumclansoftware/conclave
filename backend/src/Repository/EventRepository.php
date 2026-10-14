<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Clan;
use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    private const PAST_LIMIT = 50;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Events of the active branches: upcoming (and in progress) from the soonest, or past from
     * the latest.
     *
     * @return list<Event>
     */
    public function forClan(Clan $clan, bool $past, ?Branch $branch = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.branch', 'b')->addSelect('b')
            ->leftJoin('e.participants', 'p')->addSelect('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('e.game', 'g')->addSelect('g')
            ->leftJoin('e.film', 'f')->addSelect('f')
            ->where('b.clan = :clan')->setParameter('clan', $clan)
            ->andWhere('b.active = true')
            ->andWhere($past ? 'COALESCE(e.endsAt, e.startsAt) < :now' : 'COALESCE(e.endsAt, e.startsAt) >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.startsAt', $past ? 'DESC' : 'ASC');
        if (null !== $branch) {
            $qb->andWhere('e.branch = :branch')->setParameter('branch', $branch);
        }
        $events = $qb->getQuery()->getResult();

        // The joins prevent setMaxResults: limit the past ones here
        return $past ? \array_slice($events, 0, self::PAST_LIMIT) : $events;
    }

    /**
     * Events of the active branches taking place, even partly, between two instants (planning).
     *
     * @return list<Event>
     */
    public function between(Clan $clan, \DateTimeImmutable $from, \DateTimeImmutable $to, ?Branch $branch = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.branch', 'b')->addSelect('b')
            ->leftJoin('e.participants', 'p')->addSelect('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('e.game', 'g')->addSelect('g')
            ->leftJoin('e.film', 'f')->addSelect('f')
            ->where('b.clan = :clan')->setParameter('clan', $clan)
            ->andWhere('b.active = true')
            ->andWhere('e.startsAt < :to')->setParameter('to', $to)
            ->andWhere('COALESCE(e.endsAt, e.startsAt) >= :from')->setParameter('from', $from)
            ->orderBy('e.startsAt', 'ASC');
        if (null !== $branch) {
            $qb->andWhere('e.branch = :branch')->setParameter('branch', $branch);
        }

        return $qb->getQuery()->getResult();
    }
}
