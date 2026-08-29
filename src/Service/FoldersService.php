<?php

namespace App\Service;

use App\Entity\Companies;
use App\Entity\Files;
use App\Entity\Folders;
use App\Entity\Users;
use App\Enum\FolderType;
use App\Repository\FilesRepository;
use App\Repository\FoldersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FoldersService
{
    /**
     * Maps file type constants to entity class + company field for CLIENT folder queries.
     *
     * @var array<string, array{entity: class-string, companyField: string}>
     */
    private const array CLIENT_ENTITY_MAP = [
        // Populated as document-owning modules (contracts, offers, tests, ...) are added.
        // Shape: '<file_type>' => ['entity' => Foo::class, 'companyField' => 'client'].
    ];

    /**
     * Sub-folders auto-generated for each CLIENT folder.
     */
    private const array CLIENT_SUBFOLDERS = [
        ['name' => 'Documente', 'systemMapping' => 'companies_document', 'position' => 1],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FoldersRepository $foldersRepository,
        private readonly FilesRepository $filesRepository,
        private readonly FilesUploadService $filesUploadService,
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createFolder(string $name, ?Folders $parent, Users $createdBy): Folders
    {
        if ($parent !== null && !$parent->isCustom() && !$parent->isClient()) {
            throw new \InvalidArgumentException('Sub-folders can only be created inside CUSTOM or CLIENT folders.');
        }

        $slug = $this->generateUniqueSlug($name, $parent);

        $this->entityManager->beginTransaction();

        try {
            $folder = new Folders();
            $folder->setName($name);
            $folder->setSlug($slug);
            $folder->setParent($parent);
            $folder->setType(FolderType::CUSTOM);
            $folder->setCreatedBy($createdBy);

            $this->entityManager->persist($folder);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Folder created', [
                'id' => $folder->getId(),
                'name' => $name,
                'parent_id' => $parent?->getId(),
            ]);

            return $folder;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function renameFolder(Folders $folder, string $newName): Folders
    {
        if (!$folder->isCustom()) {
            throw new \InvalidArgumentException('Only CUSTOM folders can be renamed.');
        }

        $slug = $this->generateUniqueSlug($newName, $folder->getParent(), $folder->getId());

        $this->entityManager->beginTransaction();

        try {
            $folder->setName($newName);
            $folder->setSlug($slug);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Folder renamed', [
                'id' => $folder->getId(),
                'new_name' => $newName,
            ]);

            return $folder;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * @return array{subfolders: int, files: int}
     */
    public function getFolderStats(Folders $folder): array
    {
        return $this->foldersRepository->countDescendantsAndFiles($folder);
    }

    public function deleteFolder(Folders $folder): void
    {
        if (!$folder->isCustom()) {
            throw new \InvalidArgumentException('Only CUSTOM folders can be deleted.');
        }

        $this->entityManager->beginTransaction();

        try {
            $folderId = $folder->getId();
            $physicalPaths = [];
            $this->deleteFolderRecursive($folder, $physicalPaths);
            $this->entityManager->flush();
            $this->entityManager->commit();

            // Delete physical files only after successful commit
            foreach ($physicalPaths as $path) {
                unlink($path);
            }

            $this->logger->info('Folder deleted recursively', ['id' => $folderId]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * @param string[] $physicalPaths Collects physical file paths for deferred deletion
     */
    private function deleteFolderRecursive(Folders $folder, array &$physicalPaths): void
    {
        // Delete children first (depth-first)
        foreach ($folder->getChildren()->toArray() as $child) {
            $this->deleteFolderRecursive($child, $physicalPaths);
        }

        // Remove file entities (physical deletion deferred)
        foreach ($folder->getFiles()->toArray() as $file) {
            $path = $this->filesUploadService->removeWithoutTransaction($file);
            if ($path !== null) {
                $physicalPaths[] = $path;
            }
        }

        $this->entityManager->remove($folder);
    }

    public function moveFolder(Folders $folder, ?Folders $newParent): Folders
    {
        if (!$folder->isCustom()) {
            throw new \InvalidArgumentException('Only CUSTOM folders can be moved.');
        }

        if ($newParent !== null && !$newParent->isCustom()) {
            throw new \InvalidArgumentException('Folders can only be moved into CUSTOM folders.');
        }

        if ($newParent !== null && $this->isDescendantOf($newParent, $folder)) {
            throw new \InvalidArgumentException('Cannot move folder into its own sub-tree.');
        }

        if ($newParent !== null && $newParent->getId() === $folder->getId()) {
            throw new \InvalidArgumentException('Cannot move folder into itself.');
        }

        $slug = $this->generateUniqueSlug($folder->getName(), $newParent, $folder->getId());

        $this->entityManager->beginTransaction();

        try {
            $folder->setParent($newParent);
            $folder->setSlug($slug);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Folder moved', [
                'id' => $folder->getId(),
                'new_parent_id' => $newParent?->getId(),
            ]);

            return $folder;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * @return Folders[]
     */
    public function getTree(?FolderType $type = null, ?Companies $company = null): array
    {
        return $this->foldersRepository->findTreeByType($type, $company);
    }

    /**
     * Get direct content counts (subfolders + files) for multiple folders.
     *
     * @param int[] $folderIds
     *
     * @return array<int, array{subfolders: int, files: int}>
     */
    public function getDirectCounts(array $folderIds): array
    {
        return $this->foldersRepository->countDirectContents($folderIds);
    }

    /**
     * @return array{files: Files[], total: int}
     */
    public function getSystemFolderContents(
        Folders $folder,
        int $page = 1,
        int $limit = 25,
        ?string $search = null,
    ): array {
        $types = $folder->getSystemMappingArray();

        if (empty($types)) {
            return ['files' => [], 'total' => 0];
        }

        return $this->filesRepository->findByTypes($types, $page, $limit, $search);
    }

    /**
     * @return array{files: Files[], total: int}
     */
    public function getCustomFolderContents(
        Folders $folder,
        int $page = 1,
        int $limit = 25,
        ?string $search = null,
    ): array {
        return $this->filesRepository->findByFolder($folder, $page, $limit, $search);
    }

    /**
     * @return array{files: Files[], total: int}
     */
    public function getClientFolderContents(
        Folders $folder,
        Companies $company,
        int $page = 1,
        int $limit = 25,
    ): array {
        $mappingTypes = $folder->getSystemMappingArray();

        if (empty($mappingTypes)) {
            return ['files' => [], 'total' => 0];
        }

        // Types with no child-entity mapping are files attached to the company itself
        // (e.g. companies_document); everything else is resolved through its owning entity.
        $ownTypes = [];
        $entityGroups = [];

        foreach ($mappingTypes as $type) {
            $mapping = self::CLIENT_ENTITY_MAP[$type] ?? null;

            if ($mapping === null) {
                $ownTypes[] = $type;
                continue;
            }

            $entityClass = $mapping['entity'];
            $companyField = $mapping['companyField'];

            if (!preg_match('/^[a-zA-Z_]+$/', $companyField)) {
                throw new \LogicException(sprintf('Invalid company field "%s" in CLIENT_ENTITY_MAP.', $companyField));
            }

            if (!isset($entityGroups[$entityClass])) {
                $entityGroups[$entityClass] = [
                    'companyField' => $companyField,
                    'types' => [],
                ];
            }
            $entityGroups[$entityClass]['types'][] = $type;
        }

        // Collect all entity-type filter groups for a single paginated query
        $entityFilters = [];

        if ($ownTypes !== []) {
            $entityFilters[] = [
                'entityClass' => Companies::class,
                'entityIds' => [$company->getId()],
                'types' => $ownTypes,
            ];
        }

        foreach ($entityGroups as $entityClass => $group) {
            $entityIds = $this->entityManager->createQueryBuilder()
                ->select('e.id')
                ->from($entityClass, 'e')
                ->where('e.' . $group['companyField'] . ' = :company')
                ->setParameter('company', $company)
                ->getQuery()
                ->getSingleColumnResult();

            if (empty($entityIds)) {
                continue;
            }

            $entityFilters[] = [
                'entityClass' => $entityClass,
                'entityIds' => $entityIds,
                'types' => $group['types'],
            ];
        }

        if (empty($entityFilters)) {
            return ['files' => [], 'total' => 0];
        }

        return $this->filesRepository->findByMultipleEntityFilters($entityFilters, $page, $limit);
    }

    /**
     * @param UploadedFile[] $uploadedFiles
     *
     * @return Files[]
     */
    public function uploadToFolder(Folders $folder, array $uploadedFiles, Users $uploadedBy): array
    {
        if (!$folder->isCustom()) {
            throw new \InvalidArgumentException('Upload is only allowed in CUSTOM folders.');
        }

        $files = $this->filesUploadService->uploadBatch(
            $uploadedFiles,
            $folder,
            'folders',
            Files::TYPE_FOLDER_DOCUMENT,
            $folder,
        );

        $this->logger->info('Files uploaded to folder', [
            'folder_id' => $folder->getId(),
            'count' => count($files),
        ]);

        return $files;
    }

    public function moveFile(Files $file, Folders $targetFolder): Files
    {
        if ($file->getFolder() === null || !$file->getFolder()->isCustom()) {
            throw new \InvalidArgumentException('Only files in CUSTOM folders can be moved.');
        }

        if (!$targetFolder->isCustom()) {
            throw new \InvalidArgumentException('Files can only be moved to CUSTOM folders.');
        }

        $this->entityManager->beginTransaction();

        try {
            $file->setFolder($targetFolder);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('File moved', [
                'file_id' => $file->getId(),
                'target_folder_id' => $targetFolder->getId(),
            ]);

            return $file;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function deleteFile(Files $file): void
    {
        if ($file->getFolder() === null || !$file->getFolder()->isCustom()) {
            throw new \InvalidArgumentException('Only files in CUSTOM folders can be deleted from here.');
        }

        $fileId = $file->getId();
        $folderId = $file->getFolder()->getId();

        $this->filesUploadService->remove($file);

        $this->logger->info('File deleted from folder', [
            'file_id' => $fileId,
            'folder_id' => $folderId,
        ]);
    }

    /**
     * @return array{files: Files[], total: int}
     */
    public function searchFiles(
        string $query,
        ?Folders $folder = null,
        int $page = 1,
        int $limit = 25,
    ): array {
        return $this->filesRepository->searchByOriginalName($query, $folder, $page, $limit);
    }

    public function createClientFolder(Companies $company): Folders
    {
        $this->entityManager->beginTransaction();

        try {
            $rootFolder = new Folders();
            $rootFolder->setName($company->getName());
            $rootFolder->setSlug($this->slugger->slug($company->getName())->lower()->toString());
            $rootFolder->setType(FolderType::CLIENT);
            $rootFolder->setCompany($company);

            $this->entityManager->persist($rootFolder);

            foreach (self::CLIENT_SUBFOLDERS as $subDef) {
                $sub = new Folders();
                $sub->setName($subDef['name']);
                $sub->setSlug($this->slugger->slug($subDef['name'])->lower()->toString());
                $sub->setParent($rootFolder);
                $sub->setType(FolderType::CLIENT);
                $sub->setCompany($company);
                $sub->setSystemMapping($subDef['systemMapping']);
                $sub->setPosition($subDef['position']);

                $this->entityManager->persist($sub);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Client folder created', [
                'company_id' => $company->getId(),
                'folder_id' => $rootFolder->getId(),
            ]);

            return $rootFolder;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function deleteClientFolder(Companies $company): void
    {
        $rootFolder = $this->foldersRepository->findClientRootFolder($company);

        if ($rootFolder === null) {
            return;
        }

        $this->entityManager->beginTransaction();

        try {
            foreach ($rootFolder->getChildren() as $child) {
                $this->entityManager->remove($child);
            }

            $this->entityManager->remove($rootFolder);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Client folder deleted', [
                'company_id' => $company->getId(),
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * @return Folders[]
     */
    public function getFolderPath(Folders $folder): array
    {
        return $this->foldersRepository->findAncestors($folder);
    }

    private function generateUniqueSlug(string $name, ?Folders $parent, ?int $excludeId = null): string
    {
        $baseSlug = $this->slugger->slug($name)->lower()->toString();
        $slug = $baseSlug;
        $counter = 1;

        while ($this->foldersRepository->existsBySlugAndParent($slug, $parent, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            ++$counter;
        }

        return $slug;
    }

    private function isDescendantOf(Folders $folder, Folders $potentialAncestor): bool
    {
        $current = $folder;

        while ($current !== null) {
            if ($current->getId() === $potentialAncestor->getId()) {
                return true;
            }
            $current = $current->getParent();
        }

        return false;
    }
}
