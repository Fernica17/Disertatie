<?php

namespace App\Security\Voter;

use App\Entity\Companies;
use App\Entity\Users;
use App\Enum\CompanyStatus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CompaniesVoter extends Voter
{
    public const string COMPANIES_LIST = 'COMPANIES_LIST';
    public const string COMPANIES_VIEW = 'COMPANIES_VIEW';
    public const string COMPANIES_CREATE = 'COMPANIES_CREATE';
    public const string COMPANIES_EDIT = 'COMPANIES_EDIT';
    public const string COMPANIES_DELETE = 'COMPANIES_DELETE';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [
            self::COMPANIES_LIST,
            self::COMPANIES_VIEW,
            self::COMPANIES_CREATE,
            self::COMPANIES_EDIT,
            self::COMPANIES_DELETE,
        ], true)) {
            return false;
        }

        if ($subject !== null && !$subject instanceof Companies && $subject !== Companies::class) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Users) {
            return false;
        }

        return match ($attribute) {
            self::COMPANIES_LIST => $this->list(),
            self::COMPANIES_VIEW => $this->view($user, $subject),
            self::COMPANIES_CREATE => $this->create(),
            self::COMPANIES_EDIT => $this->edit($subject),
            self::COMPANIES_DELETE => $this->delete($subject),
            default => false,
        };
    }

    private function list(): bool
    {
        return $this->security->isGranted('ROLE_MANAGER');
    }

    private function view(Users $user, mixed $subject): bool
    {
        if ($this->security->isGranted('ROLE_MANAGER')) {
            return true;
        }

        if ($this->security->isGranted('ROLE_CLIENT')) {
            if ($subject instanceof Companies) {
                return $user->getCompany() !== null && $user->getCompany() === $subject;
            }

            return $user->getCompany() !== null;
        }

        return false;
    }

    private function create(): bool
    {
        return $this->security->isGranted('ROLE_MANAGER');
    }

    private function edit(mixed $subject): bool
    {
        if ($subject instanceof Companies && in_array($subject->getStatus(), [CompanyStatus::BLOCKED, CompanyStatus::FINALIZED], true)) {
            return false;
        }

        return $this->security->isGranted('ROLE_MANAGER');
    }

    private function delete(mixed $subject): bool
    {
        if ($subject instanceof Companies && in_array($subject->getStatus(), [CompanyStatus::BLOCKED, CompanyStatus::FINALIZED], true)) {
            return false;
        }

        return $this->security->isGranted('ROLE_ADMIN');
    }
}
