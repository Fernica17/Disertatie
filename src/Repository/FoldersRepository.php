<?php

namespace App\Repository;

use App\Entity\Companies;
use App\Entity\Folders;
use App\Enum\FolderType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Folders>
 */
class FoldersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Folders::class);
    }

    /**
     * @return Folders[]
     */
    public function findRootFolders(?FolderType $type = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.parent IS NULL')
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.name', 'ASC');

        if ($type !== null) {
            $qb->andWhere('f.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get the full folder tree with eager-loaded children.
     *
     * @return Folders[]
     */
    public function findTreeByType(?FolderType $type = null, ?Companies $company = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.children', 'c')
            ->addSelect('c')
            ->leftJoin('c.children', 'gc')
            ->addSelect('gc')
            ->where('f.parent IS NULL')
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.name', 'ASC');

        if ($type !== null) {
            $qb->andWhere('f.type = :type')
                ->setParameter('type', $type);
        }

        if ($company !== null) {
            $qb->andWhere('f.company = :company')
                ->setParameter('company', $company);
        }

        return $qb->getQuery()->getResult();
    }

    public function existsBySlugAndParent(string $slug, ?Folders $parent, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.slug = :slug')
            ->setParameter('slug', $slug);

        if ($parent !== null) {
            $qb->andWhere('f.parent = :parent')
                ->setParameter('parent', $parent);
        } else {
            $qb->andWhere('f.parent IS NULL');
        }

        if ($excludeId !== null) {
            $qb->andWhere('f.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findClientRootFolder(Companies $company): ?Folders
    {
        return $this->createQueryBuilder('f')
            ->where('f.company = :company')
            ->andWhere('f.type = :type')
            ->andWhere('f.parent IS NULL')
            ->setParameter('company', $company)
            ->setParameter('type', FolderType::CLIENT)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isEmpty(Folders $folder): bool
    {
        $childCount = (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.parent = :folder')
            ->setParameter('folder', $folder)
            ->getQuery()
            ->getSingleScalarResult();

        if ($childCount > 0) {
            return false;
        }

        $fileCount = (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(fi.id)')
            ->from('App\Entity\Files', 'fi')
            ->where('fi.folder = :folder')
            ->setParameter('folder', $folder)
            ->getQuery()
            ->getSingleScalarResult();

        return $fileCount === 0;
    }

    /**
     * Count all descendant sub-folders and files recursively.
     *
     * @return array{subfolders: int, files: int}
     */
    public function countDescendantsAndFiles(Folders $folder): array
    {
        $allIds = $this->collectDescendantIds($folder);
        $allIds[] = $folder->getId();

        $fileCount = (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(fi.id)')
            ->from('App\Entity\Files', 'fi')
            ->where('fi.folder IN (:ids)')
            ->setParameter('ids', $allIds)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'subfolders' => count($allIds) - 1,
            'files' => $fileCount,
        ];
    }

    /**
     * @return int[]
     */
    private function collectDescendantIds(Folders $folder): array
    {
        $parentIds = [$folder->getId()];
        $allIds = [];

        while (true) {
            $childIds = $this->createQueryBuilder('f')
                ->select('f.id')
                ->where('f.parent IN (:parentIds)')
                ->setParameter('parentIds', $parentIds)
                ->getQuery()
                ->getSingleColumnResult();

            if (empty($childIds)) {
                break;
            }

            $allIds = array_merge($allIds, $childIds);
            $parentIds = $childIds;
        }

        return $allIds;
    }

    /**
     * Count direct children (subfolders) and direct files for multiple folders.
     *
     * @param int[] $folderIds
     *
     * @return array<int, array{subfolders: int, files: int}>
     */
    public function countDirectContents(array $folderIds): array
    {
        if (empty($folderIds)) {
            return [];
        }

        $result = [];
        foreach ($folderIds as $id) {
            $result[$id] = ['subfolders' => 0, 'files' => 0];
        }

        // Count direct subfolders per parent
        $subfolderCounts = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.parent) AS parentId, COUNT(f.id) AS cnt')
            ->where('f.parent IN (:ids)')
            ->setParameter('ids', $folderIds)
            ->groupBy('f.parent')
            ->getQuery()
            ->getResult();

        foreach ($subfolderCounts as $row) {
            $result[(int) $row['parentId']]['subfolders'] = (int) $row['cnt'];
        }

        // Count direct files per folder
        $fileCounts = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(fi.folder) AS folderId, COUNT(fi.id) AS cnt')
            ->from('App\Entity\Files', 'fi')
            ->where('fi.folder IN (:ids)')
            ->setParameter('ids', $folderIds)
            ->groupBy('fi.folder')
            ->getQuery()
            ->getResult();

        foreach ($fileCounts as $row) {
            $result[(int) $row['folderId']]['files'] = (int) $row['cnt'];
        }

        return $result;
    }

    /**
     * @return Folders[]
     */
    public function findAncestors(Folders $folder): array
    {
        $ancestors = [];
        $current = $folder;

        while ($current !== null) {
            array_unshift($ancestors, $current);
            $current = $current->getParent();
        }

        return $ancestors;
    }
}
