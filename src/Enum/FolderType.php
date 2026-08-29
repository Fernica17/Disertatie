<?php

namespace App\Enum;

enum FolderType: string
{
    case SYSTEM = 'system';
    case CUSTOM = 'custom';
    case CLIENT = 'client';

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => 'enum.folder_type.system',
            self::CUSTOM => 'enum.folder_type.custom',
            self::CLIENT => 'enum.folder_type.client',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SYSTEM => 'fa fa-lock',
            self::CUSTOM => 'fa fa-folder',
            self::CLIENT => 'fa fa-user',
        };
    }
}
