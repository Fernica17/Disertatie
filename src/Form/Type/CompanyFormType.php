<?php

namespace App\Form\Type;

use App\Entity\Cities;
use App\Entity\Companies;
use App\Entity\Countries;
use App\Entity\Elements;
use App\Entity\Users;
use App\Enum\CompanyStatus;
use App\Enum\ElementListSlug;
use App\Enum\PersonType;
use App\Repository\ElementsRepository;
use App\Repository\UsersRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CompanyFormType extends AbstractType
{
    public function __construct(
        private readonly string $uploadMaxFileSizeMb,
        private readonly int $uploadMaxFiles,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Section 1: Identification
            ->add('name', TextType::class, [
                'required' => true,
            ])
            ->add('personType', EnumType::class, [
                'class' => PersonType::class,
                'required' => true,
                'choice_label' => fn (PersonType $type) => match ($type) {
                    PersonType::LEGAL => 'companies.choice.legal_person',
                    PersonType::PHYSICAL => 'companies.choice.physical_person',
                },
            ])
            ->add('status', EnumType::class, [
                'class' => CompanyStatus::class,
                'required' => true,
                'choice_label' => fn (CompanyStatus $status) => 'companies.status.' . $status->value,
            ])
            ->add('fiscalCode', TextType::class, [
                'required' => false,
            ])
            ->add('tradeRegisterNumber', TextType::class, [
                'required' => false,
            ])

            // Section 2: Classification
            ->add('accountManager', EntityType::class, [
                'class' => Users::class,
                'required' => false,
                'placeholder' => '',
                'query_builder' => fn (UsersRepository $repo) => $repo->createManagersQueryBuilder()
                    ->orderBy('u.lastName', 'ASC'),
                'choice_label' => fn (Users $u) => $u->getFullName(),
            ])
            ->add('clientType', EntityType::class, [
                'class' => Elements::class,
                'required' => false,
                'placeholder' => '',
                'query_builder' => fn (ElementsRepository $repo) => $repo->choicesByListSlugQueryBuilder(ElementListSlug::CLIENT_TYPE->value),
                'choice_label' => 'name',
            ])
            ->add('industry', EntityType::class, [
                'class' => Elements::class,
                'required' => false,
                'placeholder' => '',
                'query_builder' => fn (ElementsRepository $repo) => $repo->choicesByListSlugQueryBuilder(ElementListSlug::INDUSTRY->value),
                'choice_label' => 'name',
            ])
            ->add('companySize', EntityType::class, [
                'class' => Elements::class,
                'required' => false,
                'placeholder' => '',
                'query_builder' => fn (ElementsRepository $repo) => $repo->choicesByListSlugQueryBuilder(ElementListSlug::COMPANY_SIZE->value),
                'choice_label' => 'name',
            ])

            // Section 3: Address
            ->add('country', EntityType::class, [
                'class' => Countries::class,
                'required' => false,
                'placeholder' => '',
                'choice_label' => 'name',
            ])
            ->add('city', AjaxEntityType::class, [
                'class' => Cities::class,
                'required' => false,
            ])
            ->add('address', TextType::class, [
                'required' => false,
            ])
            ->add('postalCode', TextType::class, [
                'required' => false,
            ])

            // Section 4: Contact
            ->add('phone', TelType::class, [
                'required' => false,
            ])
            ->add('email', TextType::class, [
                'required' => false,
            ])
            ->add('website', TextType::class, [
                'required' => false,
            ])
            ->add('fax', TelType::class, [
                'required' => false,
            ])
            ->add('contactPersonName', TextType::class, [
                'required' => false,
            ])
            ->add('contactPersonPosition', TextType::class, [
                'required' => false,
            ])
            ->add('contactPersonPhone', TelType::class, [
                'required' => false,
            ])
            ->add('contactPersonEmail', TextType::class, [
                'required' => false,
            ])

            // Section 5: Financial
            ->add('bankName', TextType::class, [
                'required' => false,
            ])
            ->add('bankAccount', TextType::class, [
                'required' => false,
            ])
            ->add('creditLimit', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.01'],
            ])
            ->add('paymentTermDays', IntegerType::class, [
                'required' => false,
                'attr' => ['min' => 0],
            ])

            // Section 7: Notes
            ->add('notes', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 4],
            ])

            // Section 8: File uploads (unmapped)
            ->add('logo', FileType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => '.jpg,.jpeg,.png,.webp'],
                'constraints' => [
                    new Assert\File(maxSize: $this->uploadMaxFileSizeMb),
                ],
            ])
            ->add('documents', FileType::class, [
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => ['accept' => '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx'],
                'constraints' => [
                    new Assert\Count(max: $this->uploadMaxFiles, maxMessage: 'validation.files.max_count'),
                    new Assert\All([
                        new Assert\File(maxSize: $this->uploadMaxFileSizeMb),
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Companies::class,
            'translation_domain' => 'companies',
        ]);
    }
}
