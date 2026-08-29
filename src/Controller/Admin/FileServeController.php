<?php

namespace App\Controller\Admin;

use App\Entity\Companies;
use App\Entity\Files;
use App\Entity\Folders;
use App\Entity\Users;
use App\Repository\FilesRepository;
use App\Security\Voter\CompaniesVoter;
use App\Security\Voter\FoldersVoter;
use App\Security\Voter\UsersVoter;
use App\Service\FilesUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class FileServeController extends AbstractController
{
    private const array ENTITY_VOTER_MAP = [
        Companies::class => CompaniesVoter::COMPANIES_VIEW,
        Users::class => UsersVoter::USERS_VIEW_AVATAR,
        Folders::class => FoldersVoter::FOLDERS_DOWNLOAD,
    ];

    public function __construct(
        private readonly FilesRepository $filesRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesUploadService $filesUploadService,
    ) {
    }

    #[Route('/admin/files/{id}/view', name: 'admin_file_view', methods: ['GET'])]
    public function view(int $id): BinaryFileResponse
    {
        $file = $this->findFileOrFail($id);

        $response = new BinaryFileResponse($this->getPhysicalPath($file));
        $response->headers->set('Content-Type', $file->getMimeType());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file->getOriginalName());

        return $response;
    }

    #[Route('/admin/files/{id}/download', name: 'admin_file_download', methods: ['GET'])]
    public function download(int $id): BinaryFileResponse
    {
        $file = $this->findFileOrFail($id);

        $response = new BinaryFileResponse($this->getPhysicalPath($file));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getOriginalName());

        return $response;
    }

    private function findFileOrFail(int $id): Files
    {
        $file = $this->filesRepository->find($id);
        if (!$file) {
            throw $this->createNotFoundException('File not found.');
        }

        $this->checkEntityAccess($file);

        return $file;
    }

    private function checkEntityAccess(Files $file): void
    {
        $entityClass = $file->getEntity();
        $voterPermission = self::ENTITY_VOTER_MAP[$entityClass] ?? null;

        if ($voterPermission === null) {
            throw $this->createAccessDeniedException('No access configured for this file type.');
        }

        $subject = $this->entityManager->find($entityClass, $file->getEntityId());
        if ($subject === null) {
            throw $this->createNotFoundException('Parent entity not found.');
        }

        $this->denyAccessUnlessGranted($voterPermission, $subject);
    }

    private function getPhysicalPath(Files $file): string
    {
        $physicalPath = $this->filesUploadService->absolutePath($file);

        if ($physicalPath === null) {
            throw $this->createNotFoundException('Physical file not found.');
        }

        return $physicalPath;
    }
}
