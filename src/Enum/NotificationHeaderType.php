<?php

namespace App\Enum;

use App\Controller\Admin\UsersCrudController;
use App\Entity\Notifications;

enum NotificationHeaderType: string
{
    case USER_ONBOARDING = Notifications::TYPE_USER_ONBOARDING;
    case USER_RESET_PASSWORD = Notifications::TYPE_USER_RESET_PASSWORD;
    case RESEND_VERIFICATION = Notifications::TYPE_RESEND_VERIFICATION;

    public static function getAllowedTypes(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getConfig(): array
    {
        return match ($this) {
            self::USER_ONBOARDING => [
                'target_array' => null,
                'search_keys' => ['user_id'],
                'controller' => UsersCrudController::class,
                'icon' => 'fa-solid fa-user-plus',
                'color' => 'primary',
            ],
            self::USER_RESET_PASSWORD => [
                'target_array' => null,
                'search_keys' => ['user_id'],
                'controller' => UsersCrudController::class,
                'icon' => 'fa-solid fa-key',
                'color' => 'warning',
            ],
            self::RESEND_VERIFICATION => [
                'target_array' => null,
                'search_keys' => ['user_id'],
                'controller' => UsersCrudController::class,
                'icon' => 'fa-solid fa-envelope-circle-check',
                'color' => 'info',
            ],
        };
    }
}
