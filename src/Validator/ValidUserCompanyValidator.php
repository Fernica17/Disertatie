<?php

namespace App\Validator;

use App\Entity\Users;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidUserCompanyValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof Users || !$constraint instanceof ValidUserCompany) {
            return;
        }

        $root = $this->context->getRoot();
        if ($root instanceof FormInterface && $root->getName() === 'filters') {
            return;
        }

        $roles = $value->getRoles();
        $company = $value->getCompany();

        if (
            in_array(Users::ROLE_CLIENT, $roles, true)
            && $company === null
        ) {
            $this->context->buildViolation($constraint->companyRequiredMessage)
                ->atPath('company')
                ->addViolation();
        }

        if (
            (in_array(Users::ROLE_ADMIN, $roles, true) || in_array(Users::ROLE_MANAGER, $roles, true))
            && $company !== null
        ) {
            $this->context->buildViolation($constraint->companyNotAllowedMessage)
                ->atPath('company')
                ->addViolation();
        }
    }
}
