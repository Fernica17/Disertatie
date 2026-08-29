<?php

namespace App\Controller\Admin;

use App\Entity\Users;
use App\Security\Voter\CompaniesVoter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MyCompanyController extends AbstractController
{
    #[Route('/admin/my-company', name: 'app_my_company')]
    #[IsGranted(CompaniesVoter::COMPANIES_VIEW)]
    public function index(AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var Users $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if ($company === null) {
            throw $this->createAccessDeniedException();
        }

        $url = $adminUrlGenerator
            ->setController(CompaniesCrudController::class)
            ->setAction('detail')
            ->setEntityId($company->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}
