<?php

namespace App\Controller\Admin;

use App\Entity\Companies;
use App\Entity\Files;
use App\Entity\Users;
use App\Enum\CompanyStatus;
use App\Enum\PersonType;
use App\Form\Type\CompanyFormType;
use App\Repository\FilesRepository;
use App\Repository\UsersRepository;
use App\Security\Voter\CompaniesVoter;
use App\Service\CompaniesService;
use App\Service\FilesUploadService;
use App\Traits\ExportableCrudTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(CompaniesVoter::COMPANIES_VIEW)]
class CompaniesCrudController extends AbstractCrudController
{
    use ExportableCrudTrait;

    /** @var array<int, Files[]>|null Preloaded logos map for index page */
    private ?array $companyLogosMap = null;

    public function __construct(
        private readonly CompaniesService $companiesService,
        protected readonly TranslatorInterface $translator,
        private readonly FilesUploadService $filesUploadService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly FilesRepository $filesRepository,
        private readonly UsersRepository $usersRepository,
        private readonly string $companiesFilesDir,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Companies::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('companies.entity.singular', [], 'companies'))
            ->setEntityLabelInPlural($this->translator->trans('companies.entity.plural', [], 'companies'))
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(25)
            ->setSearchFields(['name', 'fiscalCode', 'email', 'phone', 'contactPersonName'])
            ->showEntityActionsInlined()
            ->setPageTitle('index', $this->translator->trans('companies.page.index', [], 'companies'))
            ->setPageTitle('new', $this->translator->trans('companies.page.new', [], 'companies'))
            ->setPageTitle('edit', fn (Companies $company) => $this->translator->trans('companies.page.edit', ['{name}' => $company->getName()], 'companies'))
            ->setPageTitle('detail', fn (Companies $company) => $company->getName())
            ->overrideTemplate('crud/detail', 'admin/companies/detail.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $this->addExportAction($actions);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)

            // Permission-based action visibility via CompaniesVoter
            ->setPermission(Action::INDEX, CompaniesVoter::COMPANIES_LIST)
            ->setPermission(Action::NEW, CompaniesVoter::COMPANIES_CREATE)
            ->setPermission(Action::EDIT, CompaniesVoter::COMPANIES_EDIT)
            ->setPermission(Action::DELETE, CompaniesVoter::COMPANIES_DELETE)
            ->setPermission(Action::DETAIL, CompaniesVoter::COMPANIES_VIEW)

            // Customize action icons
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action
                ->setIcon('far fa-square-plus')
                ->setLabel($this->translator->trans('companies.action.new', [], 'companies'
                )))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action
                ->setIcon('far fa-pen-to-square')
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'tooltip',
                    'title' => $this->translator->trans('companies.action.edit', [], 'companies'),
                ]))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'tooltip',
                    'title' => $this->translator->trans('companies.action.delete', [], 'companies'),
                ]))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $action) => $action
                ->setIcon('far fa-eye')
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'tooltip',
                    'title' => $this->translator->trans('companies.action.detail', [], 'companies'),
                ]));
    }

    public function configureFields(string $pageName): iterable
    {
        // Only index page uses EasyAdmin fields; new/edit/detail use custom templates
        yield from $this->configureIndexFields();
    }

    protected function getLogoField(): Field
    {
        return Field::new('logoUpload', $this->translator->trans('companies.field.logo', [], 'companies'))
            ->setFormType(FileType::class)
            ->setFormTypeOptions(['required' => false])
            ->setHelp($this->translator->trans('companies.help.logo_format', [], 'companies'))
            ->formatValue(function ($value, $entity) {
                $files = $this->companyLogosMap[$entity->getId()] ?? null;
                if ($files === null) {
                    $files = $this->filesUploadService->getFilesForEntity($entity, Files::TYPE_COMPANIES_LOGO);
                }
                $file = $files[0] ?? null;

                if ($file) {
                    $url = $this->generateUrl('admin_file_view', ['id' => $file->getId()]);

                    return sprintf(
                        '<img src="%s" height="50px" style="max-width: 100px; object-fit: contain; border-radius: 4px; border: 1px solid #dee2e6;">',
                        htmlspecialchars($url)
                    );
                }

                $companyName = $entity->getName() ?: '?';
                $firstLetter = mb_strtoupper(mb_substr($companyName, 0, 1));
                $colors = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#198754', '#20c997', '#0dcaf0'];
                $colorIndex = abs(crc32($companyName)) % count($colors);
                $bgColor = $colors[$colorIndex];

                return sprintf(
                    '<div style="width: 50px; height: 50px; background-color: %s; color: white; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; font-weight: bold; font-size: 20px;">%s</div>',
                    $bgColor,
                    $firstLetter
                );
            });
    }

    private function configureIndexFields(): iterable
    {
        yield $this->getLogoField();
        yield TextField::new('name', $this->translator->trans('companies.field.name', [], 'companies'));
        yield TextField::new('accountManager', $this->translator->trans('companies.field.account_manager', [], 'companies'))
            ->formatValue(fn ($value) => $value ? (string) $value : null);
        yield TextField::new('fiscalCode', $this->translator->trans('companies.field.fiscal_code', [], 'companies'));
        yield TextField::new('clientType', $this->translator->trans('companies.field.client_type', [], 'companies'))
            ->formatValue(fn ($value) => $value ?: null);
        yield TextField::new('phone', $this->translator->trans('companies.field.phone', [], 'companies'));
        yield TextField::new('email', $this->translator->trans('companies.field.email', [], 'companies'));
        yield ChoiceField::new('status', $this->translator->trans('companies.field.status', [], 'companies'))
            ->setChoices([
                $this->translator->trans('companies.status.prospect', [], 'companies') => CompanyStatus::PROSPECT->value,
                $this->translator->trans('companies.status.active', [], 'companies') => CompanyStatus::ACTIVE->value,
                $this->translator->trans('companies.status.suspended', [], 'companies') => CompanyStatus::SUSPENDED->value,
                $this->translator->trans('companies.status.blocked', [], 'companies') => CompanyStatus::BLOCKED->value,
                $this->translator->trans('companies.status.finalized', [], 'companies') => CompanyStatus::FINALIZED->value,
            ])
            ->renderAsBadges([
                CompanyStatus::PROSPECT->value => 'info',
                CompanyStatus::ACTIVE->value => 'success',
                CompanyStatus::SUSPENDED->value => 'warning',
                CompanyStatus::BLOCKED->value => 'danger',
                CompanyStatus::FINALIZED->value => 'secondary',
            ]);
    }

    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted(CompaniesVoter::COMPANIES_CREATE);

        $company = new Companies();

        $form = $this->createForm(CompanyFormType::class, $company);
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->companiesService->create($company);

                $this->handleFileUploads($form, $company);

                $this->addFlash('success', $this->translator->trans('companies.flash.created', [
                    '{name}' => $company->getName(),
                ], 'companies'));

                return $this->redirectToDetail($company);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/companies/company_form.html.twig', [
            'form' => $form,
            'company' => null,
            'companyLogo' => [],
            'companyDocuments' => [],
            'entityLabel' => $this->getEntityLabel(),
        ]);
    }

    public function edit(AdminContext $context)
    {
        $company = $context->getEntity()->getInstance();

        if (!$company instanceof Companies) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(CompaniesVoter::COMPANIES_EDIT, $company);

        $form = $this->createForm(CompanyFormType::class, $company);
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->companiesService->update($company);

                $this->handleFileRemovals($context->getRequest());
                $this->handleFileUploads($form, $company);

                $this->addFlash('success', $this->translator->trans('companies.flash.updated', [
                    '{name}' => $company->getName(),
                ], 'companies'));

                return $this->redirectToDetail($company);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/companies/company_form.html.twig', [
            'form' => $form,
            'company' => $company,
            'companyLogo' => $this->filesUploadService->getFilesForEntity($company, Files::TYPE_COMPANIES_LOGO),
            'companyDocuments' => $this->filesUploadService->getFilesForEntity($company, Files::TYPE_COMPANIES_DOCUMENT),
            'entityLabel' => $this->getEntityLabel(),
        ]);
    }

    public function detail(AdminContext $context)
    {
        $company = $context->getEntity()->getInstance();

        if ($company instanceof Companies) {
            $this->denyAccessUnlessGranted(CompaniesVoter::COMPANIES_VIEW, $company);
        }

        return parent::detail($context);
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $pageName = $responseParameters->get('pageName');

        if (Crud::PAGE_INDEX === $pageName) {
            // Batch-load logos for all companies on the index page
            $entities = $responseParameters->get('entities');
            if ($entities instanceof EntityCollection) {
                $companies = [];
                foreach ($entities as $entityDto) {
                    $instance = $entityDto->getInstance();
                    if ($instance instanceof Companies) {
                        $companies[] = $instance;
                    }
                }

                if (!empty($companies)) {
                    $this->companyLogosMap = $this->filesUploadService->getFilesForEntities($companies, Files::TYPE_COMPANIES_LOGO);
                }
            }
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            $entity = $responseParameters->get('entity');
            $company = $entity->getInstance();

            $companyLogo = $this->filesUploadService->getFilesForEntity($company, Files::TYPE_COMPANIES_LOGO);
            $companyDocuments = $this->filesUploadService->getFilesForEntity($company, Files::TYPE_COMPANIES_DOCUMENT);
            $companyUsers = $this->usersRepository->findBy(['company' => $company], ['lastName' => 'ASC', 'firstName' => 'ASC']);

            $responseParameters->set('companyLogo', $companyLogo);
            $responseParameters->set('companyDocuments', $companyDocuments);
            $responseParameters->set('companyUsers', $companyUsers);
        }

        return $responseParameters;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', $this->translator->trans('companies.field.name', [], 'companies')))
            ->add(TextFilter::new('fiscalCode', $this->translator->trans('companies.field.fiscal_code', [], 'companies')))
            ->add(EntityFilter::new('accountManager', $this->translator->trans('companies.field.account_manager', [], 'companies'))
                ->setFormTypeOption('value_type_options.query_builder', fn (UsersRepository $repo) => $repo->createManagersQueryBuilder()
                    ->orderBy('u.lastName', 'ASC')))
            ->add(ChoiceFilter::new('personType', $this->translator->trans('companies.filter.person_type', [], 'companies'))->setChoices([
                $this->translator->trans('companies.choice.legal_person', [], 'companies') => PersonType::LEGAL->value,
                $this->translator->trans('companies.choice.physical_person', [], 'companies') => PersonType::PHYSICAL->value,
            ]))
            ->add(ChoiceFilter::new('status', $this->translator->trans('companies.filter.status', [], 'companies'))->setChoices([
                $this->translator->trans('companies.status.prospect', [], 'companies') => CompanyStatus::PROSPECT->value,
                $this->translator->trans('companies.status.active', [], 'companies') => CompanyStatus::ACTIVE->value,
                $this->translator->trans('companies.status.suspended', [], 'companies') => CompanyStatus::SUSPENDED->value,
                $this->translator->trans('companies.status.blocked', [], 'companies') => CompanyStatus::BLOCKED->value,
                $this->translator->trans('companies.status.finalized', [], 'companies') => CompanyStatus::FINALIZED->value,
            ]));
    }

    /**
     * Optimize queries with eager loading to prevent N+1.
     */
    public function createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Eager load relationships
        $qb->leftJoin('entity.clientType', 'ct')
            ->addSelect('ct')
            ->leftJoin('entity.industry', 'ind')
            ->addSelect('ind')
            ->leftJoin('entity.companySize', 'cs')
            ->addSelect('cs')
            ->leftJoin('entity.accountManager', 'am')
            ->addSelect('am');

        if ($this->isGranted('ROLE_CLIENT')) {
            /** @var Users $user */
            $user = $this->getUser();
            $qb->andWhere('entity.id = :clientCompanyId')
                ->setParameter('clientCompanyId', $user->getCompany()?->getId());
        }

        return $qb;
    }

    /**
     * Override delete to use service layer.
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->denyAccessUnlessGranted(CompaniesVoter::COMPANIES_DELETE, $entityInstance);

        $this->companiesService->delete($entityInstance);

        $this->addFlash('success', $this->translator->trans('companies.flash.deleted', [
            '{name}' => $entityInstance->getName(),
        ], 'companies'));
    }

    protected function getEntityLabel(): string
    {
        return $this->translator->trans('companies.entity.plural', [], 'companies');
    }

    private function handleFileUploads(FormInterface $form, Companies $company): void
    {
        $logoFile = $form->get('logo')->getData();
        if ($logoFile) {
            $oldLogos = $this->filesUploadService->getFilesForEntity($company, Files::TYPE_COMPANIES_LOGO);
            foreach ($oldLogos as $oldLogo) {
                $this->filesUploadService->remove($oldLogo, $this->companiesFilesDir);
            }
            $this->filesUploadService->upload($logoFile, $company, $this->companiesFilesDir, Files::TYPE_COMPANIES_LOGO);
        }

        $documents = $form->get('documents')->getData();
        if (!empty($documents)) {
            $this->filesUploadService->uploadBatch($documents, $company, $this->companiesFilesDir, Files::TYPE_COMPANIES_DOCUMENT);
        }
    }

    private function handleFileRemovals(\Symfony\Component\HttpFoundation\Request $request): void
    {
        $removeLogoIds = array_map('intval', $request->request->all('removeLogo') ?: []);
        $removeDocIds = array_map('intval', $request->request->all('removeDocuments') ?: []);
        $removeIds = array_merge($removeLogoIds, $removeDocIds);

        foreach ($removeIds as $fileId) {
            $file = $this->filesRepository->find($fileId);
            if ($file) {
                $this->filesUploadService->remove($file, $this->companiesFilesDir);
            }
        }
    }

    private function redirectToDetail(Companies $company): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(static::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($company->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}
