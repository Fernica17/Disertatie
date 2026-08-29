<?php

namespace App\Security\Voter;

use App\Entity\Users;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AdministrationVoter extends Voter
{
    public const string ADMINISTRATION_VIEW = 'ADMINISTRATION_VIEW';
    public const string ADMINISTRATION_CREATE = 'ADMINISTRATION_CREATE';
    public const string ADMINISTRATION_EDIT = 'ADMINISTRATION_EDIT';
    public const string ADMINISTRATION_DELETE = 'ADMINISTRATION_DELETE';
    public const string ADMINISTRATION_CHANGELOG_VIEW = 'ADMINISTRATION_CHANGELOG_VIEW';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::ADMINISTRATION_VIEW,
            self::ADMINISTRATION_CREATE,
            self::ADMINISTRATION_EDIT,
            self::ADMINISTRATION_DELETE,
            self::ADMINISTRATION_CHANGELOG_VIEW,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Users) {
            return false;
        }

        return match ($attribute) {
            self::ADMINISTRATION_VIEW => $this->view(),
            self::ADMINISTRATION_CREATE => $this->create(),
            self::ADMINISTRATION_EDIT => $this->edit(),
            self::ADMINISTRATION_DELETE => $this->delete(),
            self::ADMINISTRATION_CHANGELOG_VIEW => $this->viewChangelog(),
            default => false,
        };
    }

    private function view(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function create(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function edit(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function delete(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function viewChangelog(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
