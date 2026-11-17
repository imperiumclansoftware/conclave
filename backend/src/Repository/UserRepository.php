<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /**
     * Members shown on the clan's public site: approved and having opted in.
     *
     * @return list<User>
     */
    public function findForPublicSite(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.status = :active')->setParameter('active', UserStatus::Active)
            ->andWhere('u.publicProfile = true')
            ->orderBy('LOWER(u.displayName)')
            ->getQuery()->getResult();
    }

    public function findOneForPublicSite(string $publicSub): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.publicSub = :sub')->setParameter('sub', $publicSub)
            ->andWhere('u.status = :active')->setParameter('active', UserStatus::Active)
            ->andWhere('u.publicProfile = true')
            ->getQuery()->getOneOrNullResult();
    }
}
