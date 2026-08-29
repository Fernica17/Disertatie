<?php

namespace App\MessageHandler\Audit;

use App\Audit\Entity\AuthenticationLogs;
use App\Message\Audit\LogAuthenticationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LogAuthenticationMessageHandler
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.audit_entity_manager')]
        private readonly EntityManagerInterface $auditEntityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LogAuthenticationMessage $message): void
    {
        try {
            $log = new AuthenticationLogs();
            $log->setUserId($message->userId);
            $log->setUsername($message->username);
            $log->setEventType($message->eventType);
            $log->setIpAddress($message->ipAddress);
            $log->setUserAgent($message->userAgent);
            $log->setSuccess($message->success);
            $log->setFailureReason($message->failureReason);
            $log->setSessionId($message->sessionId);
            $log->setAdditionalData($message->additionalData);

            $this->auditEntityManager->persist($log);
            $this->auditEntityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Failed to persist authentication log', [
                'error' => $e->getMessage(),
                'event_type' => $message->eventType,
                'user_id' => $message->userId,
            ]);

            throw $e;
        }
    }
}
