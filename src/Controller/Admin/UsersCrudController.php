<?php

namespace App\Controller\Admin;

use App\Dto\User\CreateUserDto;
use App\Dto\User\UpdateUserDto;
use App\Entity\Files;
use App\Entity\Users;
use App\Enum\UserRole;
use App\Filter\JsonContainsFilter;
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
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
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
            Crud::PAGE_INDEX => $this->configureIndexFields(),
            Crud::PAGE_DETAIL => $this->configureDetailFields(),
            default => $this->configureFormFields($pageName),
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

    private function configureFormFields(string $pageName): iterable
    {
        yield TextField::new('email', $this->translator->trans('users.field.email', [], 'users'))
            ->setRequired(true);
        yield TextField::new('firstName', $this->translator->trans('users.field.first_name', [], 'users'))
            ->setRequired(true);
        yield TextField::new('lastName', $this->translator->trans('users.field.last_name', [], 'users'))
            ->setRequired(true);
        yield TextField::new('phone', $this->translator->trans('users.field.phone', [], 'users'))
            ->setRequired(false);
        $isNew = $pageName === Crud::PAGE_NEW;
        $passwordConstraints = [
            new Length(min: 6, minMessage: 'validation.user.password.min_length'),
            new Regex(pattern: '/[A-Z]/', message: 'validation.user.password.uppercase'),
            new Regex(pattern: '/[a-z]/', message: 'validation.user.password.lowercase'),
            new Regex(pattern: '/[0-9]/', message: 'validation.user.password.digit'),
            new Regex(pattern: '/[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\\\\/~]/', message: 'validation.user.password.special'),
        ];
        if ($isNew) {
            array_unshift($passwordConstraints, new NotBlank(message: 'validation.user.password.required'));
        }

        yield TextField::new('plainPassword', $this->translator->trans('users.field.password', [], 'users'))
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'invalid_message' => 'validation.user.passwords_mismatch',
                'constraints' => $passwordConstraints,
                'first_options' => [
                    'label' => $this->translator->trans('users.field.password', [], 'users'),
                    'attr' => [
                        'class' => 'form-control password-field',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => $this->translator->trans('users.field.confirm_password', [], 'users'),
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'required' => $isNew,
            ])
            ->setRequired($isNew);
        yield AssociationField::new('company', $this->translator->trans('users.field.company', [], 'users'))
            ->setRequired(false)
            ->setPermission('ROLE_ADMIN');
        yield ChoiceField::new('primaryRole')
            ->setLabel($this->translator->trans('users.field.role', [], 'users'))
            ->setChoices($this->getRoleChoices())
            ->setRequired(false)
            ->setPermission('ROLE_ADMIN');
        yield BooleanField::new('isActive', $this->translator->trans('users.field.is_active', [], 'users'))
            ->renderAsSwitch(true);
        $currentPhoto = $isNew ? null : $this->currentPhotoOf($this->getContext()?->getEntity()?->getInstance());

        yield Field::new('photoUpload', $this->translator->trans('users.field.photo', [], 'users'))
            ->setFormType(FileType::class)
            ->setFormTypeOptions([
                'required' => false,
                'mapped' => true,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [
                    new Assert\File(
                        maxSize: '8M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'validation.user.photo.mime',
                    ),
                ],
            ])
            ->setHelp($this->renderView('admin/users/_photo_preview.html.twig', ['photo' => $currentPhoto]))
            ->setFormTypeOption('help_html', true)
            ->onlyOnForms();

        // Backs the red remove button in the preview widget. Hidden by the
        // Stimulus controller so the intent is expressed once, by the button.
        if ($currentPhoto !== null) {
            yield BooleanField::new('removePhoto', $this->translator->trans('users.field.remove_photo', [], 'users'))
                ->renderAsSwitch(false)
                ->onlyOnForms();
        }
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

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_CREATE);

        $dto = new CreateUserDto();
        $dto->email = $entityInstance->getEmail();
        $dto->firstName = $entityInstance->getFirstName();
        $dto->lastName = $entityInstance->getLastName();
        $dto->phone = $entityInstance->getPhone();
        $dto->plainPassword = $entityInstance->getPlainPassword() ?? '';
        $dto->role = $entityInstance->getPrimaryRole();
        $dto->isActive = $entityInstance->isActive();
        $dto->company = $entityInstance->getCompany();

        $user = $this->usersService->create($dto);
        $this->addFlash('success', $this->translator->trans('users.flash.created', [
            '{name}' => $entityInstance->getFullName(),
        ], 'users'));

        $this->handleReferencePhoto($user, $entityInstance->getPhotoUpload());
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_EDIT, $entityInstance);

        $dto = new UpdateUserDto();
        $dto->email = $entityInstance->getEmail();
        $dto->firstName = $entityInstance->getFirstName();
        $dto->lastName = $entityInstance->getLastName();
        $dto->phone = $entityInstance->getPhone();
        $dto->plainPassword = $entityInstance->getPlainPassword() ?: null;
        $dto->role = $entityInstance->getPrimaryRole();
        $dto->isActive = $entityInstance->isActive();
        $dto->company = $entityInstance->getCompany();

        $this->usersService->update($entityInstance, $dto);

        $this->addFlash('success', $this->translator->trans('users.flash.updated', [
            '{name}' => $entityInstance->getFullName(),
        ], 'users'));

        $photo = $entityInstance->getPhotoUpload();

        if ($photo !== null) {
            // A new upload replaces the old one, so it also settles a removal request.
            $this->handleReferencePhoto($entityInstance, $photo);
        } elseif ($entityInstance->isRemovePhoto()) {
            $this->usersService->removeReferencePhoto($entityInstance);
            $this->addFlash('success', $this->translator->trans('users.flash.photo_removed', [
                '{name}' => $entityInstance->getFullName(),
            ], 'users'));
        }
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

        $this->addFlash('warning', $this->translator->trans($problem, [], 'users'));
    }
}
