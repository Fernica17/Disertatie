<?php

namespace App\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AjaxEntityType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $class = $options['class'];
        $em = $this->entityManager;

        $builder->addModelTransformer(new CallbackTransformer(
            fn (mixed $entity): string => $entity === null ? '' : (string) $entity->getId(),
            fn (mixed $id): mixed => ($id === null || $id === '') ? null : $em->getRepository($class)->find((int) $id),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('class');
        $resolver->setAllowedTypes('class', 'string');

        // EasyAdmin passes these for association fields — accept and ignore them
        $resolver->setDefined(['query_builder', 'choice_label', 'choices']);
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'ajax_entity';
    }
}
