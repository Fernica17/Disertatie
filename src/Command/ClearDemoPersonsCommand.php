<?php

namespace App\Command;

use App\Entity\Persons;
use App\Repository\PersonsRepository;
use App\Service\PersonsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Empties the person registry.
 *
 * The bulk fixture appends, so a second run without this would double every
 * record. Deletion goes through PersonsService rather than SQL so the stored
 * photo and the face embedding go with the row instead of being orphaned.
 */
#[AsCommand(
    name: 'app:demo:clear-persons',
    description: 'Deletes registry persons, their photos and their face data',
)]
class ClearDemoPersonsCommand extends Command
{
    public function __construct(
        private readonly PersonsRepository $persons,
        private readonly PersonsService $personsService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('keep', null, InputOption::VALUE_REQUIRED, 'Spare the first N records by id', '0')
            ->addOption('ids', null, InputOption::VALUE_REQUIRED, 'Delete only these ids, comma separated')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Delete without asking');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keep = max(0, (int) $input->getOption('keep'));

        /** @var list<Persons> $all */
        $all = $this->persons->createQueryBuilder('p')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        $only = $input->getOption('ids');

        if (\is_string($only) && $only !== '') {
            $wanted = array_map('intval', explode(',', $only));
            $doomed = array_values(array_filter(
                $all,
                static fn (Persons $p): bool => \in_array($p->getId(), $wanted, true),
            ));
        } else {
            $doomed = \array_slice($all, $keep);
        }

        if ($doomed === []) {
            $io->success('Nothing to delete.');

            return Command::SUCCESS;
        }

        $io->warning(sprintf(
            'About to delete %d of %d registry persons, with their photos and face data.',
            \count($doomed),
            \count($all),
        ));

        if (!$input->getOption('force') && !$io->confirm('Continue?', false)) {
            $io->text('Cancelled.');

            return Command::SUCCESS;
        }

        $io->progressStart(\count($doomed));

        foreach ($doomed as $person) {
            $this->personsService->delete($person);
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('%d deleted, %d kept.', \count($doomed), $keep));

        return Command::SUCCESS;
    }
}
