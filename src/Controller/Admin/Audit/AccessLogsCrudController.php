<?php

namespace App\Controller\Admin\Audit;

use App\Audit\Entity\AccessLogs;
use App\Security\Voter\AuditVoter;
use App\Traits\ExportableCrudTrait;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminCrud(routePath: '/access-logs', routeName: 'access_logs_')]
class AccessLogsCrudController extends AbstractCrudController
{
    use ExportableCrudTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AccessLogs::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('audit.access_logs.label_singular', [], 'audit'))
            ->setEntityLabelInPlural($this->translator->trans('audit.access_logs.label_plural', [], 'audit'))
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->setSearchFields(['username', 'routeName', 'url', 'controller', 'ipAddress']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $this->addExportAction($actions);

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->setPermission(Action::INDEX, AuditVoter::AUDIT_VIEW_ACCESS_LOGS)
            ->setPermission(Action::DETAIL, AuditVoter::AUDIT_VIEW_ACCESS_LOGS);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('userId', $this->translator->trans('audit.access_logs.field.userId', [], 'audit'))
            ->hideOnIndex();
        yield TextField::new('username', $this->translator->trans('audit.access_logs.field.username', [], 'audit'));
        yield TextField::new('routeName', $this->translator->trans('audit.access_logs.field.routeName', [], 'audit'));
        yield ChoiceField::new('method', $this->translator->trans('audit.access_logs.field.method', [], 'audit'))
            ->setChoices([
                'GET' => 'GET',
                'POST' => 'POST',
                'PUT' => 'PUT',
                'PATCH' => 'PATCH',
                'DELETE' => 'DELETE',
            ])
            ->renderAsBadges([
                'GET' => 'info',
                'POST' => 'success',
                'PUT' => 'warning',
                'PATCH' => 'warning',
                'DELETE' => 'danger',
            ]);
        yield IntegerField::new('responseCode', $this->translator->trans('audit.access_logs.field.responseCode', [], 'audit'));
        yield NumberField::new('executionTime', $this->translator->trans('audit.access_logs.field.executionTime', [], 'audit'))
            ->setNumDecimals(2);
        yield TextareaField::new('url', $this->translator->trans('audit.access_logs.field.url', [], 'audit'))
            ->hideOnIndex();
        yield TextField::new('controller', $this->translator->trans('audit.access_logs.field.controller', [], 'audit'))
            ->hideOnIndex();
        yield TextField::new('action', $this->translator->trans('audit.access_logs.field.action', [], 'audit'))
            ->hideOnIndex();
        yield ArrayField::new('requestParams', $this->translator->trans('audit.access_logs.field.requestParams', [], 'audit'))
            ->hideOnIndex();
        yield TextField::new('ipAddress', $this->translator->trans('audit.access_logs.field.ipAddress', [], 'audit'));
        yield TextareaField::new('userAgent', $this->translator->trans('audit.access_logs.field.userAgent', [], 'audit'))
            ->hideOnIndex();
        yield IntegerField::new('memoryUsage', $this->translator->trans('audit.access_logs.field.memoryUsage', [], 'audit'))
            ->hideOnIndex();
        yield DateTimeField::new('createdAt', $this->translator->trans('audit.access_logs.field.createdAt', [], 'audit'));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('username'))
            ->add(TextFilter::new('routeName'))
            ->add(TextFilter::new('ipAddress'))
            ->add(ChoiceFilter::new('method')->setChoices([
                'GET' => 'GET',
                'POST' => 'POST',
                'PUT' => 'PUT',
                'DELETE' => 'DELETE',
            ]))
            ->add(NumericFilter::new('responseCode'))
            ->add(DateTimeFilter::new('createdAt'));
    }
}
