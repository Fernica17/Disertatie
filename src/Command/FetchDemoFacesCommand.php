<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fetches demo faces for the person registry from Labeled Faces in the Wild.
 *
 * The photos stay under var/, out of the repository: a few thousand pictures of
 * real people is not something to carry in git, and the archive can be pulled
 * again whenever it is needed.
 *
 * One image per person, because the registry holds one record per person: two
 * pictures of the same face would give the search two equally good answers.
 */
#[AsCommand(
    name: 'app:demo:fetch-faces',
    description: 'Downloads LFW portraits into var/demo-faces for the person fixtures',
)]
class FetchDemoFacesCommand extends Command
{
    private const string ARCHIVE_URL = 'https://huggingface.co/datasets/DerrickUnleashed/LFW/resolve/main/lfw.tgz';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'How many portraits to extract', '3000')
            ->addOption('keep-archive', null, InputOption::VALUE_NONE, 'Keep the downloaded archive for a future run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = max(1, (int) $input->getOption('count'));
        $base = $this->projectDir . '/var/demo-faces';
        $photos = $base . '/photos';
        $archive = $base . '/lfw.tgz';

        if (!is_dir($photos) && !mkdir($photos, 0o775, true) && !is_dir($photos)) {
            $io->error(sprintf('Could not create %s.', $photos));

            return Command::FAILURE;
        }

        $already = glob($photos . '/*.jpg') ?: [];

        if (\count($already) >= $count) {
            $io->success(sprintf('%d portraits already in %s. Nothing to do.', \count($already), $photos));

            return Command::SUCCESS;
        }

        if (!is_file($archive)) {
            $io->section('Download');
            $io->text(sprintf('%s (about 172 MB)', self::ARCHIVE_URL));

            if (!$this->download(self::ARCHIVE_URL, $archive, $io)) {
                return Command::FAILURE;
            }
        }

        $io->section('Extract');
        $extracted = $this->extract($archive, $photos, $count, $io);

        if ($extracted === 0) {
            $io->error('The archive yielded no portraits. It may be truncated; delete it and run again.');

            return Command::FAILURE;
        }

        if (!$input->getOption('keep-archive')) {
            @unlink($archive);
        }

        $io->success(sprintf(
            '%d portraits in %s. Load them with: bin/console doctrine:fixtures:load --group=persons-bulk --append',
            $extracted,
            $photos,
        ));

        return Command::SUCCESS;
    }

    private function download(string $url, string $target, SymfonyStyle $io): bool
    {
        $partial = $target . '.part';
        $source = @fopen($url, 'rb');

        if ($source === false) {
            $io->error('Could not reach the archive. Check the network and try again.');

            return false;
        }

        $sink = fopen($partial, 'wb');
        $io->progressStart();
        $bytes = 0;

        while (!feof($source)) {
            $chunk = fread($source, 1 << 20);

            if ($chunk === false) {
                break;
            }

            fwrite($sink, $chunk);
            $bytes += \strlen($chunk);
            $io->progressAdvance();
        }

        fclose($source);
        fclose($sink);
        $io->progressFinish();

        if ($bytes < 1 << 20) {
            @unlink($partial);
            $io->error('The download was empty or truncated.');

            return false;
        }

        rename($partial, $target);
        $io->text(sprintf('%d MB downloaded.', intdiv($bytes, 1 << 20)));

        return true;
    }

    /**
     * Walks the archive and keeps the first portrait of each person.
     *
     * The stream is read once from start to finish rather than unpacked whole,
     * so extracting a few thousand faces never costs the full 13k on disk.
     */
    private function extract(string $archive, string $photos, int $count, SymfonyStyle $io): int
    {
        $handle = @gzopen($archive, 'rb');

        if ($handle === false) {
            $io->error('Could not open the archive.');

            return 0;
        }

        $seen = [];
        $written = 0;
        $io->progressStart($count);

        while (($header = gzread($handle, 512)) !== false && \strlen($header) === 512) {
            $name = trim(substr($header, 0, 100), "\0");

            if ($name === '') {
                continue;
            }

            $size = (int) octdec(trim(substr($header, 124, 12), "\0 "));
            $padded = $size % 512 === 0 ? $size : $size + (512 - $size % 512);

            // lfw/<Person_Name>/<Person_Name>_0001.jpg
            $person = basename(\dirname($name));
            $wanted = $written < $count
                && str_ends_with($name, '.jpg')
                && !isset($seen[$person]);

            if (!$wanted) {
                if ($padded > 0) {
                    gzseek($handle, $padded, \SEEK_CUR);
                }

                continue;
            }

            $body = $size > 0 ? gzread($handle, $padded) : '';

            if ($padded > $size) {
                $body = substr($body, 0, $size);
            }

            file_put_contents($photos . '/' . $this->safeName($person) . '.jpg', $body);
            $seen[$person] = true;
            ++$written;
            $io->progressAdvance();

            if ($written >= $count) {
                break;
            }
        }

        gzclose($handle);
        $io->progressFinish();

        return $written;
    }

    /**
     * Keeps the archive's own spelling: the directory name is the person in the
     * picture, and the fixture reads the record's name back out of it. Lowercase
     * it here and "McDonald" would come back as "Mcdonald".
     */
    private function safeName(string $person): string
    {
        return trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $person) ?? $person, '_');
    }
}
