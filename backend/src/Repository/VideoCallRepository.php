<?php

namespace App\Repository;

use App\Entity\Clan;
use App\Entity\User;
use App\Entity\VideoCall;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoCall>
 */
class VideoCallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoCall::class);
    }

    /** @return list<VideoCall> ongoing calls the user is invited to */
    public function findActiveFor(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'me', 'WITH', 'me = :user')
            ->where('c.endedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByChannel(string $channelId): ?VideoCall
    {
        return $this->findOneBy(['discordChannelId' => $channelId, 'endedAt' => null]);
    }

    /** @return list<VideoCall> */
    public function findActiveInClan(Clan $clan): array
    {
        return $this->findBy(['clan' => $clan, 'endedAt' => null]);
    }

    /** @return list<VideoCall> */
    public function findAllActive(): array
    {
        return $this->findBy(['endedAt' => null]);
    }
}
