<?php

namespace App\MessageHandler\Audit;

use App\Audit\Entity\EntityChangeLogs;
use App\Message\Audit\LogEntityChangeMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LogEntityChangeMessageHandler
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.audit_entity_manager')]
        private readonly EntityManagerInterface $auditEntityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LogEntityChangeMessage $message): void
    {
        try {
            $log = new EntityChangeLogs();
            $log->setEntityClass($message->entityClass);
            $log->setEntityId($message->entityId);
            $log->setAction($message->action);
            $log->setChanges($message->changes);
            $log->setUserId($message->userId);
            $log->setUsername($message->username);
            $log->setEntityLabel($message->entityLabel);
            $log->setIpAddress($message->ipAddress);

            $this->auditEntityManager->persist($log);
            $this->auditEntityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Failed to persist entity change log', [
                'error' => $e->getMessage(),
                'entity' => $message->entityClass,
                'entity_id' => $message->entityId,
                'action' => $message->action,
            ]);

            throw $e;
        }
    }
}
