<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NotSelfDeactivation extends Constraint
{
    public string $message = 'validation.user.cannot_deactivate_self';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
