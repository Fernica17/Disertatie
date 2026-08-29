<?php

namespace App\Twig;

use App\Entity\Companies;
use App\Entity\Users;
use App\Security\Voter\CompaniesVoter;
use App\Security\Voter\UsersVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class EasyAdminPermissionExtension extends AbstractExtension
{
    private const array ENTITY_VIEW_PERMISSIONS = [
        Companies::class => CompaniesVoter::COMPANIES_VIEW,
        Users::class => UsersVoter::USERS_VIEW,
    ];

    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('can_view_entity', $this->canViewEntity(...)),
        ];
    }

    public function canViewEntity(mixed $entity): bool
    {
        if ($entity === null) {
            return false;
        }

        $className = \is_object($entity) ? $entity::class : null;

        if ($className === null) {
            return true;
        }

        $permission = self::ENTITY_VIEW_PERMISSIONS[$className] ?? null;

        if ($permission === null) {
            return true;
        }

        return $this->security->isGranted($permission, $entity);
    }
}
