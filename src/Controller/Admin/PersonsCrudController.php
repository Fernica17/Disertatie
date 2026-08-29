<?php

namespace App\Controller\Admin;

use App\Entity\Files;
use App\Entity\Persons;
use App\Form\Type\PersonFormType;
use App\Security\Voter\UsersVoter;
use App\Service\FilesUploadService;
use App\Service\PersonsService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The face-searchable person registry.
 *
 * Guarded by the users permission because these are identity records: whoever
 * may see the staff list may see the registry.
 */
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class PersonsCrudController extends AbstractCrudController
{
    /** @var array<int, Files[]> */
    private array $photosMap = [];

    public function __construct(
        private readonly PersonsService $personsService,
        private readonly FilesUploadService $filesUploadService,
        private readonly TranslatorInterface $translator,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Persons::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->trans('persons.entity.singular'))
            ->setEntityLabelInPlural($this->trans('persons.entity.plural'))
            ->setPageTitle(Crud::PAGE_INDEX, $this->trans('persons.page.index'))
            ->setDefaultSort(['lastName' => 'ASC', 'firstName' => 'ASC'])
            ->setSearchFields(['firstName', 'lastName', 'nationalId', 'idDocument', 'phone', 'email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, UsersVoter::USERS_CREATE)
            ->setPermission(Action::EDIT, UsersVoter::USERS_EDIT)
            ->setPermission(Action::DELETE, UsersVoter::USERS_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('photo', $this->trans('persons.field.photo'))
            ->formatValue(fn ($value, $entity) => $this->photoThumbnail($entity))
            ->onlyOnIndex();
        yield TextField::new('fullName', $this->trans('persons.field.full_name'))->onlyOnIndex();
        yield TextField::new('firstName', $this->trans('persons.field.first_name'))->onlyOnDetail();
        yield TextField::new('lastName', $this->trans('persons.field.last_name'))->onlyOnDetail();
        yield TextField::new('nationalId', $this->trans('persons.field.national_id'));
        yield TextField::new('idDocument', $this->trans('persons.field.id_document'))->onlyOnDetail();
        yield DateField::new('birthDate', $this->trans('persons.field.birth_date'));
        yield TextField::new('phone', $this->trans('persons.field.phone'));
        yield TextField::new('email', $this->trans('persons.field.email'));
        yield TextField::new('fullAddress', $this->trans('persons.field.address'))->onlyOnDetail();
        yield TextField::new('notes', $this->trans('persons.field.notes'))->onlyOnDetail();
        yield BooleanField::new('isActive', $this->trans('persons.field.is_active'))->renderAsSwitch(false);
    }

    public function new(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_CREATE);

        $person = new Persons();
        $form = $this->createForm(PersonFormType::class, $person);
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $this->personsService->create($person);

            $this->addFlash('success', $this->translator->trans('persons.flash.created', [
                '{name}' => $person->getFullName(),
            ], 'persons'));

            $this->handlePhoto($person, $person->getPhotoUpload());

            return $this->redirect($this->indexUrl());
        }

        return $this->render('admin/persons/person_form.html.twig', [
            'form' => $form,
            'person' => null,
            'photo' => null,
        ]);
    }

    public function edit(AdminContext $context): Response
    {
        $person = $context->getEntity()->getInstance();

        if (!$person instanceof Persons) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(UsersVoter::USERS_EDIT);

        $form = $this->createForm(PersonFormType::class, $person);
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $this->personsService->update($person);

            $this->addFlash('success', $this->translator->trans('persons.flash.updated', [
                '{name}' => $person->getFullName(),
            ], 'persons'));

            $photo = $person->getPhotoUpload();

            if ($photo !== null) {
                $this->handlePhoto($person, $photo);
            } elseif ($person->isRemovePhoto()) {
                $this->personsService->removePhoto($person);
                $this->addFlash('success', $this->trans('persons.flash.photo_removed'));
            }

            return $this->redirect($this->indexUrl());
        }

        return $this->render('admin/persons/person_form.html.twig', [
            'form' => $form,
            'person' => $person,
            'photo' => $this->personsService->photoOf($person),
        ]);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->denyAccessUnlessGranted(UsersVoter::USERS_DELETE);

        if ($entityInstance instanceof Persons) {
            $this->personsService->delete($entityInstance);
        }
    }

    /**
     * Loads every photo on the page in one query instead of one per row.
     */
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if ($responseParameters->get('pageName') !== Crud::PAGE_INDEX) {
            return $responseParameters;
        }

        $entities = $responseParameters->get('entities');

        if (!$entities instanceof EntityCollection) {
            return $responseParameters;
        }

        $persons = [];

        foreach ($entities as $entityDto) {
            $instance = $entityDto->getInstance();

            if ($instance instanceof Persons) {
                $persons[] = $instance;
            }
        }

        if ($persons !== []) {
            $this->photosMap = $this->filesUploadService->getFilesForEntities($persons, Files::TYPE_PERSON_PHOTO);
        }

        return $responseParameters;
    }

    private function photoThumbnail(Persons $person): string
    {
        $photo = ($this->photosMap[$person->getId()] ?? [])[0]
            ?? $this->personsService->photoOf($person);

        if ($photo === null) {
            return sprintf(
                '<div class="mg-avatar-initials" style="width:40px;height:40px;border-radius:50%%;background:#94a3b8;'
                . 'color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">%s</div>',
                htmlspecialchars(mb_strtoupper(mb_substr($person->getFirstName() ?? '?', 0, 1)), \ENT_QUOTES),
            );
        }

        return sprintf(
            '<img src="%s" style="width:40px;height:40px;object-fit:cover;border-radius:50%%;">',
            htmlspecialchars($this->generateUrl('admin_file_view', ['id' => $photo->getId()]), \ENT_QUOTES),
        );
    }

    private function handlePhoto(Persons $person, ?UploadedFile $photo): void
    {
        if ($photo === null) {
            return;
        }

        $problem = $this->personsService->storePhoto($person, $photo);

        if ($problem === null) {
            $this->addFlash('success', $this->translator->trans('persons.flash.face_enrolled', [
                '{name}' => $person->getFullName(),
            ], 'persons'));

            return;
        }

        $this->addFlash('warning', $this->translator->trans('persons.face.failed', [
            '{reason}' => $this->translator->trans($problem, [], 'users'),
        ], 'persons'));
    }

    private function indexUrl(): string
    {
        return $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'persons');
    }
}
