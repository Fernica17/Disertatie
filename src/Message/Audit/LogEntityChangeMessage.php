<?php

namespace App\Message\Audit;

readonly class LogEntityChangeMessage
{
    public function __construct(
        public string $entityClass,
        public int $entityId,
        public string $action,
        public ?array $changes,
        public ?int $userId,
        public ?string $username,
        public ?string $entityLabel,
        public ?string $ipAddress,
    ) {
    }
}
