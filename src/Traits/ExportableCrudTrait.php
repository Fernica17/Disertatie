<?php

namespace App\Traits;

use App\Service\ExcelExportService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Add Excel export capability to any EasyAdmin CRUD controller.
 *
 * Usage:
 *   use ExportableCrudTrait;
 *
 *   // in configureActions():
 *   $this->addExportAction($actions);
 */
trait ExportableCrudTrait
{
    private ExcelExportService $excelExportService;
    private TranslatorInterface $exportTranslator;

    #[Required]
    public function setExportDependencies(
        ExcelExportService $excelExportService,
        TranslatorInterface $translator,
    ): void {
        $this->excelExportService = $excelExportService;
        $this->exportTranslator = $translator;
    }

    protected function addExportAction(Actions $actions): Actions
    {
        $exportAction = Action::new('export', 'admin.action.export', 'fa fa-file-excel')
            ->linkToUrl(function () {
                return $this->container->get(AdminUrlGenerator::class)
                    ->setController(static::class)
                    ->setAction('export')
                    ->generateUrl();
            })
            ->setCssClass('btn btn-success')
            ->createAsGlobalAction();

        $actions->add(Crud::PAGE_INDEX, $exportAction);

        return $actions;
    }

    #[AdminAction('export', 'export')]
    public function export(AdminContext $context): Response
    {
        $fields = new FieldCollection($this->configureFields(Crud::PAGE_INDEX));

        $filters = $this->container->get(FilterFactory::class)
            ->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());

        $queryBuilder = $this->createIndexQueryBuilder(
            $context->getSearch(),
            $context->getEntity(),
            $fields,
            $filters
        );

        $rawTitle = $context->getCrud()->getCustomPageTitle(Crud::PAGE_INDEX)
            ?? $context->getCrud()->getEntityLabelInPlural()
            ?? 'export';

        if ($rawTitle instanceof TranslatableMessage) {
            $rawTitle = $rawTitle->trans($this->exportTranslator);
        }

        $filename = $rawTitle . '-' . date('d-m-Y') . '.xlsx';

        return $this->excelExportService->createResponseFromQueryBuilder(
            $queryBuilder,
            $fields,
            $filename
        );
    }
}
