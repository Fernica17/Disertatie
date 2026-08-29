<?php

namespace App\Security;

use App\Entity\Users;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof Users) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                $this->translator->trans('security.user_checker.account_disabled', [], 'security')
            );
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(
                $this->translator->trans('security.user_checker.email_not_verified', [], 'security'),
                ['requires_verification' => true]
            );
        }

        $roles = $user->getRoles();

        $isExternalUser = in_array('ROLE_CLIENT', $roles, true);
        if ($isExternalUser && $user->getCompany() === null) {
            throw new CustomUserMessageAccountStatusException(
                $this->translator->trans('security.user_checker.company_missing', [], 'security')
            );
        }

        if ($user->getCompany() !== null && !$user->getCompany()->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                $this->translator->trans('security.user_checker.company_inactive', [], 'security')
            );
        }
    }
}
