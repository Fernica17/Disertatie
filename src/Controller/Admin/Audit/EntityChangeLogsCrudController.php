<?php

namespace App\Controller\Admin\Audit;

use App\Audit\Entity\EntityChangeLogs;
use App\Security\Voter\AuditVoter;
use App\Traits\ExportableCrudTrait;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminCrud(routePath: '/entity-change-logs', routeName: 'entity_change_logs_')]
class EntityChangeLogsCrudController extends AbstractCrudController
{
    use ExportableCrudTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EntityChangeLogs::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('audit.entity_change_logs.label_singular', [], 'audit'))
            ->setEntityLabelInPlural($this->translator->trans('audit.entity_change_logs.label_plural', [], 'audit'))
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->setSearchFields(['entityClass', 'username', 'entityLabel']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $this->addExportAction($actions);

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE, Action::DETAIL)
            ->setPermission(Action::INDEX, AuditVoter::AUDIT_VIEW_CHANGE_LOGS);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('entityClass', $this->translator->trans('audit.entity_change_logs.field.entityClass', [], 'audit'));
        yield IntegerField::new('entityId', $this->translator->trans('audit.entity_change_logs.field.entityId', [], 'audit'));
        yield TextField::new('entityLabel', $this->translator->trans('audit.entity_change_logs.field.entityLabel', [], 'audit'));
        yield ChoiceField::new('action', $this->translator->trans('audit.entity_change_logs.field.action', [], 'audit'))
            ->setChoices([
                $this->translator->trans('audit.enum.action.create', [], 'audit') => EntityChangeLogs::ACTION_CREATE,
                $this->translator->trans('audit.enum.action.update', [], 'audit') => EntityChangeLogs::ACTION_UPDATE,
                $this->translator->trans('audit.enum.action.delete', [], 'audit') => EntityChangeLogs::ACTION_DELETE,
            ])
            ->renderAsBadges([
                EntityChangeLogs::ACTION_CREATE => 'success',
                EntityChangeLogs::ACTION_UPDATE => 'info',
                EntityChangeLogs::ACTION_DELETE => 'danger',
            ]);
        yield TextField::new('username', $this->translator->trans('audit.entity_change_logs.field.username', [], 'audit'));
        yield TextField::new('ipAddress', $this->translator->trans('audit.entity_change_logs.field.ipAddress', [], 'audit'));
        yield TextField::new('formattedChanges', $this->translator->trans('audit.entity_change_logs.field.changes', [], 'audit'))
            ->renderAsHtml()
            ->setVirtual(true);
        yield DateTimeField::new('createdAt', $this->translator->trans('audit.entity_change_logs.field.createdAt', [], 'audit'));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('entityClass'))
            ->add(ChoiceFilter::new('action')->setChoices([
                $this->translator->trans('audit.enum.action.create', [], 'audit') => EntityChangeLogs::ACTION_CREATE,
                $this->translator->trans('audit.enum.action.update', [], 'audit') => EntityChangeLogs::ACTION_UPDATE,
                $this->translator->trans('audit.enum.action.delete', [], 'audit') => EntityChangeLogs::ACTION_DELETE,
            ]))
            ->add(TextFilter::new('username'))
            ->add(TextFilter::new('entityLabel'))
            ->add(DateTimeFilter::new('createdAt'));
    }
}
