<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidUserCompany extends Constraint
{
    public string $companyRequiredMessage = 'validation.user.company_required';
    public string $companyNotAllowedMessage = 'validation.user.company_not_allowed';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
