<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class JsonContainsFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceType::class);
    }

    public function setChoices(array $choices): self
    {
        $this->dto->setFormTypeOption('choices', $choices);

        return $this;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        $paramName = 'json_filter_' . str_replace('.', '_', $filterDataDto->getProperty());

        $queryBuilder
            ->andWhere(sprintf('JSONB_CONTAINS(%s.%s, :%s) = true', $filterDataDto->getEntityAlias(), $filterDataDto->getProperty(), $paramName))
            ->setParameter($paramName, json_encode($value));
    }
}
