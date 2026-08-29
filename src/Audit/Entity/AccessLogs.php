<?php

namespace App\Audit\Entity;

use App\Audit\Repository\AccessLogsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccessLogsRepository::class)]
#[ORM\Table(name: 'access_logs')]
#[ORM\Index(columns: ['user_id'], name: 'idx_access_user_id')]
#[ORM\Index(columns: ['route_name'], name: 'idx_access_route_name')]
#[ORM\Index(columns: ['created_at'], name: 'idx_access_created_at')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_access_user_created')]
#[ORM\Index(columns: ['response_code'], name: 'idx_access_response_code')]
class AccessLogs
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $userId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(nullable: true)]
    private ?array $userRoles = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $routeName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $controller = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $action = null;

    #[ORM\Column(nullable: true)]
    private ?array $requestParams = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $responseCode = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $referer = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $executionTime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $memoryUsage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getUserRoles(): ?array
    {
        return $this->userRoles;
    }

    public function setUserRoles(?array $userRoles): static
    {
        $this->userRoles = $userRoles;

        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function setRouteName(?string $routeName): static
    {
        $this->routeName = $routeName;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getController(): ?string
    {
        return $this->controller;
    }

    public function setController(?string $controller): static
    {
        $this->controller = $controller;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getRequestParams(): ?array
    {
        return $this->requestParams;
    }

    public function setRequestParams(?array $requestParams): static
    {
        $this->requestParams = $requestParams;

        return $this;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function setResponseCode(?int $responseCode): static
    {
        $this->responseCode = $responseCode;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }

    public function setReferer(?string $referer): static
    {
        $this->referer = $referer;

        return $this;
    }

    public function getExecutionTime(): ?float
    {
        return $this->executionTime;
    }

    public function setExecutionTime(?float $executionTime): static
    {
        $this->executionTime = $executionTime;

        return $this;
    }

    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    public function setMemoryUsage(?int $memoryUsage): static
    {
        $this->memoryUsage = $memoryUsage;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
