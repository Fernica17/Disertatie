<?php

namespace App\Controller\Admin;

use App\Entity\Users;
use App\Enum\FolderType;
use App\Security\Voter\FoldersVoter;
use App\Service\FoldersService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class FolderManagerController extends AbstractController
{
    public function __construct(
        private readonly FoldersService $foldersService,
    ) {
    }

    #[AdminRoute('/documents', name: 'documents')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_documents_system');
    }

    #[AdminRoute('/documents/system', name: 'documents_system')]
    public function system(): Response
    {
        $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_VIEW);

        $tree = $this->foldersService->getTree(FolderType::SYSTEM);

        return $this->render('admin/folders/index.html.twig', [
            'tree' => $tree,
            'folderType' => 'system',
            'isClient' => false,
            'canManage' => false,
        ]);
    }

    #[AdminRoute('/documents/custom', name: 'documents_custom')]
    public function custom(): Response
    {
        $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_VIEW);

        $tree = $this->foldersService->getTree(FolderType::CUSTOM);

        return $this->render('admin/folders/index.html.twig', [
            'tree' => $tree,
            'folderType' => 'custom',
            'isClient' => false,
            'canManage' => $this->isGranted('ROLE_MANAGER'),
        ]);
    }

    #[AdminRoute('/documents/clients', name: 'documents_clients')]
    public function clients(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();
        $isClient = $this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_MANAGER');

        if ($isClient) {
            $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_VIEW_CLIENT);
            $company = $user->getCompany();
            $tree = $this->foldersService->getTree(FolderType::CLIENT, $company);
        } else {
            $this->denyAccessUnlessGranted(FoldersVoter::FOLDERS_VIEW);
            $tree = $this->foldersService->getTree(FolderType::CLIENT);
        }

        return $this->render('admin/folders/index.html.twig', [
            'tree' => $tree,
            'folderType' => 'client',
            'isClient' => $isClient,
            'canManage' => $this->isGranted('ROLE_MANAGER'),
        ]);
    }
}
