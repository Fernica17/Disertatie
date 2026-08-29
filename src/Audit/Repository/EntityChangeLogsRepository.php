<?php

namespace App\Audit\Repository;

use App\Audit\Entity\EntityChangeLogs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EntityChangeLogs>
 */
class EntityChangeLogsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityChangeLogs::class);
    }

    /**
     * @return EntityChangeLogs[]
     */
    public function findByEntity(string $entityClass, int $entityId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.entityClass = :class')
            ->andWhere('e.entityId = :id')
            ->setParameter('class', $entityClass)
            ->setParameter('id', $entityId)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EntityChangeLogs[]
     */
    public function findByUser(int $userId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('e.createdAt', 'DESC');

        if ($from !== null) {
            $qb->andWhere('e.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('e.createdAt <= :to')
                ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('e')
            ->delete()
            ->where('e.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    public function deleteByUserId(int $userId): int
    {
        return $this->createQueryBuilder('e')
            ->delete()
            ->where('e.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
