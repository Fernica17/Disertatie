<?php

namespace App\Form\Type;

use App\Entity\Companies;
use App\Entity\Users;
use App\Enum\UserRole;
use App\Repository\CompaniesRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['is_new'];

        $passwordConstraints = [
            new Assert\Length(min: 6, minMessage: 'validation.user.password.min_length'),
            new Assert\Regex(pattern: '/[A-Z]/', message: 'validation.user.password.uppercase'),
            new Assert\Regex(pattern: '/[a-z]/', message: 'validation.user.password.lowercase'),
            new Assert\Regex(pattern: '/[0-9]/', message: 'validation.user.password.digit'),
            new Assert\Regex(
                pattern: '/[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\\\\/~]/',
                message: 'validation.user.password.special',
            ),
        ];

        if ($isNew) {
            array_unshift($passwordConstraints, new Assert\NotBlank(message: 'validation.user.password.required'));
        }

        $builder
            ->add('email', EmailType::class, [
                'label' => $this->translator->trans('users.field.email', [], 'users'),
            ])
            ->add('firstName', TextType::class, [
                'label' => $this->translator->trans('users.field.first_name', [], 'users'),
            ])
            ->add('lastName', TextType::class, [
                'label' => $this->translator->trans('users.field.last_name', [], 'users'),
            ])
            ->add('phone', TextType::class, [
                'label' => $this->translator->trans('users.field.phone', [], 'users'),
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => $isNew,
                'invalid_message' => 'validation.user.passwords_mismatch',
                'constraints' => $passwordConstraints,
                'first_options' => [
                    'label' => $this->translator->trans('users.field.password', [], 'users'),
                    'attr' => ['autocomplete' => 'new-password', 'class' => 'of-input password-field'],
                ],
                'second_options' => [
                    'label' => $this->translator->trans('users.field.confirm_password', [], 'users'),
                    'attr' => ['autocomplete' => 'new-password', 'class' => 'of-input'],
                ],
            ])
            ->add('company', EntityType::class, [
                'label' => $this->translator->trans('users.field.company', [], 'users'),
                'class' => Companies::class,
                'required' => false,
                'placeholder' => '',
                'choice_label' => 'name',
                'query_builder' => fn (CompaniesRepository $repo) => $repo->createQueryBuilder('c')
                    ->orderBy('c.name', 'ASC'),
            ])
            ->add('primaryRole', ChoiceType::class, [
                'label' => $this->translator->trans('users.field.role', [], 'users'),
                'choices' => $this->roleChoices(),
                'required' => false,
                'placeholder' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => $this->translator->trans('users.field.is_active', [], 'users'),
                'required' => false,
            ])
            ->add('photoUpload', FileType::class, [
                'label' => $this->translator->trans('users.field.photo', [], 'users'),
                'required' => false,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [
                    new Assert\File(
                        maxSize: '8M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'validation.user.photo.mime',
                    ),
                ],
            ])
            ->add('removePhoto', CheckboxType::class, [
                'label' => $this->translator->trans('users.field.remove_photo', [], 'users'),
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Users::class,
            'is_new' => false,
        ]);

        $resolver->setAllowedTypes('is_new', 'bool');
    }

    /**
     * @return array<string, string>
     */
    private function roleChoices(): array
    {
        $choices = [];

        foreach (UserRole::cases() as $role) {
            $choices[$this->translator->trans($role->label(), [], 'users')] = $role->value;
        }

        return $choices;
    }
}
