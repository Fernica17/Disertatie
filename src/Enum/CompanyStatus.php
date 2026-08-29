<?php

namespace App\Enum;

enum CompanyStatus: string
{
    case PROSPECT = 'prospect';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BLOCKED = 'blocked';
    case FINALIZED = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'enum.company_status.active',
            self::PROSPECT => 'enum.company_status.prospect',
            self::SUSPENDED => 'enum.company_status.suspended',
            self::BLOCKED => 'enum.company_status.blocked',
            self::FINALIZED => 'enum.company_status.finalized',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PROSPECT => 'mg-badge mg-badge-pending',
            self::ACTIVE => 'mg-badge mg-badge-completed',
            self::SUSPENDED => 'mg-badge mg-badge-warning',
            self::BLOCKED => 'mg-badge mg-badge-blocked',
            self::FINALIZED => 'mg-badge mg-badge-inactive',
        };
    }
}
