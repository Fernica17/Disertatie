<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileChangePasswordType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => $this->translator->trans('security.change_password.current_password_label', [], 'security'),
                'mapped' => false,
                'attr' => [
                    'placeholder' => '••••••••',
                ],
                'constraints' => [
                    new NotBlank(
                        message: $this->translator->trans('security.change_password.current_password_required', [], 'security')
                    ),
                    new UserPassword(
                        message: $this->translator->trans('security.change_password.current_password_invalid', [], 'security')
                    ),
                ],
            ])

            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => $this->translator->trans('security.change_password.passwords_mismatch', [], 'security'),
                'mapped' => false,
                'first_options' => [
                    'label' => $this->translator->trans('security.change_password.new_password_label', [], 'security'),
                    'attr' => [
                        'placeholder' => '••••••••',
                        'minlength' => 6,
                    ]],
                'second_options' => [
                    'label' => $this->translator->trans('security.change_password.confirm_password_label', [], 'security'),
                    'attr' => [
                        'placeholder' => '••••••••',
                        'minlength' => 6,
                    ]],
                'constraints' => [
                    new NotBlank(
                        message: $this->translator->trans('security.change_password.password_required', [], 'security')
                    ),
                    new Length(
                        min: 6,
                        max: 255,
                        minMessage: $this->translator->trans('security.change_password.password_min_length', [], 'security')
                    ),
                    new Regex(
                        pattern: '/[A-Z]/',
                        message: $this->translator->trans('validation.user.password.uppercase', [], 'validators'),
                    ),
                    new Regex(
                        pattern: '/[a-z]/',
                        message: $this->translator->trans('validation.user.password.lowercase', [], 'validators'),
                    ),
                    new Regex(
                        pattern: '/[0-9]/',
                        message: $this->translator->trans('validation.user.password.digit', [], 'validators'),
                    ),
                    new Regex(
                        pattern: '/[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\\\\/~]/',
                        message: $this->translator->trans('validation.user.password.special', [], 'validators'),
                    ),
                ],
            ])
        ;
    }
}
