<?php

namespace App\Form\Type;

use App\Entity\Settings;
use App\Service\SettingsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SettingsFormType extends AbstractType
{
    public const ALLOWED_CURRENCIES = ['RON'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $defaults = SettingsService::getDefaults();
        $currentValues = $options['current_values'];

        $groupOrder = [
            Settings::GROUP_GENERAL,
            Settings::GROUP_FINANCIAL,
            Settings::GROUP_SERIES,
            Settings::GROUP_NOTIFICATIONS,
        ];

        $grouped = [];
        foreach ($defaults as $key => $def) {
            $grouped[$def['group']][$key] = $def;
        }

        foreach ($groupOrder as $group) {
            if (!isset($grouped[$group])) {
                continue;
            }

            foreach ($grouped[$group] as $key => $def) {
                $value = $currentValues[$key] ?? $def['value'];
                $this->addField($builder, $key, $def, $value);
            }

            unset($grouped[$group]);
        }

        // Any remaining groups not in the order
        foreach ($grouped as $settings) {
            foreach ($settings as $key => $def) {
                $value = $currentValues[$key] ?? $def['value'];
                $this->addField($builder, $key, $def, $value);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'current_values' => [],
            'translation_domain' => 'admin',
        ]);

        $resolver->setAllowedTypes('current_values', 'array');
    }

    private function addField(FormBuilderInterface $builder, string $key, array $def, ?string $value): void
    {
        match (true) {
            $key === 'default_currency' => $builder->add($key, ChoiceType::class, [
                'label' => 'admin.settings.field.' . $key,
                'choices' => array_combine(self::ALLOWED_CURRENCIES, self::ALLOWED_CURRENCIES),
                'data' => $value,
                'required' => true,
                'help' => $def['description'],
                'attr' => ['class' => 'form-select'],
            ]),
            $key === 'default_vat_percent' => $builder->add($key, NumberType::class, [
                'label' => 'admin.settings.field.' . $key,
                'data' => $value !== null && $value !== '' ? (float) $value : null,
                'required' => false,
                'help' => $def['description'],
                'html5' => true,
                'scale' => 2,
                'attr' => ['class' => 'form-control', 'step' => '0.01', 'min' => '0', 'max' => '100'],
                'constraints' => [
                    new Assert\Range(min: 0, max: 100),
                ],
            ]),
            $def['type'] === Settings::TYPE_INTEGER => $builder->add($key, IntegerType::class, [
                'label' => 'admin.settings.field.' . $key,
                'data' => $value !== null && $value !== '' ? (int) $value : null,
                'required' => false,
                'help' => $def['description'],
                'attr' => ['class' => 'form-control', 'min' => '1'],
                'constraints' => [
                    new Assert\PositiveOrZero(),
                    new Assert\GreaterThanOrEqual(1),
                ],
            ]),
            default => $builder->add($key, TextType::class, [
                'label' => 'admin.settings.field.' . $key,
                'data' => $value ?? '',
                'required' => false,
                'help' => $def['description'],
                'attr' => ['class' => 'form-control'],
            ]),
        };
    }
}
