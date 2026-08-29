<?php

namespace App\Command;

use App\Service\Audit\AuditService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:audit:cleanup',
    description: 'Clean up old audit log entries based on retention policy',
)]
class CleanupAuditLogsCommand extends Command
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly LoggerInterface $logger,
        #[Autowire('%audit.retention_days%')]
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->info(sprintf('Cleaning up audit logs older than %d days...', $this->retentionDays));

            $authDeleted = $this->auditService->cleanupOldAuthenticationLogs();
            $accessDeleted = $this->auditService->cleanupOldAccessLogs();
            $changeDeleted = $this->auditService->cleanupOldEntityChangeLogs();

            $total = $authDeleted + $accessDeleted + $changeDeleted;

            $io->text(sprintf('Deleted %d authentication log entries.', $authDeleted));
            $io->text(sprintf('Deleted %d access log entries.', $accessDeleted));
            $io->text(sprintf('Deleted %d entity change log entries.', $changeDeleted));
            $io->success(sprintf('Total: %d entries removed.', $total));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Audit cleanup failed', [
                'error' => $e->getMessage(),
            ]);

            $io->error('Cleanup failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
