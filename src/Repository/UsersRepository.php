<?php

namespace App\Repository;

use App\Entity\Users;
use App\Enum\UserRole;
use App\Traits\MonthlyDataTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class UsersRepository extends ServiceEntityRepository
{
    use MonthlyDataTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Users::class);
    }

    /**
     * Users who can act as an account manager (admins and managers).
     *
     * roles is a JSON column, so membership goes through jsonb containment —
     * PostgreSQL has no LIKE operator for json.
     */
    public function createManagersQueryBuilder(string $alias = 'u'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->where(sprintf('JSONB_CONTAINS(%1$s.roles, :admin) = true OR JSONB_CONTAINS(%1$s.roles, :manager) = true', $alias))
            ->setParameter('admin', json_encode([UserRole::ADMIN->value]))
            ->setParameter('manager', json_encode([UserRole::MANAGER->value]));
    }

    /**
     * @return Users[]
     */
    public function findManagers(): array
    {
        return $this->createManagersQueryBuilder()
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function clientVisibilityFilter(QueryBuilder $qb, string $alias, Users $user): QueryBuilder
    {
        $company = $user->getCompany();

        if ($company !== null) {
            return $qb->andWhere(sprintf('%s.company = :company', $alias))
                ->setParameter('company', $company);
        }

        return $qb->andWhere(sprintf('%s.id = :userId', $alias))
            ->setParameter('userId', $user->getId());
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countInactive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = :active')
            ->setParameter('active', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Counts users by their primary role.
     *
     * roles is a JSON column, so containment is checked with the jsonb operator.
     *
     * @return array<string, int> UserRole value => count
     */
    public function countByRole(): array
    {
        $counts = [];

        foreach (UserRole::cases() as $role) {
            $counts[$role->value] = (int) $this->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('JSONB_CONTAINS(u.roles, :role) = true')
                ->setParameter('role', json_encode([$role->value]))
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $counts;
    }

    /**
     * Users created per month over the last N months.
     *
     * @return array<string, float> 'YYYY-MM' => count
     */
    public function countPerMonth(int $months = 12): array
    {
        $sql = "SELECT TO_CHAR(created_at, 'YYYY-MM') AS month, COUNT(id) AS cnt
                FROM users
                WHERE created_at >= :since
                GROUP BY month";

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'since' => (new \DateTimeImmutable("first day of -{$months} months"))->format('Y-m-01 00:00:00'),
        ])->fetchAllAssociative();

        return $this->fillMonths($rows, $months, 'cnt');
    }

    /**
     * @return Users[] most recently created users
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
