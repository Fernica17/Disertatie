<?php

namespace App\Controller\Admin;

use App\Entity\Countries;
use App\Security\Voter\AdministrationVoter;
use App\Traits\ExportableCrudTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(AdministrationVoter::ADMINISTRATION_VIEW)]
class CountriesCrudController extends AbstractCrudController
{
    use ExportableCrudTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Countries::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', $this->translator->trans('countries.field.name', [], 'admin')))
            ->add(BooleanFilter::new('isActive', $this->translator->trans('countries.field.is_active', [], 'admin')));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(25)
            ->setSearchFields(['name'])
            ->setEntityLabelInSingular($this->translator->trans('countries.entity.singular', [], 'admin'))
            ->setEntityLabelInPlural($this->translator->trans('countries.entity.plural', [], 'admin'))
            ->showEntityActionsInlined()
            ->setPageTitle('index', $this->translator->trans('countries.page.index', [], 'admin'))
            ->setPageTitle('new', $this->translator->trans('countries.page.new', [], 'admin'))
            ->setPageTitle('edit', fn (Countries $country) => $this->translator->trans('countries.page.edit', ['{name}' => $country->getName()], 'admin'))
            ->setPageTitle('detail', fn (Countries $country) => $country->getName());
    }

    public function configureActions(Actions $actions): Actions
    {
        $this->addExportAction($actions);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->disable(Action::DELETE)
            ->setPermission(Action::NEW, AdministrationVoter::ADMINISTRATION_CREATE)
            ->setPermission(Action::EDIT, AdministrationVoter::ADMINISTRATION_EDIT)
            ->setPermission(Action::DETAIL, AdministrationVoter::ADMINISTRATION_VIEW)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action
                ->setIcon('far fa-square-plus'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action
                ->setIcon('far fa-pen-to-square')
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'tooltip',
                    'title' => $this->translator->trans('admin.action.edit', [], 'admin'),
                ]))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $action) => $action
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'tooltip',
                    'title' => $this->translator->trans('admin.action.detail', [], 'admin'),
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
        yield TextField::new('name', $this->translator->trans('countries.field.name', [], 'admin'));
        yield BooleanField::new('isActive', $this->translator->trans('countries.field.is_active', [], 'admin'))
            ->renderAsSwitch(false);
    }

    private function configureDetailFields(): iterable
    {
        yield TextField::new('name', $this->translator->trans('countries.field.name', [], 'admin'));
        yield ChoiceField::new('isActive', $this->translator->trans('countries.field.is_active', [], 'admin'))
            ->setChoices([
                $this->translator->trans('common.choice.yes', [], 'admin') => 1,
                $this->translator->trans('common.choice.no', [], 'admin') => 0,
            ])
            ->renderAsBadges([
                1 => 'success',
                0 => 'secondary',
            ]);
    }

    private function configureFormFields(string $pageName): iterable
    {
        yield TextField::new('name', $this->translator->trans('countries.field.name', [], 'admin'));
        yield BooleanField::new('isActive', $this->translator->trans('countries.field.is_active', [], 'admin'))
            ->renderAsSwitch(true);
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->denyAccessUnlessGranted(AdministrationVoter::ADMINISTRATION_CREATE);

        parent::persistEntity($entityManager, $entityInstance);

        $this->addFlash('success', $this->translator->trans('countries.flash.created', [], 'admin'));
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->denyAccessUnlessGranted(AdministrationVoter::ADMINISTRATION_EDIT);

        parent::updateEntity($entityManager, $entityInstance);

        $this->addFlash('success', $this->translator->trans('countries.flash.updated', [], 'admin'));
    }
}
