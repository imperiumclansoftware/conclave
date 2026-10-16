<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\EventMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventMessage>
 */
class EventMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventMessage::class);
    }

    /**
     * A page of the event's messages in chronological order: the latest ones, or those preceding
     * the message $beforeId when paginating backwards.
     *
     * @return list<EventMessage>
     */
    public function findPage(Event $event, ?int $beforeId, int $limit): array
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('a')
            ->leftJoin('m.author', 'a')
            ->where('m.event = :event')
            ->setParameter('event', $event)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $beforeId) {
            $qb->andWhere('m.id < :beforeId')->setParameter('beforeId', $beforeId);
        }

        return array_reverse($qb->getQuery()->getResult());
    }
}
