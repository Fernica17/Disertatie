<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\EntityFilterType;

class AssociationPathFilter implements FilterInterface
{
    use FilterTrait;

    private string $joinAlias = '';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(EntityFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function setJoinPath(string $joinPath, string $joinAlias): self
    {
        $this->joinAlias = $joinAlias;

        return $this;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $parameterName = $filterDataDto->getParameterName();
        $value = $filterDataDto->getValue();
        $comparison = $filterDataDto->getComparison();

        if (null === $value || (is_countable($value) && 0 === \count($value))) {
            $queryBuilder->andWhere(sprintf('%s %s', $this->joinAlias, $comparison));
        } else {
            $queryBuilder->andWhere(sprintf('%s %s (:%s)', $this->joinAlias, $comparison, $parameterName))
                ->setParameter($parameterName, $value);
        }
    }
}
