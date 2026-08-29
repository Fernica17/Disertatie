<?php

namespace App\Service;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExcelExportService
{
    private const SENSITIVE_FIELDS = [
        'password',
        'roles',
        'salt',
        'token',
        'logoUpload',
        'avatar',
    ];

    /** Map enum class → translation domain */
    private const ENUM_DOMAIN_MAP = [
        'App\Enum\PersonType' => 'companies',
        'App\Enum\CompanyStatus' => 'companies',
        'App\Enum\ContractType' => 'contracts',
        'App\Enum\ContractStatus' => 'contracts',
        'App\Enum\OfferType' => 'offers',
        'App\Enum\OfferStatus' => 'offers',
        'App\Enum\DeliveryMethod' => 'offers',
        'App\Enum\EquipmentStatus' => 'equipments',
        'App\Enum\EquipmentRecordType' => 'equipments',
        'App\Enum\UnitOfMeasure' => 'products',
        'App\Enum\ProjectStatus' => 'projects',
        'App\Enum\ProjectType' => 'projects',
        'App\Enum\ProjectPhaseStatus' => 'projects',
        'App\Enum\StockItemType' => 'stock',
        'App\Enum\StockLotStatus' => 'stock',
        'App\Enum\StockMovementType' => 'stock',
        'App\Enum\TestStatus' => 'tests',
        'App\Enum\UserRole' => 'users',
        'App\Enum\CorrespondenceChannel' => 'correspondence',
        'App\Enum\CorrespondenceDirection' => 'correspondence',
        'App\Enum\CorrespondenceDocumentType' => 'correspondence',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function createResponseFromQueryBuilder(
        QueryBuilder $queryBuilder,
        FieldCollection $fields,
        string $filename,
    ): Response {
        $entities = $queryBuilder->getQuery()->getResult();

        // Determine which properties to export from field definitions
        $exportProperties = $this->resolveExportProperties($fields);

        $rows = [];
        foreach ($entities as $entity) {
            $rows[] = $this->extractRow($entity, $exportProperties);
        }

        $headers = $this->resolveHeaders($exportProperties, $fields);

        return $this->streamExcel($rows, $headers, $filename);
    }

    /**
     * Get the list of property paths to export, based on field definitions.
     * Only exports fields visible on INDEX page (no hideOnIndex fields).
     *
     * @return string[]
     */
    private function resolveExportProperties(FieldCollection $fields): array
    {
        $properties = [];

        foreach ($fields as $field) {
            $property = $field->getProperty();

            if (in_array($property, self::SENSITIVE_FIELDS, true)) {
                continue;
            }

            // Only include fields visible on INDEX page
            if (!$field->isDisplayedOn(Crud::PAGE_INDEX)) {
                continue;
            }

            $properties[] = $property;
        }

        return $properties;
    }

    /**
     * Extract a single row of data from an entity using property accessor.
     */
    private function extractRow(object $entity, array $properties): array
    {
        $row = [];

        foreach ($properties as $property) {
            try {
                $value = $this->propertyAccessor->getValue($entity, $property);
            } catch (\Exception) {
                $row[$property] = '';
                continue;
            }

            $row[$property] = $this->normalizeValue($value);
        }

        return $row;
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            return $this->translateEnum($value);
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_bool($value)) {
            return $value
                ? $this->translator->trans('common.choice.yes', [], 'admin')
                : $this->translator->trans('common.choice.no', [], 'admin');
        }

        // Related entity — use __toString
        if (is_object($value)) {
            if ($value instanceof \Doctrine\Common\Collections\Collection) {
                $items = [];
                foreach ($value as $item) {
                    $items[] = method_exists($item, '__toString') ? (string) $item : '';
                }

                return implode(', ', array_filter($items));
            }

            return method_exists($value, '__toString') ? (string) $value : '';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v, $value));
        }

        return (string) $value;
    }

    private function translateEnum(\BackedEnum $enum): string
    {
        if (!method_exists($enum, 'label')) {
            return ucfirst(str_replace('_', ' ', (string) $enum->value));
        }

        $key = $enum->label();
        $domain = self::ENUM_DOMAIN_MAP[$enum::class] ?? null;

        if ($domain !== null) {
            $translated = $this->translator->trans($key, [], $domain);
            if ($translated !== $key) {
                return $translated;
            }
        }

        return ucfirst(str_replace('_', ' ', (string) $enum->value));
    }

    /**
     * @param string[] $properties
     */
    private function resolveHeaders(array $properties, FieldCollection $fields): array
    {
        $headers = [];

        foreach ($properties as $property) {
            $label = ucfirst($property);

            foreach ($fields as $field) {
                if ($property === $field->getProperty() && $field->getLabel()) {
                    $rawLabel = $field->getLabel();
                    if ($rawLabel instanceof \Symfony\Component\Translation\TranslatableMessage) {
                        $label = $rawLabel->trans($this->translator);
                    } elseif (is_string($rawLabel)) {
                        $label = $this->translator->trans($rawLabel);
                    }
                    break;
                }
            }

            $headers[] = $label;
        }

        return $headers;
    }

    private function streamExcel(array $data, array $headers, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($data, $headers) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            if (!empty($headers)) {
                foreach ($headers as $index => $label) {
                    $col = Coordinate::stringFromColumnIndex($index + 1);
                    $sheet->setCellValue($col . '1', $label);
                }
            }

            if (!empty($data)) {
                $rowIndex = 2;
                foreach ($data as $row) {
                    $colIndex = 1;
                    foreach ($row as $value) {
                        $col = Coordinate::stringFromColumnIndex($colIndex);
                        $sheet->setCellValue($col . $rowIndex, $value);
                        ++$colIndex;
                    }
                    ++$rowIndex;
                }
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $cleanFilename = $this->sanitizeFilename($filename);

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $cleanFilename,
            'export.xlsx'
        );

        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        return $response;
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = str_replace(
            ['ă', 'Ă', 'â', 'Â', 'î', 'Î', 'ș', 'Ș', 'ş', 'Ş', 'ț', 'Ț', 'ţ', 'Ţ'],
            ['a', 'A', 'a', 'A', 'i', 'I', 's', 'S', 's', 'S', 't', 'T', 't', 'T'],
            $filename
        );

        return preg_replace('/[^\p{L}0-9 _.-]/u', '_', $filename);
    }
}
