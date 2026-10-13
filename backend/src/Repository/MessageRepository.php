<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Returns a page of messages in chronological order: the latest ones,
     * or those preceding the message $beforeId when paginating backwards.
     *
     * @return list<Message>
     */
    public function findPage(Conversation $conversation, ?int $beforeId, int $limit): array
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('s')
            ->innerJoin('m.sender', 's')
            ->where('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $beforeId) {
            $qb->andWhere('m.id < :beforeId')->setParameter('beforeId', $beforeId);
        }

        return array_reverse($qb->getQuery()->getResult());
    }
}
