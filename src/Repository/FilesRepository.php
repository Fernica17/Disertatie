<?php

namespace App\Repository;

use App\Entity\Files;
use App\Entity\Folders;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Files>
 */
class FilesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Files::class);
    }

    /**
     * Find all files for a given entity.
     *
     * @return Files[]
     */
    public function findByEntity(string $entityClass, int $entityId, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.entity = :entity')
            ->andWhere('f.entityId = :entityId')
            ->setParameter('entity', $entityClass)
            ->setParameter('entityId', $entityId)
            ->orderBy('f.createdAt', 'DESC');

        if ($type !== null) {
            $qb->andWhere('f.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find files for multiple entities of the same class in a single query.
     *
     * @param int[] $entityIds
     *
     * @return array<int, Files[]> Indexed by entityId
     */
    public function findByEntities(string $entityClass, array $entityIds, ?string $type = null): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $qb = $this->createQueryBuilder('f')
            ->where('f.entity = :entity')
            ->andWhere('f.entityId IN (:entityIds)')
            ->setParameter('entity', $entityClass)
            ->setParameter('entityIds', $entityIds)
            ->orderBy('f.createdAt', 'DESC');

        if ($type !== null) {
            $qb->andWhere('f.type = :type')
                ->setParameter('type', $type);
        }

        $results = $qb->getQuery()->getResult();

        // Group by entityId
        $grouped = [];
        foreach ($results as $file) {
            $grouped[$file->getEntityId()][] = $file;
        }

        return $grouped;
    }

    /**
     * Find one file for a given entity (e.g., logo).
     */
    public function findOneByEntity(string $entityClass, int $entityId, ?string $type = null): ?Files
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.entity = :entity')
            ->andWhere('f.entityId = :entityId')
            ->setParameter('entity', $entityClass)
            ->setParameter('entityId', $entityId)
            ->setMaxResults(1);

        if ($type !== null) {
            $qb->andWhere('f.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    /**
     * Find files by type(s) — used for SYSTEM folder contents.
     *
     * @param string[] $types
     *
     * @return array{files: Files[], total: int}
     */
    public function findByTypes(array $types, int $page = 1, int $limit = 25, ?string $search = null): array
    {
        if (empty($types)) {
            return ['files' => [], 'total' => 0];
        }

        $qb = $this->createQueryBuilder('f')
            ->where('f.type IN (:types)')
            ->setParameter('types', $types)
            ->orderBy('f.createdAt', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('f.originalName LIKE :search')
                ->setParameter('search', '%' . $this->escapeLike($search) . '%');
        }

        // PostgreSQL rejects ORDER BY on a non-grouped column alongside an aggregate,
        // so the count query drops the ordering the list query needs.
        $total = (int) (clone $qb)->select('COUNT(f.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $files = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['files' => $files, 'total' => $total];
    }

    /**
     * Find files in a CUSTOM folder.
     *
     * @return array{files: Files[], total: int}
     */
    public function findByFolder(Folders $folder, int $page = 1, int $limit = 25, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.folder = :folder')
            ->setParameter('folder', $folder)
            ->orderBy('f.createdAt', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('f.originalName LIKE :search')
                ->setParameter('search', '%' . $this->escapeLike($search) . '%');
        }

        // PostgreSQL rejects ORDER BY on a non-grouped column alongside an aggregate,
        // so the count query drops the ordering the list query needs.
        $total = (int) (clone $qb)->select('COUNT(f.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $files = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['files' => $files, 'total' => $total];
    }

    /**
     * Find files for specific entity IDs and types — used for CLIENT folder contents.
     *
     * @param int[]    $entityIds
     * @param string[] $types
     *
     * @return array{files: Files[], total: int}
     */
    public function findByEntityIdsAndTypes(
        string $entityClass,
        array $entityIds,
        array $types,
        int $page = 1,
        int $limit = 25,
    ): array {
        if (empty($entityIds) || empty($types)) {
            return ['files' => [], 'total' => 0];
        }

        $qb = $this->createQueryBuilder('f')
            ->where('f.entity = :entity')
            ->andWhere('f.entityId IN (:entityIds)')
            ->andWhere('f.type IN (:types)')
            ->setParameter('entity', $entityClass)
            ->setParameter('entityIds', $entityIds)
            ->setParameter('types', $types)
            ->orderBy('f.createdAt', 'DESC');

        // PostgreSQL rejects ORDER BY on a non-grouped column alongside an aggregate,
        // so the count query drops the ordering the list query needs.
        $total = (int) (clone $qb)->select('COUNT(f.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $files = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['files' => $files, 'total' => $total];
    }

    /**
     * Find files matching multiple entity-type filter groups with unified pagination.
     *
     * @param array<array{entityClass: string, entityIds: int[], types: string[]}> $entityFilters
     *
     * @return array{files: Files[], total: int}
     */
    public function findByMultipleEntityFilters(
        array $entityFilters,
        int $page = 1,
        int $limit = 25,
    ): array {
        if (empty($entityFilters)) {
            return ['files' => [], 'total' => 0];
        }

        $qb = $this->createQueryBuilder('f');
        $orConditions = $qb->expr()->orX();

        foreach ($entityFilters as $i => $filter) {
            $orConditions->add(
                $qb->expr()->andX(
                    $qb->expr()->eq('f.entity', ':entity_' . $i),
                    $qb->expr()->in('f.entityId', ':entityIds_' . $i),
                    $qb->expr()->in('f.type', ':types_' . $i),
                )
            );
            $qb->setParameter('entity_' . $i, $filter['entityClass']);
            $qb->setParameter('entityIds_' . $i, $filter['entityIds']);
            $qb->setParameter('types_' . $i, $filter['types']);
        }

        $qb->where($orConditions)->orderBy('f.createdAt', 'DESC');

        // PostgreSQL rejects ORDER BY on a non-grouped column alongside an aggregate,
        // so the count query drops the ordering the list query needs.
        $total = (int) (clone $qb)->select('COUNT(f.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $files = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['files' => $files, 'total' => $total];
    }

    /**
     * Search files by original name across all or within a specific folder.
     *
     * @return array{files: Files[], total: int}
     */
    public function searchByOriginalName(
        string $query,
        ?Folders $folder = null,
        int $page = 1,
        int $limit = 25,
    ): array {
        $qb = $this->createQueryBuilder('f')
            ->where('f.originalName LIKE :query')
            ->setParameter('query', '%' . $this->escapeLike($query) . '%')
            ->orderBy('f.createdAt', 'DESC');

        if ($folder !== null) {
            $qb->andWhere('f.folder = :folder')
                ->setParameter('folder', $folder);
        }

        // PostgreSQL rejects ORDER BY on a non-grouped column alongside an aggregate,
        // so the count query drops the ordering the list query needs.
        $total = (int) (clone $qb)->select('COUNT(f.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $files = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['files' => $files, 'total' => $total];
    }
}
