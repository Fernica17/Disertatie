<?php

namespace App\Security\Voter;

use App\Entity\Folders;
use App\Entity\Users;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FoldersVoter extends Voter
{
    public const string FOLDERS_VIEW = 'FOLDERS_VIEW';
    public const string FOLDERS_VIEW_CLIENT = 'FOLDERS_VIEW_CLIENT';
    public const string FOLDERS_CREATE = 'FOLDERS_CREATE';
    public const string FOLDERS_EDIT = 'FOLDERS_EDIT';
    public const string FOLDERS_DELETE = 'FOLDERS_DELETE';
    public const string FOLDERS_UPLOAD = 'FOLDERS_UPLOAD';
    public const string FOLDERS_MOVE_FILE = 'FOLDERS_MOVE_FILE';
    public const string FOLDERS_DOWNLOAD = 'FOLDERS_DOWNLOAD';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [
            self::FOLDERS_VIEW,
            self::FOLDERS_VIEW_CLIENT,
            self::FOLDERS_CREATE,
            self::FOLDERS_EDIT,
            self::FOLDERS_DELETE,
            self::FOLDERS_UPLOAD,
            self::FOLDERS_MOVE_FILE,
            self::FOLDERS_DOWNLOAD,
        ], true)) {
            return false;
        }

        if ($subject !== null && !$subject instanceof Folders) {
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
            self::FOLDERS_VIEW => $this->view(),
            self::FOLDERS_VIEW_CLIENT => $this->viewClient($user, $subject),
            self::FOLDERS_CREATE, self::FOLDERS_EDIT,
            self::FOLDERS_UPLOAD, self::FOLDERS_MOVE_FILE => $this->manage(),
            self::FOLDERS_DELETE => $this->delete(),
            self::FOLDERS_DOWNLOAD => $this->download($user, $subject),
            default => false,
        };
    }

    private function view(): bool
    {
        return $this->security->isGranted('ROLE_MANAGER');
    }

    private function viewClient(Users $user, mixed $subject): bool
    {
        if ($this->security->isGranted('ROLE_MANAGER')) {
            return true;
        }

        if ($this->security->isGranted('ROLE_CLIENT') && $subject instanceof Folders) {
            return $this->isOwnerOfClientFolder($user, $subject);
        }

        return false;
    }

    private function manage(): bool
    {
        return $this->security->isGranted('ROLE_MANAGER');
    }

    private function delete(): bool
    {
        return $this->security->isGranted('ROLE_MANAGER');
    }

    private function download(Users $user, mixed $subject): bool
    {
        if ($this->security->isGranted('ROLE_MANAGER')) {
            return true;
        }

        if ($this->security->isGranted('ROLE_CLIENT') && $subject instanceof Folders) {
            return $this->isOwnerOfClientFolder($user, $subject);
        }

        return false;
    }

    private function isOwnerOfClientFolder(Users $user, Folders $folder): bool
    {
        $rootClientFolder = $this->getClientRootFolder($folder);

        return $rootClientFolder !== null
            && $rootClientFolder->getCompany() !== null
            && $user->getCompany() !== null
            && $rootClientFolder->getCompany()->getId() === $user->getCompany()->getId();
    }

    private function getClientRootFolder(Folders $folder): ?Folders
    {
        $current = $folder;

        while ($current->getParent() !== null) {
            $current = $current->getParent();
        }

        return $current->isClient() ? $current : null;
    }
}
