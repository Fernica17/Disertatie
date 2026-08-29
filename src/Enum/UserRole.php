<?php

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'ROLE_ADMIN';
    case MANAGER = 'ROLE_MANAGER';
    case CLIENT = 'ROLE_CLIENT';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'enum.user_role.admin',
            self::MANAGER => 'enum.user_role.manager',
            self::CLIENT => 'enum.user_role.client',
        };
    }
}
