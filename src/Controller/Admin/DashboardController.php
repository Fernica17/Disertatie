<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Audit\AccessLogsCrudController;
use App\Controller\Admin\Audit\AuthenticationLogsCrudController;
use App\Controller\Admin\Audit\EntityChangeLogsCrudController;
use App\Entity\Files;
use App\Entity\Users;
use App\Security\Voter\AdministrationVoter;
use App\Security\Voter\AuditVoter;
use App\Security\Voter\CompaniesVoter;
use App\Security\Voter\FoldersVoter;
use App\Security\Voter\UsersVoter;
use App\Service\DashboardService;
use App\Service\FilesUploadService;
use App\Service\SettingsService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SettingsService $settingsService,
        private readonly FilesUploadService $filesService,
        private readonly DashboardService $dashboardService,
    ) {
    }

    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function index(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        if ($this->isGranted(Users::ROLE_ADMIN)) {
            $role = Users::ROLE_ADMIN;
            $data = $this->dashboardService->getAdminData($user);
        } elseif ($this->isGranted(Users::ROLE_MANAGER)) {
            $role = Users::ROLE_MANAGER;
            $data = $this->dashboardService->getManagerData($user);
        } else {
            $role = Users::ROLE_CLIENT;
            $data = $this->dashboardService->getClientData($user);
        }

        return $this->render('admin/dashboard.html.twig', [
            'user' => $user,
            'role' => $role,
            'data' => $data,
        ]);
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $avatar = null;
        if ($user instanceof Users) {
            $avatar = $this->filesService->getFilesForEntity($user, Files::TYPE_USER_AVATAR)[0] ?? null;
        }

        $avatarUrl = $avatar ? $this->generateUrl('admin_file_view', ['id' => $avatar->getId()]) : null;

        return parent::configureUserMenu($user)
            ->setAvatarUrl($avatarUrl)
            ->setMenuItems([
                MenuItem::linkToUrl($this->translator->trans('admin.nav.my_profile', [], 'admin'), 'fa fa-user-circle', $this->generateUrl('admin_profile')),
                MenuItem::linkToUrl($this->translator->trans('admin.nav.logout', [], 'admin'), 'fa fa-right-from-bracket', $this->generateUrl('app_logout')),
            ]);
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->hideNullValues()
            ->setDefaultRowAction(Action::DETAIL)
            ->setFormOptions(['attr' => ['novalidate' => 'novalidate']])
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
            ->overrideTemplates([
                'layout' => 'admin/layout.html.twig',
                'crud/index' => 'admin/crud/index.html.twig',
                'crud/detail' => 'admin/crud/detail.html.twig',
                'crud/paginator' => 'admin/crud/paginator.html.twig',
            ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->settingsService->get('app_title') ?? 'ERP')
            ->setFaviconPath('images/favicon.png')
            ->setTranslationDomain('admin')
            ->renderContentMaximized()
            ->generateRelativeUrls()
            ->disableDarkMode();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard($this->translator->trans('admin.menu.dashboard', [], 'admin'), 'fa fa-home');

        // Companies
        if ($this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_MANAGER')) {
            yield MenuItem::linkToUrl(
                $this->translator->trans('admin.menu.my_company', [], 'admin'),
                'fa fa-building',
                $this->generateUrl('app_my_company')
            );
        } elseif ($this->isGranted(CompaniesVoter::COMPANIES_LIST)) {
            yield MenuItem::section($this->translator->trans('admin.menu.section.companies', [], 'admin'));
            yield MenuItem::linkTo(CompaniesCrudController::class, $this->translator->trans('admin.menu.all_companies', [], 'admin'), 'fa fa-building');
        }

        // Documents
        if ($this->isGranted(FoldersVoter::FOLDERS_VIEW) || $this->isGranted(FoldersVoter::FOLDERS_VIEW_CLIENT)) {
            yield MenuItem::section($this->translator->trans('admin.menu.section.documents', [], 'admin'));
            if ($this->isGranted(FoldersVoter::FOLDERS_VIEW)) {
                yield MenuItem::linkToRoute(
                    $this->translator->trans('admin.menu.documents_system', [], 'admin'),
                    'fa fa-lock',
                    'admin_documents_system'
                );
                yield MenuItem::linkToRoute(
                    $this->translator->trans('admin.menu.documents_custom', [], 'admin'),
                    'fa fa-folder',
                    'admin_documents_custom'
                );
            }
            yield MenuItem::linkToRoute(
                $this->translator->trans('admin.menu.documents_clients', [], 'admin'),
                'fa fa-user',
                'admin_documents_clients'
            );
        }

        // Administration
        if ($this->isGranted(UsersVoter::USERS_VIEW)) {
            yield MenuItem::section($this->translator->trans('admin.menu.section.administration', [], 'admin'));
            yield MenuItem::linkTo(UsersCrudController::class, $this->translator->trans('admin.menu.users', [], 'admin'), 'fa fa-users');
            yield MenuItem::linkToRoute($this->translator->trans('admin.menu.face_recognition', [], 'admin'), 'fa fa-camera', 'admin_face_recognition');
        }

        if ($this->isGranted(AdministrationVoter::ADMINISTRATION_VIEW)) {
            yield MenuItem::linkToRoute($this->translator->trans('admin.menu.settings', [], 'admin'), 'fa fa-gear', 'admin_settings');

            yield MenuItem::subMenu($this->translator->trans('admin.menu.section.nomenclator', [], 'admin'), 'fa fa-database')->setSubItems([
                MenuItem::linkTo(ListsCrudController::class, $this->translator->trans('admin.menu.lists', [], 'admin'), 'fa fa-list-ul'),
                MenuItem::linkTo(CountriesCrudController::class, $this->translator->trans('admin.nav.countries', [], 'admin'), 'fa fa-globe'),
                MenuItem::linkTo(CountiesCrudController::class, $this->translator->trans('admin.nav.counties', [], 'admin'), 'fa fa-map-location-dot'),
                MenuItem::linkTo(CitiesCrudController::class, $this->translator->trans('admin.nav.cities', [], 'admin'), 'fa fa-city'),
            ]);
        }

        // Audit & Security
        if ($this->isGranted(AuditVoter::AUDIT_VIEW_AUTH_LOGS)) {
            yield MenuItem::section($this->translator->trans('audit.menu.section', [], 'audit'));
            yield MenuItem::linkTo(AuthenticationLogsCrudController::class, $this->translator->trans('audit.menu.authentication_logs', [], 'audit'), 'fa fa-shield-halved');

            if ($this->isGranted(AuditVoter::AUDIT_VIEW_ACCESS_LOGS)) {
                yield MenuItem::linkTo(AccessLogsCrudController::class, $this->translator->trans('audit.menu.access_logs', [], 'audit'), 'fa fa-chart-line');
            }

            if ($this->isGranted(AuditVoter::AUDIT_VIEW_CHANGE_LOGS)) {
                yield MenuItem::linkTo(EntityChangeLogsCrudController::class, $this->translator->trans('audit.menu.entity_change_logs', [], 'audit'), 'fa fa-clock-rotate-left');
            }
        }
    }
}
