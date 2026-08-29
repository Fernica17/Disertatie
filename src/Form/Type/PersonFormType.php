<?php

namespace App\Form\Type;

use App\Entity\Cities;
use App\Entity\Countries;
use App\Entity\Persons;
use App\Repository\CountriesRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

class PersonFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => $this->label('first_name'),
            ])
            ->add('lastName', TextType::class, [
                'label' => $this->label('last_name'),
            ])
            ->add('nationalId', TextType::class, [
                'label' => $this->label('national_id'),
                'required' => false,
            ])
            ->add('idDocument', TextType::class, [
                'label' => $this->label('id_document'),
                'required' => false,
            ])
            ->add('birthDate', DateType::class, [
                'label' => $this->label('birth_date'),
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('phone', TextType::class, [
                'label' => $this->label('phone'),
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => $this->label('email'),
                'required' => false,
            ])
            ->add('address', TextType::class, [
                'label' => $this->label('address'),
                'required' => false,
            ])
            ->add('country', EntityType::class, [
                'label' => $this->label('country'),
                'class' => Countries::class,
                'required' => false,
                'placeholder' => '',
                'choice_label' => 'name',
                'query_builder' => fn (CountriesRepository $repo) => $repo->createQueryBuilder('c')
                    ->where('c.isActive = :active')
                    ->setParameter('active', true)
                    ->orderBy('c.name', 'ASC'),
            ])
            // Around 16 000 rows: rendered as a hidden id, with the visible
            // selects filled on demand by the location-picker controller.
            ->add('city', AjaxEntityType::class, [
                'label' => $this->label('city'),
                'class' => Cities::class,
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => $this->label('notes'),
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => $this->label('is_active'),
                'required' => false,
            ])
            ->add('photoUpload', FileType::class, [
                'label' => $this->label('photo'),
                'required' => false,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [
                    new Assert\File(
                        maxSize: '8M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'validation.person.photo.mime',
                    ),
                ],
            ])
            ->add('removePhoto', CheckboxType::class, [
                'label' => $this->label('remove_photo'),
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Persons::class]);
    }

    private function label(string $key): string
    {
        return $this->translator->trans('persons.field.' . $key, [], 'persons');
    }
}
