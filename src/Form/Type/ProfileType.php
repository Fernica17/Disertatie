<?php

namespace App\Form\Type;

use App\Entity\Users;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => $this->translator->trans('profile.form.email', [], 'admin'),
                'disabled' => true,
            ])
            ->add('firstName', TextType::class, [
                'label' => $this->translator->trans('profile.form.first_name', [], 'admin'),
            ])
            ->add('lastName', TextType::class, [
                'label' => $this->translator->trans('profile.form.last_name', [], 'admin'),
            ])
            ->add('phone', TextType::class, [
                'label' => $this->translator->trans('profile.form.phone', [], 'admin'),
                'required' => false,
            ])
            ->add('avatar', FileType::class, [
                'label' => $this->translator->trans('profile.form.avatar_label', [], 'admin'),
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        maxSizeMessage: $this->translator->trans('profile.form.avatar_too_large', [], 'admin'),
                        mimeTypesMessage: $this->translator->trans('profile.form.avatar_invalid_type', [], 'admin'),
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Users::class,
            'validation_groups' => ['profile'],
        ]);
    }
}
