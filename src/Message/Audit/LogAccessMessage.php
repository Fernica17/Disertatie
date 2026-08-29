<?php

namespace App\Message\Audit;

readonly class LogAccessMessage
{
    public function __construct(
        public int $userId,
        public ?string $username,
        public ?array $userRoles,
        public ?string $routeName,
        public ?string $url,
        public ?string $method,
        public ?string $controller,
        public ?string $action,
        public ?array $requestParams,
        public ?int $responseCode,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $sessionId,
        public ?string $referer,
        public ?float $executionTime,
        public ?int $memoryUsage,
    ) {
    }
}
