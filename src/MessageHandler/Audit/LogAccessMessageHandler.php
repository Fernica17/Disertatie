<?php

namespace App\MessageHandler\Audit;

use App\Audit\Entity\AccessLogs;
use App\Message\Audit\LogAccessMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LogAccessMessageHandler
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.audit_entity_manager')]
        private readonly EntityManagerInterface $auditEntityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LogAccessMessage $message): void
    {
        try {
            $log = new AccessLogs();
            $log->setUserId($message->userId);
            $log->setUsername($message->username);
            $log->setUserRoles($message->userRoles);
            $log->setRouteName($message->routeName);
            $log->setUrl($message->url);
            $log->setMethod($message->method);
            $log->setController($message->controller);
            $log->setAction($message->action);
            $log->setRequestParams($message->requestParams);
            $log->setResponseCode($message->responseCode);
            $log->setIpAddress($message->ipAddress);
            $log->setUserAgent($message->userAgent);
            $log->setSessionId($message->sessionId);
            $log->setReferer($message->referer);
            $log->setExecutionTime($message->executionTime);
            $log->setMemoryUsage($message->memoryUsage);

            $this->auditEntityManager->persist($log);
            $this->auditEntityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Failed to persist access log', [
                'error' => $e->getMessage(),
                'route' => $message->routeName,
                'user_id' => $message->userId,
            ]);

            throw $e;
        }
    }
}
