<?php

namespace App\Security\Voter;

use App\Entity\Users;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UsersVoter extends Voter
{
    public const string USERS_VIEW = 'USERS_VIEW';
    public const string USERS_CREATE = 'USERS_CREATE';
    public const string USERS_EDIT = 'USERS_EDIT';
    public const string USERS_DELETE = 'USERS_DELETE';
    public const string USERS_VIEW_AVATAR = 'USERS_VIEW_AVATAR';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::USERS_VIEW,
            self::USERS_CREATE,
            self::USERS_EDIT,
            self::USERS_DELETE,
            self::USERS_VIEW_AVATAR,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Users) {
            return false;
        }

        return match ($attribute) {
            self::USERS_CREATE => $this->create(),
            self::USERS_VIEW => $this->view(),
            self::USERS_EDIT => $this->edit(),
            self::USERS_DELETE => $this->delete($user, $subject),
            self::USERS_VIEW_AVATAR => $this->viewAvatar(),
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

    private function delete(Users $user, mixed $subject): bool
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return false;
        }

        if ($subject instanceof Users) {
            return $subject !== $user;
        }

        return true;
    }

    private function viewAvatar(): bool
    {
        return $this->security->isGranted('ROLE_MANAGER')
            || $this->security->isGranted('ROLE_CLIENT');
    }
}
