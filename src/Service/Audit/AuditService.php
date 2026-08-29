<?php

namespace App\Service\Audit;

use App\Audit\Repository\AccessLogsRepository;
use App\Audit\Repository\AuthenticationLogsRepository;
use App\Audit\Repository\EntityChangeLogsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AuditService
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.audit_entity_manager')]
        private readonly EntityManagerInterface $auditEntityManager,
        private readonly AuthenticationLogsRepository $authLogRepository,
        private readonly AccessLogsRepository $accessLogRepository,
        private readonly EntityChangeLogsRepository $changeLogRepository,
        private readonly LoggerInterface $logger,
        #[Autowire('%audit.retention_days%')]
        private readonly int $retentionDays,
    ) {
    }

    public function cleanupOldAuthenticationLogs(?int $retentionDays = null): int
    {
        $days = $retentionDays ?? $this->retentionDays;
        $date = new \DateTimeImmutable(sprintf('-%d days', $days));

        $deleted = $this->authLogRepository->deleteOlderThan($date);

        $this->logger->info('Cleaned up authentication logs', [
            'deleted' => $deleted,
            'older_than' => $date->format('Y-m-d H:i:s'),
        ]);

        return $deleted;
    }

    public function cleanupOldAccessLogs(?int $retentionDays = null): int
    {
        $days = $retentionDays ?? $this->retentionDays;
        $date = new \DateTimeImmutable(sprintf('-%d days', $days));

        $deleted = $this->accessLogRepository->deleteOlderThan($date);

        $this->logger->info('Cleaned up access logs', [
            'deleted' => $deleted,
            'older_than' => $date->format('Y-m-d H:i:s'),
        ]);

        return $deleted;
    }

    public function cleanupOldEntityChangeLogs(?int $retentionDays = null): int
    {
        $days = $retentionDays ?? $this->retentionDays;
        $date = new \DateTimeImmutable(sprintf('-%d days', $days));

        $deleted = $this->changeLogRepository->deleteOlderThan($date);

        $this->logger->info('Cleaned up entity change logs', [
            'deleted' => $deleted,
            'older_than' => $date->format('Y-m-d H:i:s'),
        ]);

        return $deleted;
    }

    /**
     * @return \App\Audit\Entity\AuthenticationLogs[]
     */
    public function getUserAuthenticationHistory(int $userId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        return $this->authLogRepository->findByUser($userId, $from, $to);
    }

    /**
     * @return \App\Audit\Entity\AccessLogs[]
     */
    public function getUserAccessHistory(int $userId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        return $this->accessLogRepository->findByUser($userId, $from, $to);
    }

    public function getFailedLoginsByIp(string $ip, \DateTimeInterface $since): int
    {
        return $this->authLogRepository->countFailedLoginsByIp($ip, $since);
    }

    public function deleteUserAuditData(int $userId): void
    {
        $this->auditEntityManager->beginTransaction();

        try {
            $authDeleted = $this->authLogRepository->deleteByUserId($userId);
            $accessDeleted = $this->accessLogRepository->deleteByUserId($userId);
            $changeDeleted = $this->changeLogRepository->deleteByUserId($userId);

            $this->auditEntityManager->commit();

            $this->logger->info('Deleted user audit data', [
                'user_id' => $userId,
                'auth_logs_deleted' => $authDeleted,
                'access_logs_deleted' => $accessDeleted,
                'change_logs_deleted' => $changeDeleted,
            ]);
        } catch (\Exception $e) {
            $this->auditEntityManager->rollback();

            throw $e;
        }
    }

    public function exportUserAuditData(int $userId): array
    {
        $authLogs = $this->authLogRepository->findByUser($userId);
        $accessLogs = $this->accessLogRepository->findByUser($userId);

        return [
            'user_id' => $userId,
            'export_date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'authentication_logs' => array_map(
                fn ($log) => [
                    'event_type' => $log->getEventType(),
                    'success' => $log->isSuccess(),
                    'ip_address' => $log->getIpAddress(),
                    'created_at' => $log->getCreatedAt()?->format('Y-m-d H:i:s'),
                ],
                $authLogs
            ),
            'access_logs' => array_map(
                fn ($log) => [
                    'route' => $log->getRouteName(),
                    'method' => $log->getMethod(),
                    'status_code' => $log->getResponseCode(),
                    'created_at' => $log->getCreatedAt()?->format('Y-m-d H:i:s'),
                ],
                $accessLogs
            ),
        ];
    }
}
