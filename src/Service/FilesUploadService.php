<?php

namespace App\Service;

use App\Entity\Files;
use App\Entity\Folders;
use App\Repository\FilesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FilesUploadService
{
    private const array ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-rar-compressed',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesRepository $filesRepository,
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
        private readonly string $storageDir,
        private readonly int $uploadMaxFileSize,
        private readonly int $uploadMaxFiles,
    ) {
    }

    /**
     * Upload a file and associate it with an entity.
     */
    public function upload(
        UploadedFile $uploadedFile,
        object $entity,
        ?string $subDir = null,
        string $type = Files::TYPE_DEFAULT,
    ): Files {
        $this->validateFile($uploadedFile);

        $entityClass = $this->resolveEntityClass($entity);
        $entityId = $entity->getId();

        $originalName = $uploadedFile->getClientOriginalName();
        $safeFilename = $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = $uploadedFile->guessExtension() ?? $uploadedFile->getClientOriginalExtension();
        $newFilename = $safeFilename . '-' . bin2hex(random_bytes(8)) . '.' . $extension;

        $targetDir = $this->getTargetDir($subDir);

        $mimeType = $uploadedFile->getClientMimeType() ?: 'application/octet-stream';
        $fileSize = $uploadedFile->getSize() ?: 0;

        $this->entityManager->beginTransaction();

        try {
            $uploadedFile->move($targetDir, $newFilename);

            $file = new Files();
            $file->setFilename(($subDir ? $subDir . '/' : '') . $newFilename);
            $file->setOriginalName($originalName);
            $file->setMimeType($mimeType);
            $file->setSize($fileSize);
            $file->setType($type);
            $file->setEntity($entityClass);
            $file->setEntityId($entityId);

            $this->entityManager->persist($file);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('File uploaded', [
                'id' => $file->getId(),
                'original_name' => $originalName,
                'entity' => $entityClass,
                'entity_id' => $entityId,
            ]);

            return $file;
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            // Cleanup physical file if it was moved
            $physicalPath = $targetDir . '/' . $newFilename;
            if (file_exists($physicalPath)) {
                unlink($physicalPath);
            }

            $this->logger->error('Failed to upload file', [
                'error' => $e->getMessage(),
                'original_name' => $originalName,
                'entity' => $entityClass,
            ]);

            throw $e;
        }
    }

    /**
     * Absolute path of a stored file on disk, or null when it is missing.
     *
     * Files::getFilename() holds the path relative to the storage root, so
     * callers that need to read the bytes go through here instead of
     * reassembling the path themselves.
     */
    public function absolutePath(Files $file): ?string
    {
        $filename = $file->getFilename();

        if ($filename === null) {
            return null;
        }

        $path = $this->storageDir . '/' . $filename;

        return is_file($path) ? $path : null;
    }

    /**
     * Upload multiple files for an entity in a single transaction.
     *
     * @param UploadedFile[] $uploadedFiles
     *
     * @return Files[]
     */
    public function uploadBatch(
        array $uploadedFiles,
        object $entity,
        string $targetDirectory,
        string $type = Files::TYPE_DEFAULT,
        ?Folders $folder = null,
    ): array {
        if (empty($uploadedFiles)) {
            return [];
        }

        if (count($uploadedFiles) > $this->uploadMaxFiles) {
            throw new \InvalidArgumentException(
                sprintf('Maximum %d files allowed per upload.', $this->uploadMaxFiles)
            );
        }

        $entityClass = $this->resolveEntityClass($entity);
        $entityId = $entity->getId();
        $targetDir = $this->getTargetDir($targetDirectory);

        $this->entityManager->beginTransaction();
        $movedFiles = [];

        try {
            $files = [];

            foreach ($uploadedFiles as $uploadedFile) {
                $this->validateFile($uploadedFile);

                $originalName = $uploadedFile->getClientOriginalName();
                $safeFilename = $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME));
                $extension = $uploadedFile->guessExtension() ?? $uploadedFile->getClientOriginalExtension();
                $newFilename = $safeFilename . '-' . bin2hex(random_bytes(8)) . '.' . $extension;

                $mimeType = $uploadedFile->getClientMimeType() ?: 'application/octet-stream';
                $fileSize = $uploadedFile->getSize() ?: 0;

                $uploadedFile->move($targetDir, $newFilename);
                $movedFiles[] = $targetDir . '/' . $newFilename;

                $file = new Files();
                $file->setFilename(($targetDirectory ? $targetDirectory . '/' : '') . $newFilename);
                $file->setOriginalName($originalName);
                $file->setMimeType($mimeType);
                $file->setSize($fileSize);
                $file->setType($type);
                $file->setEntity($entityClass);
                $file->setEntityId($entityId);

                if ($folder !== null) {
                    $file->setFolder($folder);
                }

                $this->entityManager->persist($file);
                $files[] = $file;
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Batch upload completed', [
                'count' => count($files),
                'entity' => $entityClass,
                'entity_id' => $entityId,
            ]);

            return $files;
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            // Cleanup moved physical files
            foreach ($movedFiles as $physicalPath) {
                if (file_exists($physicalPath)) {
                    unlink($physicalPath);
                }
            }

            $this->logger->error('Failed to upload batch', [
                'error' => $e->getMessage(),
                'entity' => $entityClass,
            ]);

            throw $e;
        }
    }

    /**
     * Remove a file (DB record + physical file).
     */
    public function remove(Files $file, ?string $subDir = null): void
    {
        $this->entityManager->beginTransaction();

        try {
            $fileId = $file->getId();
            $filename = $file->getFilename();

            $this->entityManager->remove($file);
            $this->entityManager->flush();
            $this->entityManager->commit();

            // Delete physical file after successful DB removal
            $physicalPath = $this->storageDir . '/' . $filename;
            if (file_exists($physicalPath)) {
                unlink($physicalPath);
            }

            $this->logger->info('File removed', [
                'id' => $fileId,
                'filename' => $filename,
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to remove file', [
                'error' => $e->getMessage(),
                'file_id' => $file->getId(),
            ]);

            throw $e;
        }
    }

    /**
     * Remove a file entity without wrapping in its own transaction (for use inside an existing transaction).
     * Returns the physical path so the caller can delete it after commit.
     */
    public function removeWithoutTransaction(Files $file): ?string
    {
        $fileId = $file->getId();
        $filename = $file->getFilename();

        $this->entityManager->remove($file);

        $this->logger->info('File removed', [
            'id' => $fileId,
            'filename' => $filename,
        ]);

        $physicalPath = $this->storageDir . '/' . $filename;

        return file_exists($physicalPath) ? $physicalPath : null;
    }

    /**
     * Get all files for a given entity.
     *
     * @return Files[]
     */
    public function getFilesForEntity(object $entity, ?string $type = null): array
    {
        $entityClass = $this->resolveEntityClass($entity);

        return $this->filesRepository->findByEntity($entityClass, $entity->getId(), $type);
    }

    /**
     * Get files for multiple entities of the same class in a single query.
     *
     * @param object[] $entities
     *
     * @return array<int, Files[]> Indexed by entity ID
     */
    public function getFilesForEntities(array $entities, ?string $type = null): array
    {
        if (empty($entities)) {
            return [];
        }

        $firstEntity = reset($entities);
        $entityClass = $this->resolveEntityClass($firstEntity);
        $entityIds = array_map(fn (object $e) => $e->getId(), $entities);

        return $this->filesRepository->findByEntities($entityClass, $entityIds, $type);
    }

    /**
     * Resolve the real entity class (handles Doctrine proxy classes).
     */
    private function resolveEntityClass(object $entity): string
    {
        $class = $entity::class;

        // Strip Doctrine proxy prefix
        if (str_contains($class, 'Proxies\\__CG__\\')) {
            $class = substr($class, strlen('Proxies\\__CG__\\'));
        }

        return $class;
    }

    public function getMaxFileSize(): int
    {
        return $this->uploadMaxFileSize;
    }

    public function getMaxFiles(): int
    {
        return $this->uploadMaxFiles;
    }

    private function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > $this->uploadMaxFileSize) {
            throw new \InvalidArgumentException(
                sprintf('File size exceeds maximum allowed (%d MB).', $this->uploadMaxFileSize / 1024 / 1024)
            );
        }

        $mimeType = $file->getMimeType();
        if ($mimeType !== null && !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('File type "%s" is not allowed.', $mimeType)
            );
        }
    }

    private function getTargetDir(?string $subDir): string
    {
        $targetDir = $subDir ? $this->storageDir . '/' . $subDir : $this->storageDir;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        return $targetDir;
    }
}
