<?php

namespace App\Enum;

enum PersonType: string
{
    case LEGAL = 'legal';
    case PHYSICAL = 'physical';

    public function label(): string
    {
        return match ($this) {
            self::LEGAL => 'enum.person_type.legal',
            self::PHYSICAL => 'enum.person_type.physical',
        };
    }
}
