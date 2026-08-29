<?php

namespace App\Controller\Admin;

use App\Dto\User\CreateUserDto;
use App\Dto\User\UpdateUserDto;
use App\Entity\Files;
use App\Entity\Users;
use App\Enum\UserRole;
use App\Filter\JsonContainsFilter;
use App\Form\Type\UserFormType;
use App\Security\Voter\UsersVoter;
use App\Service\FilesUploadService;
use App\Service\UsersService;
use App\Traits\ExportableCrudTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(UsersVoter::USERS_VIEW)]
class UsersCrudController extends AbstractCrudController
{
    use ExportableCrudTrait;

    /** @var array<int, Files[]>|null Preloaded avatars map for index page */
    private ?array $userAvatarsMap = null;

    public function __construct(
        private readonly UsersService $usersService,
        private readonly TranslatorInterface $translator,
        private readonly FilesUploadService $filesUploadService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Users::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('users.entity.singular', [], 'users'))
            ->setEntityLabelInPlural($this->translator->trans('users.entity.plural', [], 'users'))
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(25)
            ->setSearchFields(['email', 'firstName', 'lastName'])
            ->showEntityActionsInlined()
            ->setPageTitle('index', $this->translator->trans('users.page.index', [], 'users'))
            ->setPageTitle('new', $this->translator->trans('users.page.new', [], 'users'))
            ->setPageTitle('edit', fn (Users $user) => $this->translator->trans('users.page.edit', ['{name}' => $user->getFullName()], 'users'))
            ->setPageTitle('detail', fn (Users $user) => $user->getFullName());
    }

    public function configureActions(Actions $actions): Actions
    {
        $this->addExportAction($actions);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->add(Crud::PAGE_EDIT, Action::INDEX)

            // Role-based action visibility
            ->setPermission(Action::NEW, UsersVoter::USERS_CREATE)
            ->setPermission(Action::EDIT, UsersVoter::USERS_EDIT)
            ->setPermission(Action::DELETE, UsersVoter::USERS_DELETE)
            ->setPermission(Action::DETAIL, UsersVoter::USERS_VIEW)

            // Customize action icons
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action
                ->setIcon('far fa-square-plus')
                ->setLabel($this->translator->trans('users.action.new', [], 'users')))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action
                ->setIcon('far fa-pen-to-square')
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'tooltip',
                    'title' => $this->translator->trans('users.action.edit', [], 'users'),
                ]))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $action) => $action
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'tooltip',
                    'title' => $this->translator->trans('users.action.detail', [], 'users'),
                ]))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action
                ->setIcon('far fa-trash-can')
                ->setCssClass('text-danger')
                ->setHtmlAttributes([
                    'title' => $this->translator->trans('users.action.delete', [], 'users'),
                ]));
    }

    public function configureFields(string $pageName): iterable
    {
        return match ($pageName) {
            Crud::PAGE_DETAIL => $this->configureDetailFields(),
            // NEW and EDIT render admin/users/user_form.html.twig instead
            default => $this->configureIndexFields(),
        };
    }

    private function configureIndexFields(): iterable
    {
        yield Field::new('avatar', $this->translator->trans('users.field.avatar', [], 'users'))
            ->formatValue(function ($value, $entity) {
                $files = $this->userAvatarsMap[$entity->getId()] ?? null;
                if ($files === null) {
                    $files = $this->filesUploadService->getFilesForEntity($entity, Files::TYPE_USER_AVATAR);
                }
                $file = $files[0] ?? null;

                if ($file) {
                    $url = $this->generateUrl('admin_file_view', ['id' => $file->getId()]);

                    return sprintf(
                        '<img src="%s" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%%;">',
                        htmlspecialchars($url)
                    );
                }

                $firstName = $entity->getFirstName() ?: '?';
                $lastName = $entity->getLastName() ?: '';
                $initials = mb_strtoupper(mb_substr($firstName, 0, 1)) . mb_strtoupper(mb_substr($lastName, 0, 1));
                $fullName = $firstName . ' ' . $lastName;
                $colors = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#198754', '#20c997', '#0dcaf0'];
                $bgColor = $colors[mb_strlen($fullName) % count($colors)];

                return sprintf(
                    '<div style="width: 40px; height: 40px; background-color: %s; color: white; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%%; font-weight: bold; font-size: 14px;">%s</div>',
                    $bgColor,
                    htmlspecialchars($initials)
                );
            });
        yield TextField::new('fullName', $this->translator->trans('users.field.full_name', [], 'users'));
        yield EmailField::new('email', $this->translator->trans('users.field.email', [], 'users'));
        yield AssociationField::new('company', $this->translator->trans('users.field.company', [], 'users'));
        yield ChoiceField::new('primaryRole')
            ->setLabel($this->translator->trans('users.field.role', [], 'users'))
            ->setChoices($this->getRoleChoices());
        yield BooleanField::new('isActive', $this->translator->trans('users.field.is_active', [], 'users'))
            ->renderAsSwitch(false);
        yield DateTimeField::new('createdAt', $this->translator->trans('users.field.created_at', [], 'users'))
            ->setFormat('dd/MM/yyyy HH:mm');
    }

    private function configureDetailFields(): iterable
    {
        yield IdField::new('id');
        yield EmailField::new('email', $this->translator->trans('users.field.email', [], 'users'));
        yield TextField::new('firstName', $this->translator->trans('users.field.first_name', [], 'users'));
        yield TextField::new('lastName', $this->translator->trans('users.field.last_name', [], 'users'));
        yield TextField::new('phone', $this->translator->trans('users.field.phone', [], 'users'));
        yield AssociationField::new('company', $this->translator->trans('users.field.company', [], 'users'));
        yield ChoiceField::new('primaryRole')
            ->setLabel($this->translator->trans('users.field.role', [], 'users'))
            ->setChoices($this->getRoleChoices());
        yield BooleanField::new('isActive', $this->translator->trans('users.field.is_active', [], 'users'))
            ->renderAsSwitch(false);
        yield DateTimeField::new('createdAt', $this->translator->trans('users.field.created_at', [], 'users'))
            ->setFormat('dd/MM/yyyy HH:mm');
        yield DateTimeField::new('updatedAt', $this->translator->trans('users.field.updated_at', [], 'users'))
            ->setFormat('dd/MM/yyyy HH:mm');
    }

    private function getRoleChoices(): array
    {
        $choices = [];
        foreach (UserRole::cases() as $userRole) {
            $choices[$this->translator->trans($userRole->label(), [], 'users')] = $userRole->value;
        }

        return $choices;
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $pageName = $responseParameters->get('pageName');

        if (Crud::PAGE_INDEX === $pageName) {
            $entities = $responseParameters->get('entities');
            if ($entities instanceof EntityCollection) {
                $users = [];
                foreach ($entities as $entityDto) {
                    $instance = $entityDto->getInstance();
                    if ($instance instanceof Users) {
                        $users[] = $instance;
                    }
                }

                if (!empty($users)) {
                    $this->userAvatarsMap = $this->filesUploadService->getFilesForEntities($users, Files::TYPE_USER_AVATAR);
                }
            }
        }

        return $responseParameters;
    }

    public function createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters): \Doctrine\ORM\QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $alias = $qb->getRootAliases()[0];
        $qb->leftJoin(sprintf('%s.company', $alias), 'c')->addSelect('c');

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        $roleChoices = [];
        foreach (UserRole::cases() as $userRole) {
            $roleChoices[$this->translator->trans($userRole->label(), [], 'users')] = $userRole->value;
        }

        return $filters
            ->add(BooleanFilter::new('isActive', $this->translator->trans('users.filter.is_active', [], 'users')))
            ->add(JsonContainsFilter::new('roles', $this->translator->trans('users.filter.role', [], 'users'))->setChoices($roleChoices));
    }

    /**
     * The form is a custom Symfony form rendered through the Magnum layout,
     * matching the profile page, instead of EasyAdmin's default form theme.
     */
    public function new(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_CREATE);

        $user = new Users();
        $form = $this->createForm(UserFormType::class, $user, ['is_new' => true]);
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $dto = new CreateUserDto();
            $dto->email = $user->getEmail();
            $dto->firstName = $user->getFirstName();
            $dto->lastName = $user->getLastName();
            $dto->phone = $user->getPhone();
            $dto->plainPassword = $user->getPlainPassword() ?? '';
            $dto->role = $user->getPrimaryRole();
            $dto->isActive = $user->isActive();
            $dto->company = $user->getCompany();

            $created = $this->usersService->create($dto);

            $this->addFlash('success', $this->translator->trans('users.flash.created', [
                '{name}' => $created->getFullName(),
            ], 'users'));

            $this->handleReferencePhoto($created, $user->getPhotoUpload());

            return $this->redirect($this->indexUrl());
        }

        return $this->render('admin/users/user_form.html.twig', [
            'form' => $form,
            'user' => null,
            'photo' => null,
        ]);
    }

    public function edit(AdminContext $context): Response
    {
        $user = $context->getEntity()->getInstance();

        if (!$user instanceof Users) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(UsersVoter::USERS_EDIT, $user);

        $form = $this->createForm(UserFormType::class, $user);
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $dto = new UpdateUserDto();
            $dto->email = $user->getEmail();
            $dto->firstName = $user->getFirstName();
            $dto->lastName = $user->getLastName();
            $dto->phone = $user->getPhone();
            $dto->plainPassword = $user->getPlainPassword() ?: null;
            $dto->role = $user->getPrimaryRole();
            $dto->isActive = $user->isActive();
            $dto->company = $user->getCompany();

            $this->usersService->update($user, $dto);

            $this->addFlash('success', $this->translator->trans('users.flash.updated', [
                '{name}' => $user->getFullName(),
            ], 'users'));

            $photo = $user->getPhotoUpload();

            if ($photo !== null) {
                // A new upload replaces the old one, so it also settles a removal request.
                $this->handleReferencePhoto($user, $photo);
            } elseif ($user->isRemovePhoto()) {
                $this->usersService->removeReferencePhoto($user);
                $this->addFlash('success', $this->translator->trans('users.flash.photo_removed', [
                    '{name}' => $user->getFullName(),
                ], 'users'));
            }

            return $this->redirect($this->indexUrl());
        }

        return $this->render('admin/users/user_form.html.twig', [
            'form' => $form,
            'user' => $user,
            'photo' => $this->currentPhotoOf($user),
        ]);
    }

    private function indexUrl(): string
    {
        return $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_DELETE, $entityInstance);

        try {
            $this->usersService->delete($entityInstance);
            $this->addFlash('success', $this->translator->trans('users.flash.deleted', [], 'users'));
        } catch (\Exception $e) {
            $this->addFlash('danger', $this->translator->trans('users.flash.delete_error_general', [], 'users'));
        }
    }

    public function createEntity(string $entityFqcn): Users
    {
        return new Users();
    }

    private function currentPhotoOf(mixed $entity): ?Files
    {
        if (!$entity instanceof Users || $entity->getId() === null) {
            return null;
        }

        return $this->filesUploadService->getFilesForEntity($entity, Files::TYPE_USER_AVATAR)[0] ?? null;
    }

    /**
     * Stores the uploaded photo and reports whether face login became available.
     *
     * A photo the recogniser rejects is still kept as the avatar, so the admin
     * gets a warning rather than a failed save.
     */
    private function handleReferencePhoto(Users $user, ?UploadedFile $photo): void
    {
        if ($photo === null) {
            return;
        }

        $problem = $this->usersService->storeReferencePhoto($user, $photo);

        if ($problem === null) {
            $this->addFlash('success', $this->translator->trans('users.flash.face_enrolled', [
                '{name}' => $user->getFullName(),
            ], 'users'));

            return;
        }

        $this->addFlash('warning', $this->translator->trans('users.face.enroll.failed', [
            '{reason}' => $this->translator->trans($problem, [], 'users'),
        ], 'users'));
    }
}
