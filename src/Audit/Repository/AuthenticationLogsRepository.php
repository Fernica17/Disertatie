<?php

namespace App\Audit\Repository;

use App\Audit\Entity\AuthenticationLogs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuthenticationLogs>
 */
class AuthenticationLogsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthenticationLogs::class);
    }

    /**
     * @return AuthenticationLogs[]
     */
    public function findByUser(int $userId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.createdAt', 'DESC');

        if ($from !== null) {
            $qb->andWhere('a.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('a.createdAt <= :to')
                ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    public function countFailedLoginsByIp(string $ip, \DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.ipAddress = :ip')
            ->andWhere('a.success = false')
            ->andWhere('a.eventType = :eventType')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('ip', $ip)
            ->setParameter('eventType', AuthenticationLogs::TYPE_LOGIN_FAILED)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<array{period: string, login_count: int, failed_count: int}>
     */
    public function getAuthenticationStats(int $userId, string $groupBy = 'day'): array
    {
        $dateFormat = match ($groupBy) {
            'week' => "DATE_FORMAT(a.createdAt, '%x-W%v')",
            'month' => "DATE_FORMAT(a.createdAt, '%Y-%m')",
            default => "DATE_FORMAT(a.createdAt, '%Y-%m-%d')",
        };

        $conn = $this->getEntityManager()->getConnection();

        $sql = sprintf(
            "SELECT %s AS period,
                    SUM(CASE WHEN event_type = 'LOGIN_SUCCESS' THEN 1 ELSE 0 END) AS login_count,
                    SUM(CASE WHEN event_type = 'LOGIN_FAILED' THEN 1 ELSE 0 END) AS failed_count
             FROM authentication_logs
             WHERE user_id = :userId
             GROUP BY period
             ORDER BY period DESC",
            match ($groupBy) {
                'week' => "DATE_FORMAT(created_at, '%x-W%v')",
                'month' => "DATE_FORMAT(created_at, '%Y-%m')",
                default => "DATE_FORMAT(created_at, '%Y-%m-%d')",
            }
        );

        return $conn->fetchAllAssociative($sql, ['userId' => $userId]);
    }

    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    public function deleteByUserId(int $userId): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
