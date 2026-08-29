<?php

namespace App\Controller\Admin\Audit;

use App\Audit\Entity\AuthenticationLogs;
use App\Security\Voter\AuditVoter;
use App\Traits\ExportableCrudTrait;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminCrud(routePath: '/authentication-logs', routeName: 'authentication_logs_')]
class AuthenticationLogsCrudController extends AbstractCrudController
{
    use ExportableCrudTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AuthenticationLogs::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('audit.authentication_logs.label_singular', [], 'audit'))
            ->setEntityLabelInPlural($this->translator->trans('audit.authentication_logs.label_plural', [], 'audit'))
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->setSearchFields(['username', 'ipAddress', 'eventType', 'failureReason']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $this->addExportAction($actions);

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->setPermission(Action::INDEX, AuditVoter::AUDIT_VIEW_AUTH_LOGS)
            ->setPermission(Action::DETAIL, AuditVoter::AUDIT_VIEW_AUTH_LOGS);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('username', $this->translator->trans('audit.authentication_logs.field.username', [], 'audit'));
        yield ChoiceField::new('eventType', $this->translator->trans('audit.authentication_logs.field.eventType', [], 'audit'))
            ->setChoices([
                $this->translator->trans('audit.enum.event_type.login_success', [], 'audit') => AuthenticationLogs::TYPE_LOGIN_SUCCESS,
                $this->translator->trans('audit.enum.event_type.login_failed', [], 'audit') => AuthenticationLogs::TYPE_LOGIN_FAILED,
                $this->translator->trans('audit.enum.event_type.logout', [], 'audit') => AuthenticationLogs::TYPE_LOGOUT,
                $this->translator->trans('audit.enum.event_type.password_change', [], 'audit') => AuthenticationLogs::TYPE_PASSWORD_CHANGE,
            ])
            ->renderAsBadges([
                AuthenticationLogs::TYPE_LOGIN_SUCCESS => 'success',
                AuthenticationLogs::TYPE_LOGIN_FAILED => 'danger',
                AuthenticationLogs::TYPE_LOGOUT => 'secondary',
                AuthenticationLogs::TYPE_PASSWORD_CHANGE => 'info',
            ]);
        yield BooleanField::new('success', $this->translator->trans('audit.authentication_logs.field.success', [], 'audit'))
            ->renderAsSwitch(false);
        yield TextField::new('ipAddress', $this->translator->trans('audit.authentication_logs.field.ipAddress', [], 'audit'));
        yield TextField::new('failureReason', $this->translator->trans('audit.authentication_logs.field.failureReason', [], 'audit'))
            ->hideOnIndex();
        yield TextareaField::new('userAgent', $this->translator->trans('audit.authentication_logs.field.userAgent', [], 'audit'))
            ->hideOnIndex();
        yield TextField::new('sessionId', $this->translator->trans('audit.authentication_logs.field.sessionId', [], 'audit'))
            ->hideOnIndex();
        yield DateTimeField::new('createdAt', $this->translator->trans('audit.authentication_logs.field.createdAt', [], 'audit'));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('username'))
            ->add(ChoiceFilter::new('eventType')->setChoices([
                $this->translator->trans('audit.enum.event_type.login_success', [], 'audit') => AuthenticationLogs::TYPE_LOGIN_SUCCESS,
                $this->translator->trans('audit.enum.event_type.login_failed', [], 'audit') => AuthenticationLogs::TYPE_LOGIN_FAILED,
                $this->translator->trans('audit.enum.event_type.logout', [], 'audit') => AuthenticationLogs::TYPE_LOGOUT,
                $this->translator->trans('audit.enum.event_type.password_change', [], 'audit') => AuthenticationLogs::TYPE_PASSWORD_CHANGE,
            ]))
            ->add(BooleanFilter::new('success'))
            ->add(DateTimeFilter::new('createdAt'));
    }
}
