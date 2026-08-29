<?php

namespace App\Controller\Admin;

use App\Entity\Elements;
use App\Security\Voter\AdministrationVoter;
use App\Traits\ExportableCrudTrait;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(AdministrationVoter::ADMINISTRATION_VIEW)]
class ElementsCrudController extends AbstractCrudController
{
    use ExportableCrudTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Elements::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $this->addExportAction($actions);

        return $actions
            ->disable(Action::DELETE, Action::NEW, Action::EDIT, Action::DETAIL, Action::INDEX);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', $this->translator->trans('elements.field.name', [], 'lists'))
            ->setColumns('row');

        yield AssociationField::new('subList', $this->translator->trans('elements.field.sub_list', [], 'lists'))
            ->setColumns('row');

        yield BooleanField::new('isActive', $this->translator->trans('elements.field.is_active', [], 'lists'))
            ->setColumns('row');
    }
}
