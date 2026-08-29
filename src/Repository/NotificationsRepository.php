<?php

namespace App\Repository;

use App\Entity\Notifications;
use App\Entity\Users;
use App\Enum\NotificationHeaderType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notifications>
 */
class NotificationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notifications::class);
    }

    public function createUnreadHeaderQueryBuilder(Users $user): QueryBuilder
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->andWhere('n.type IN (:included_types)')
            ->setParameter('user', $user)
            ->setParameter('included_types', NotificationHeaderType::getAllowedTypes())
            ->orderBy('n.createdAt', 'DESC');
    }

    public function getUnreadNotifications(Users $user, int $limit = 20): array
    {
        return $this->createUnreadHeaderQueryBuilder($user)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadNotifications(Users $user): int
    {
        // PostgreSQL rejects ORDER BY on a non-grouped column alongside an aggregate,
        // so drop the ordering the shared builder applies.
        return (int) $this->createUnreadHeaderQueryBuilder($user)
            ->select('COUNT(n.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
