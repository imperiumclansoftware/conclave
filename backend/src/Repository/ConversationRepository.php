<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findDirect(User $a, User $b): ?Conversation
    {
        return $this->findOneBy(['directKey' => Conversation::directKeyFor($a, $b)]);
    }

    /**
     * @return list<Conversation> the user's conversations, most recently active first
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p', 'u', 'm')
            ->innerJoin(ConversationParticipant::class, 'me', 'WITH', 'me.conversation = c AND me.user = :user')
            ->innerJoin('c.participants', 'p')
            ->innerJoin('p.user', 'u')
            ->leftJoin('c.lastMessage', 'm')
            ->setParameter('user', $user)
            ->orderBy('m.id', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int> number of unread messages, indexed by conversation id (only non-zero counts)
     */
    public function countUnreadByConversation(User $user): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(msg.conversation) AS conversationId', 'COUNT(msg.id) AS unread')
            ->from(Message::class, 'msg')
            ->innerJoin(ConversationParticipant::class, 'p', 'WITH', 'p.conversation = msg.conversation AND p.user = :user')
            ->where('msg.sender != :user')
            ->andWhere('msg.id > COALESCE(p.lastReadMessageId, 0)')
            ->groupBy('msg.conversation')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_column(
            array_map(static fn (array $row): array => [(int) $row['conversationId'], (int) $row['unread']], $rows),
            1,
            0,
        );
    }
}
