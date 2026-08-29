<?php

namespace App\Security\Voter;

use App\Entity\Users;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AuditVoter extends Voter
{
    public const string AUDIT_VIEW_AUTH_LOGS = 'AUDIT_VIEW_AUTH_LOGS';
    public const string AUDIT_VIEW_ACCESS_LOGS = 'AUDIT_VIEW_ACCESS_LOGS';
    public const string AUDIT_VIEW_CHANGE_LOGS = 'AUDIT_VIEW_CHANGE_LOGS';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::AUDIT_VIEW_AUTH_LOGS,
            self::AUDIT_VIEW_ACCESS_LOGS,
            self::AUDIT_VIEW_CHANGE_LOGS,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Users) {
            return false;
        }

        return match ($attribute) {
            self::AUDIT_VIEW_AUTH_LOGS => $this->canViewAuthLogs(),
            self::AUDIT_VIEW_ACCESS_LOGS => $this->canViewAccessLogs(),
            self::AUDIT_VIEW_CHANGE_LOGS => $this->canViewChangeLogs(),
            default => false,
        };
    }

    private function canViewAuthLogs(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function canViewAccessLogs(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function canViewChangeLogs(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
