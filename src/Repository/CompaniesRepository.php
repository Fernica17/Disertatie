<?php

namespace App\Repository;

use App\Entity\Companies;
use App\Enum\CompanyStatus;
use App\Traits\CountByStatusTrait;
use App\Traits\MonthlyDataTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Companies>
 */
class CompaniesRepository extends ServiceEntityRepository
{
    use CountByStatusTrait;
    use MonthlyDataTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Companies::class);
    }

    /**
     * Find all active companies ordered by name.
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->setParameter('statuses', [CompanyStatus::ACTIVE, CompanyStatus::PROSPECT])
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByFiscalCode(string $fiscalCode, ?int $excludeId = null): ?Companies
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.fiscalCode = :fiscalCode')
            ->setParameter('fiscalCode', $fiscalCode);

        if ($excludeId !== null) {
            $qb->andWhere('c.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return array<string, int> status value => count
     */
    public function countByStatus(): array
    {
        return $this->countGroupedByStatus('c');
    }

    public function countByStatusValue(CompanyStatus $status): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Companies created per month over the last N months.
     *
     * @return array<string, float> 'YYYY-MM' => count
     */
    public function countPerMonth(int $months = 12): array
    {
        $sql = "SELECT TO_CHAR(created_at, 'YYYY-MM') AS month, COUNT(id) AS cnt
                FROM companies
                WHERE created_at >= :since
                GROUP BY month";

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'since' => (new \DateTimeImmutable("first day of -{$months} months"))->format('Y-m-01 00:00:00'),
        ])->fetchAllAssociative();

        return $this->fillMonths($rows, $months, 'cnt');
    }

    /**
     * @return Companies[] most recently created companies
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
