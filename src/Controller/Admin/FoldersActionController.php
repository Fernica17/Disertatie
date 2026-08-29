<?php

namespace App\Controller\Admin;

use App\Entity\Companies;
use App\Entity\Files;
use App\Entity\Folders;
use App\Entity\Users;
use App\Enum\FolderType;
use App\Repository\FoldersRepository;
use App\Security\Voter\FoldersVoter;
use App\Service\FilesUploadService;
use App\Service\FoldersService;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class FoldersActionController extends AbstractController
{
    private const array ENTITY_CRUD_MAP = [
        'App\\Entity\\Companies' => CompaniesCrudController::class,
    ];

    public function __construct(
        private readonly FoldersService $foldersService,
        private readonly FoldersRepository $foldersRepository,
        private readonly FilesUploadService $filesUploadService,
        private readonly TranslatorInterface $translator,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[Route('/admin/folders/tree', name: 'admin_folders_tree', methods: ['GET'])]
    public function tree(): JsonResponse
    {
        /** @var Users $user */
        $user = $this->getUser();
        $isClient = $this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_MANAGER');

        if ($isClient) {
            $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_VIEW_CLIENT);
            $tree = $this->foldersService->getTree(FolderType::CLIENT, $user->getCompany());
        } else {
            $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_VIEW);
            $tree = $this->foldersService->getTree();
        }

        return $this->json($this->serializeTree($tree));
    }

    #[Route('/admin/folders/{id}/contents', name: 'admin_folders_contents', methods: ['GET'])]
    public function contents(Folders $folder, Request $request): JsonResponse
    {
        $this->checkFolderViewAccess($folder);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $search = $request->query->get('search');

        $result = match (true) {
            $folder->isSystem() => $this->foldersService->getSystemFolderContents($folder, $page, $limit, $search),
            $folder->isCustom() => $this->foldersService->getCustomFolderContents($folder, $page, $limit, $search),
            $folder->isClient() && $folder->getSystemMapping() !== null => $this->foldersService->getClientFolderContents(
                $folder,
                $this->resolveCompany($folder),
                $page,
                $limit,
            ),
            default => ['files' => [], 'total' => 0],
        };

        $breadcrumb = $this->foldersService->getFolderPath($folder);

        // Include subfolders for navigation
        $subfolders = [];
        foreach ($folder->getChildren() as $child) {
            $subfolders[] = [
                'id' => $child->getId(),
                'name' => $child->getName(),
                'type' => $child->getType()->value,
                'icon' => $child->getType()->icon(),
                'hasSystemMapping' => $child->getSystemMapping() !== null,
            ];
        }

        return $this->json([
            'files' => array_map(fn (Files $f) => $this->serializeFile($f), $result['files']),
            'subfolders' => $subfolders,
            'total' => $result['total'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / $limit),
            'breadcrumb' => array_map(fn (Folders $f) => [
                'id' => $f->getId(),
                'name' => $f->getName(),
            ], $breadcrumb),
            'folder' => [
                'id' => $folder->getId(),
                'name' => $folder->getName(),
                'type' => $folder->getType()->value,
                'canUpload' => $folder->isCustom() && $this->isGranted(FoldersVoter::FOLDERS_UPLOAD),
                'canCreateSubfolder' => ($folder->isCustom() || $folder->isClient()) && $this->isGranted(FoldersVoter::FOLDERS_CREATE),
                'canManageFiles' => ($folder->isCustom() || $folder->isClient()) && $this->isGranted(FoldersVoter::FOLDERS_MOVE_FILE),
            ],
        ]);
    }

    #[Route('/admin/folders/{id}/stats', name: 'admin_folders_stats', methods: ['GET'])]
    public function stats(Folders $folder): JsonResponse
    {
        $this->checkFolderViewAccess($folder);

        $stats = $this->foldersService->getFolderStats($folder);

        return $this->json($stats);
    }

    #[Route('/admin/folders/create', name: 'admin_folders_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_CREATE);

        if ($csrfError = $this->validateCsrf($request)) {
            return $csrfError;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $name = trim($data['name'] ?? '');
        $parentId = $data['parentId'] ?? null;

        if ($name === '') {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('documents.error.name_required', [], 'documents'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $parent = null;
        if ($parentId !== null) {
            $parent = $this->foldersRepository->find($parentId);
            if ($parent === null) {
                return $this->json([
                    'success' => false,
                    'message' => $this->translator->trans('documents.error.parent_not_found', [], 'documents'),
                ], Response::HTTP_NOT_FOUND);
            }
        }

        try {
            /** @var Users $user */
            $user = $this->getUser();
            $folder = $this->foldersService->createFolder($name, $parent, $user);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('documents.flash.folder_created', [], 'documents'),
                'folder' => [
                    'id' => $folder->getId(),
                    'name' => $folder->getName(),
                    'slug' => $folder->getSlug(),
                    'type' => $folder->getType()->value,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/admin/folders/{id}/rename', name: 'admin_folders_rename', methods: ['PUT'])]
    public function rename(Folders $folder, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_EDIT);

        if ($csrfError = $this->validateCsrf($request)) {
            return $csrfError;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $newName = trim($data['name'] ?? '');

        if ($newName === '') {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('documents.error.name_required', [], 'documents'),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->foldersService->renameFolder($folder, $newName);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('documents.flash.folder_renamed', [], 'documents'),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/admin/folders/{id}/delete', name: 'admin_folders_delete', methods: ['DELETE'])]
    public function delete(Folders $folder, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_DELETE);

        if ($csrfError = $this->validateCsrf($request)) {
            return $csrfError;
        }

        try {
            $this->foldersService->deleteFolder($folder);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('documents.flash.folder_deleted', [], 'documents'),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/admin/folders/{id}/move', name: 'admin_folders_move', methods: ['PUT'])]
    public function move(Folders $folder, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_EDIT);

        if ($csrfError = $this->validateCsrf($request)) {
            return $csrfError;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $targetParentId = $data['targetParentId'] ?? null;

        $newParent = null;
        if ($targetParentId !== null) {
            $newParent = $this->foldersRepository->find($targetParentId);
            if ($newParent === null) {
                return $this->json([
                    'success' => false,
                    'message' => $this->translator->trans('documents.error.target_not_found', [], 'documents'),
                ], Response::HTTP_NOT_FOUND);
            }
        }

        try {
            $this->foldersService->moveFolder($folder, $newParent);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('documents.flash.folder_moved', [], 'documents'),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/admin/folders/{id}/upload', name: 'admin_folders_upload', methods: ['POST'])]
    public function upload(Folders $folder, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_UPLOAD);

        if ($csrfError = $this->validateCsrf($request)) {
            return $csrfError;
        }

        $uploadedFiles = $request->files->get('files', []);

        if (empty($uploadedFiles)) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('documents.error.no_files_provided', [], 'documents'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $maxFiles = $this->filesUploadService->getMaxFiles();
        if (count($uploadedFiles) > $maxFiles) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('documents.error.max_files_exceeded', ['{max}' => $maxFiles], 'documents'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $maxFileSize = $this->filesUploadService->getMaxFileSize();
        foreach ($uploadedFiles as $file) {
            if ($file->getSize() > $maxFileSize) {
                return $this->json([
                    'success' => false,
                    'message' => $this->translator->trans('documents.error.file_too_large', [
                        '{name}' => $file->getClientOriginalName(),
                        '{max}' => intdiv($maxFileSize, 1024 * 1024) . ' MB',
                    ], 'documents'),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            /** @var Users $user */
            $user = $this->getUser();
            $files = $this->foldersService->uploadToFolder($folder, $uploadedFiles, $user);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('documents.flash.files_uploaded', [
                    '{count}' => count($files),
                ], 'documents'),
                'files' => array_map(fn (Files $f) => $this->serializeFile($f), $files),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/admin/folders/files/{id}/move', name: 'admin_folders_file_move', methods: ['PUT'])]
    public function moveFile(Files $file, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_MOVE_FILE);

        if ($csrfError = $this->validateCsrf($request)) {
            return $csrfError;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $targetFolderId = $data['targetFolderId'] ?? null;

        if ($targetFolderId === null) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('documents.error.target_id_required', [], 'documents'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $targetFolder = $this->foldersRepository->find($targetFolderId);
        if ($targetFolder === null) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('documents.error.target_not_found', [], 'documents'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->foldersService->moveFile($file, $targetFolder);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('documents.flash.file_moved', [], 'documents'),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/admin/folders/files/{id}/delete', name: 'admin_folders_file_delete', methods: ['DELETE'])]
    public function deleteFile(Files $file, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_DELETE);

        if ($csrfError = $this->validateCsrf($request)) {
            return $csrfError;
        }

        try {
            $this->foldersService->deleteFile($file);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('documents.flash.file_deleted', [], 'documents'),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/admin/folders/search', name: 'admin_folders_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_VIEW);

        $query = trim($request->query->get('q', ''));
        $folderId = $request->query->getInt('folderId', 0);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));

        if ($query === '') {
            return $this->json(['files' => [], 'total' => 0]);
        }

        $folder = null;
        if ($folderId > 0) {
            $folder = $this->foldersRepository->find($folderId);
        }

        $result = $this->foldersService->searchFiles($query, $folder, $page, $limit);

        return $this->json([
            'files' => array_map(fn (Files $f) => $this->serializeFile($f), $result['files']),
            'total' => $result['total'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / $limit),
        ]);
    }

    private function checkFolderViewAccess(Folders $folder): void
    {
        if ($folder->isClient()) {
            $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_VIEW_CLIENT, $folder);
        } else {
            $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_VIEW);
        }
    }

    private function validateCsrf(Request $request): ?JsonResponse
    {
        if (!$this->isCsrfTokenValid('folders_action', $request->headers->get('X-CSRF-Token'))) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('documents.error.invalid_token', [], 'documents'),
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function resolveCompany(Folders $folder): Companies
    {
        $current = $folder;
        while ($current !== null) {
            if ($current->getCompany() !== null) {
                return $current->getCompany();
            }
            $current = $current->getParent();
        }

        throw new \LogicException('CLIENT folder has no associated company.');
    }

    private function handleException(\Exception $e): JsonResponse
    {
        if ($e instanceof \InvalidArgumentException || $e instanceof \LogicException) {
            $translationKey = $this->mapExceptionToTranslationKey($e->getMessage());
            $message = $this->translator->trans($translationKey, [], 'documents');
        } else {
            $message = $this->translator->trans('documents.error.generic', [], 'documents');
        }

        return $this->json([
            'success' => false,
            'message' => $message,
        ], Response::HTTP_BAD_REQUEST);
    }

    private function mapExceptionToTranslationKey(string $exceptionMessage): string
    {
        return match (true) {
            str_contains($exceptionMessage, 'CUSTOM folders') => 'documents.error.folder_not_custom',
            str_contains($exceptionMessage, 'sub-tree') => 'documents.error.cannot_move_to_self',
            str_contains($exceptionMessage, 'into itself') => 'documents.error.cannot_move_to_self',
            str_contains($exceptionMessage, 'slug') => 'documents.error.slug_conflict',
            default => 'documents.error.generic',
        };
    }

    private function serializeFile(Files $file): array
    {
        return [
            'id' => $file->getId(),
            'originalName' => $file->getOriginalName(),
            'mimeType' => $file->getMimeType(),
            'size' => $file->getSize(),
            'formattedSize' => $file->getFormattedSize(),
            'type' => $file->getType(),
            'createdAt' => $file->getCreatedAt()?->format('Y-m-d H:i'),
            'downloadUrl' => $this->generateUrl('admin_file_download', ['id' => $file->getId()]),
            'viewUrl' => $this->generateUrl('admin_file_view', ['id' => $file->getId()]),
            'folderId' => $file->getFolder()?->getId(),
            'isCustom' => $file->getFolder()?->isCustom() ?? false,
            'entityUrl' => $this->buildEntityDetailUrl($file),
        ];
    }

    private function buildEntityDetailUrl(Files $file): ?string
    {
        $entityClass = $file->getEntity();
        $entityId = $file->getEntityId();
        $crudClass = self::ENTITY_CRUD_MAP[$entityClass] ?? null;

        if ($crudClass === null || $entityId === null) {
            return null;
        }

        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($crudClass)
            ->setAction('detail')
            ->setEntityId($entityId)
            ->generateUrl();
    }

    /**
     * @param Folders[] $folders
     */
    private function serializeTree(array $folders): array
    {
        return array_map(function (Folders $folder) {
            $data = [
                'id' => $folder->getId(),
                'name' => $folder->getName(),
                'slug' => $folder->getSlug(),
                'type' => $folder->getType()->value,
                'icon' => $folder->getType()->icon(),
                'hasSystemMapping' => $folder->getSystemMapping() !== null,
                'children' => $this->serializeTree($folder->getChildren()->toArray()),
            ];

            return $data;
        }, $folders);
    }
}
