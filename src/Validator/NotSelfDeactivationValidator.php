<?php

namespace App\Validator;

use App\Entity\Users;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NotSelfDeactivationValidator extends ConstraintValidator
{
    public function __construct(private Security $security)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof Users || !$constraint instanceof NotSelfDeactivation) {
            return;
        }

        $currentUser = $this->security->getUser();

        if ($currentUser instanceof Users && $value->getId() === $currentUser->getId() && $value->isActive() === false) {
            $this->context->buildViolation($constraint->message)
                ->atPath('isActive')
                ->addViolation();
        }
    }
}
