<?php

namespace App\Security\Voter;

use App\Entity\Users;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FilesVoter extends Voter
{
    public const string FILES_VIEW = 'FILES_VIEW';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::FILES_VIEW;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Users) {
            return false;
        }

        return match ($attribute) {
            self::FILES_VIEW => $this->view(),
            default => false,
        };
    }

    private function view(): bool
    {
        return $this->security->isGranted('ROLE_MANAGER')
            || $this->security->isGranted('ROLE_CLIENT');
    }
}
