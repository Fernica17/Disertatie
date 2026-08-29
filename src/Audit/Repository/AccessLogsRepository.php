<?php

namespace App\Audit\Repository;

use App\Audit\Entity\AccessLogs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccessLogs>
 */
class AccessLogsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessLogs::class);
    }

    /**
     * @return AccessLogs[]
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

    /**
     * @return AccessLogs[]
     */
    public function findByRoute(string $route): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.routeName = :route')
            ->setParameter('route', $route)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<array{period: string, access_count: int, unique_routes: int}>
     */
    public function getAccessStatsByUser(int $userId, string $groupBy = 'day'): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = sprintf(
            'SELECT %s AS period,
                    COUNT(*) AS access_count,
                    COUNT(DISTINCT route_name) AS unique_routes
             FROM access_logs
             WHERE user_id = :userId
             GROUP BY period
             ORDER BY period DESC',
            match ($groupBy) {
                'week' => "DATE_FORMAT(created_at, '%x-W%v')",
                'month' => "DATE_FORMAT(created_at, '%Y-%m')",
                default => "DATE_FORMAT(created_at, '%Y-%m-%d')",
            }
        );

        return $conn->fetchAllAssociative($sql, ['userId' => $userId]);
    }

    public function getActiveUsersCount(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.userId)')
            ->where('a.createdAt >= :from')
            ->andWhere('a.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<array{route: string, access_count: int, unique_users: int, avg_response_time: float}>
     */
    public function getMostAccessedRoutes(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null, int $limit = 20): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT route_name AS route,
                       COUNT(*) AS access_count,
                       COUNT(DISTINCT user_id) AS unique_users,
                       ROUND(AVG(execution_time), 2) AS avg_response_time,
                       ROUND(MAX(execution_time), 2) AS max_response_time
                FROM access_logs
                WHERE route_name IS NOT NULL';

        $params = [];
        if ($from !== null) {
            $sql .= ' AND created_at >= :from';
            $params['from'] = $from->format('Y-m-d H:i:s');
        }
        if ($to !== null) {
            $sql .= ' AND created_at <= :to';
            $params['to'] = $to->format('Y-m-d H:i:s');
        }

        $sql .= ' GROUP BY route_name ORDER BY access_count DESC LIMIT :limit';
        $params['limit'] = $limit;

        return $conn->fetchAllAssociative($sql, $params, ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]);
    }

    /**
     * @return array<array{route: string, status_code: int, error_count: int}>
     */
    public function getErrorStats(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT route_name AS route,
                       response_code AS status_code,
                       COUNT(*) AS error_count
                FROM access_logs
                WHERE response_code >= 400';

        $params = [];
        if ($from !== null) {
            $sql .= ' AND created_at >= :from';
            $params['from'] = $from->format('Y-m-d H:i:s');
        }
        if ($to !== null) {
            $sql .= ' AND created_at <= :to';
            $params['to'] = $to->format('Y-m-d H:i:s');
        }

        $sql .= ' GROUP BY route_name, response_code ORDER BY error_count DESC';

        return $conn->fetchAllAssociative($sql, $params);
    }

    public function getLastAccessTime(int $userId): ?\DateTimeInterface
    {
        $result = $this->createQueryBuilder('a')
            ->select('a.createdAt')
            ->where('a.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['createdAt'] : null;
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
