<?php

namespace App\Message\Audit;

readonly class LogAuthenticationMessage
{
    public function __construct(
        public ?int $userId,
        public ?string $username,
        public string $eventType,
        public ?string $ipAddress,
        public ?string $userAgent,
        public bool $success,
        public ?string $failureReason = null,
        public ?string $sessionId = null,
        public ?array $additionalData = null,
    ) {
    }
}
