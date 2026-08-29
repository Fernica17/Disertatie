<?php

namespace App\Controller\Admin;

use App\Entity\Lists;
use App\Security\Voter\AdministrationVoter;
use App\Traits\ExportableCrudTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(AdministrationVoter::ADMINISTRATION_VIEW)]
class ListsCrudController extends AbstractCrudController
{
    use ExportableCrudTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Lists::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25)
            ->setSearchFields(['name'])
            ->setEntityLabelInSingular($this->translator->trans('lists.entity.singular', [], 'lists'))
            ->setEntityLabelInPlural($this->translator->trans('lists.entity.plural', [], 'lists'))
            ->setPageTitle(Crud::PAGE_INDEX, $this->translator->trans('lists.page.index', [], 'lists'))
            ->setPageTitle(Crud::PAGE_DETAIL, $this->translator->trans('lists.page.detail', [], 'lists'))
            ->setPageTitle(Crud::PAGE_NEW, $this->translator->trans('lists.page.new', [], 'lists'))
            ->setPageTitle(Crud::PAGE_EDIT, fn (Lists $list) => $this->translator->trans('lists.page.edit', ['{name}' => $list->getName()], 'lists'))
            ->showEntityActionsInlined();
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
        yield TextField::new('name', $this->translator->trans('lists.field.name', [], 'lists'));
        yield TextField::new('parentFormat', $this->translator->trans('lists.field.parent_format', [], 'lists'));
    }

    private function configureDetailFields(): iterable
    {
        yield TextField::new('name', $this->translator->trans('lists.field.name', [], 'lists'));
        yield CollectionField::new('elements', $this->translator->trans('lists.field.elements', [], 'lists'))
            ->renderExpanded()
            ->setTemplatePath('admin/lists/elements_table.html.twig');
    }

    private function configureFormFields(string $pageName): iterable
    {
        yield TextField::new('name', $this->translator->trans('lists.field.name', [], 'lists'))
            ->setColumns(12);
        yield CollectionField::new('elements', $this->translator->trans('lists.field.elements', [], 'lists'))
            ->useEntryCrudForm()
            ->setEntryIsComplex()
            ->allowDelete(false)
            ->setFormTypeOptions([
                'by_reference' => false,
            ])
            ->setColumns(12);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('name');
    }

    public function configureActions(Actions $actions): Actions
    {
        $this->addExportAction($actions);

        return $actions
            ->disable(Action::DELETE)
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action
                ->setIcon('far fa-square-plus')
                ->setLabel($this->translator->trans('lists.action.new', [], 'lists')))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action
                ->setIcon('far fa-pen-to-square')
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'tooltip',
                    'title' => $this->translator->trans('lists.action.edit', [], 'lists'),
                ]))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $action) => $action
                ->setIcon('far fa-eye')
                ->setHtmlAttributes([
                    'data-bs-toggle' => 'tooltip',
                    'title' => $this->translator->trans('lists.action.detail', [], 'lists'),
                ]));
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->denyAccessUnlessGranted(AdministrationVoter::ADMINISTRATION_CREATE);

        parent::persistEntity($entityManager, $entityInstance);

        $this->addFlash('success', $this->translator->trans('lists.flash.created', [], 'lists'));
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->denyAccessUnlessGranted(AdministrationVoter::ADMINISTRATION_EDIT);

        parent::updateEntity($entityManager, $entityInstance);

        $this->addFlash('success', $this->translator->trans('lists.flash.updated', [], 'lists'));
    }
}
