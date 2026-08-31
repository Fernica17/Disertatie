<?php

namespace App\Repository;

use App\Entity\FaceSearchReports;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaceSearchReports>
 */
class FaceSearchReportsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaceSearchReports::class);
    }

    /**
     * @return list<FaceSearchReports>
     */
    public function latest(int $limit = 50): array
    {
        /** @var list<FaceSearchReports> $result */
        $result = $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
