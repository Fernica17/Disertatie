<?php

namespace App\Command;

use App\Service\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:settings:clear-cache',
    description: 'Clear the application settings cache'
)]
class SettingsClearCacheCommand extends Command
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->settingsService->clearCache();

        $io->success('Settings cache cleared successfully.');

        return Command::SUCCESS;
    }
}
