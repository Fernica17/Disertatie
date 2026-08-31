<?php

namespace App\Service;

use App\Entity\FaceSearchReports;
use App\Entity\Files;
use App\Entity\Folders;
use App\Entity\Persons;
use App\Entity\Users;
use App\Enum\FolderType;
use App\Repository\FoldersRepository;
use App\Repository\PersonsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Environment;

/**
 * Turns a face search into something that outlives the page it happened on.
 *
 * The report keeps the searched frame and the ranking exactly as the recogniser
 * returned them, and writes a PDF into the document tree under
 * `<root>/<date>/<who searched>` so it can be found later without knowing an id.
 */
class FaceSearchReportService
{
    public const string ROOT_SLUG = 'rapoarte-cautare-fata';

    /**
     * Candidates below this are left out.
     *
     * Higher than the recogniser's own match threshold on purpose: in a registry
     * of a few thousand, scores around the match threshold are common between
     * strangers, so listing them would pad the report with noise.
     */
    public const float REPORT_THRESHOLD = 0.65;

    public const int MAX_CANDIDATES = 10;

    private const string STORAGE_SUBDIR = 'face-reports';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesUploadService $filesUploadService,
        private readonly FoldersRepository $foldersRepository,
        private readonly PersonsRepository $personsRepository,
        private readonly Environment $twig,
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array{matched: bool, userId: ?int, score: ?float, candidates: list<array{userId: int, score: float}>, ...} $identify
     */
    public function save(UploadedFile $frame, array $identify, Users $searchedBy, float $matchThreshold): FaceSearchReports
    {
        $report = new FaceSearchReports();
        $report->setSearchedBy($searchedBy);
        $report->setSearchedByName($searchedBy->getFullName());
        $report->setTopScore($identify['score']);
        $report->setMatchThreshold($matchThreshold);
        $report->setReportThreshold(self::REPORT_THRESHOLD);
        $report->setRegistrySize($this->personsRepository->count([]));
        $report->setCandidates($this->describeCandidates($identify['candidates']));

        if ($identify['matched'] && $identify['userId'] !== null) {
            $report->setMatchedPerson($this->personsRepository->find($identify['userId']));
        }

        $report->setFolder($this->folderFor($searchedBy));

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        // The upload needs the report to have an id before it can point at it
        $report->setQueryPhoto(
            $this->filesUploadService->upload($frame, $report, self::STORAGE_SUBDIR, Files::TYPE_FACE_SEARCH_QUERY),
        );
        $this->entityManager->flush();

        $this->attachPdf($report);

        return $report;
    }

    /**
     * Copies name and photo id into the report rather than referencing them.
     *
     * A report is a record of an answer given on a day; renaming or deleting a
     * person afterwards must not quietly rewrite what it says.
     *
     * The whole ranking is kept, including the ones below the listing threshold:
     * "nobody else came close" is itself a finding, and a report that silently
     * dropped them could not tell you that. Filtering happens at render time.
     *
     * @param list<array{userId: int, score: float}> $candidates
     *
     * @return list<array{personId: int, name: string, score: float, photoId: ?int}>
     */
    public function describeCandidates(array $candidates): array
    {
        $wanted = \array_slice($candidates, 0, self::MAX_CANDIDATES);

        if ($wanted === []) {
            return [];
        }

        $persons = $this->personsRepository->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', array_column($wanted, 'userId'))
            ->getQuery()
            ->getResult();

        /** @var array<int, Persons> $byId */
        $byId = [];
        foreach ($persons as $person) {
            $byId[$person->getId()] = $person;
        }

        $photos = $this->filesUploadService->getFilesForEntities(array_values($byId), Files::TYPE_PERSON_PHOTO);

        $rows = [];

        foreach ($wanted as $candidate) {
            $person = $byId[$candidate['userId']] ?? null;

            if ($person === null) {
                continue;
            }

            $photo = ($photos[$person->getId()] ?? [])[0] ?? null;

            $rows[] = [
                'personId' => $person->getId(),
                'name' => $person->getFullName(),
                'score' => round($candidate['score'], 4),
                'photoId' => $photo?->getId(),
            ];
        }

        return $rows;
    }

    /**
     * `<root>/<yyyy-mm-dd>/<who searched>`, created on first use.
     *
     * Built here rather than through FoldersService because that one refuses to
     * nest under a system folder, which is exactly what this tree does.
     */
    private function folderFor(Users $user): Folders
    {
        $root = $this->foldersRepository->findOneBy(['slug' => self::ROOT_SLUG, 'parent' => null])
            ?? $this->makeFolder('Rapoarte căutare după față', self::ROOT_SLUG, null, FolderType::SYSTEM, $user, 3);

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $dateFolder = $this->childBySlug($root, $today)
            ?? $this->makeFolder($today, $today, $root, FolderType::CUSTOM, $user);

        $name = $user->getFullName() ?: ($user->getEmail() ?? 'necunoscut');
        $slug = $this->slugger->slug(mb_strtolower($name))->toString();

        return $this->childBySlug($dateFolder, $slug)
            ?? $this->makeFolder($name, $slug, $dateFolder, FolderType::CUSTOM, $user);
    }

    private function childBySlug(Folders $parent, string $slug): ?Folders
    {
        return $this->foldersRepository->findOneBy(['parent' => $parent, 'slug' => $slug]);
    }

    private function makeFolder(
        string $name,
        string $slug,
        ?Folders $parent,
        FolderType $type,
        Users $createdBy,
        int $position = 0,
    ): Folders {
        $folder = new Folders();
        $folder->setName($name);
        $folder->setSlug($slug);
        $folder->setParent($parent);
        $folder->setType($type);
        $folder->setCreatedBy($createdBy);
        $folder->setPosition($position);

        $this->entityManager->persist($folder);
        $this->entityManager->flush();

        return $folder;
    }

    /**
     * Renders the PDF and files it next to the date and the searcher.
     *
     * A failure here must not lose the report: the record and its photo are
     * already saved, and the page renders from those, not from the PDF.
     */
    private function attachPdf(FaceSearchReports $report): void
    {
        try {
            $html = $this->twig->render('admin/face_reports/pdf.html.twig', [
                'report' => $report,
                'queryImage' => $this->dataUri($report->getQueryPhoto()),
                'candidateImages' => $this->candidateImages($report),
            ]);

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4');
            $dompdf->render();

            $temporary = tempnam(sys_get_temp_dir(), 'face_report_') . '.pdf';
            file_put_contents($temporary, $dompdf->output());

            $name = sprintf('raport-%s-%d.pdf', (new \DateTimeImmutable())->format('H-i-s'), $report->getId());
            $file = $this->filesUploadService->upload(
                new UploadedFile($temporary, $name, 'application/pdf', null, true),
                $report,
                self::STORAGE_SUBDIR,
                Files::TYPE_FACE_SEARCH_REPORT,
            );
            $file->setFolder($report->getFolder());

            $report->setPdfFile($file);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Face search report PDF failed', [
                'report_id' => $report->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Images have to travel inside the PDF: dompdf runs with remote fetching
     * off, so a URL would render as a broken box.
     *
     * @return array<int, string>
     */
    public function candidateImages(FaceSearchReports $report): array
    {
        $ids = array_values(array_filter(array_column($report->getCandidates(), 'photoId')));

        if ($ids === []) {
            return [];
        }

        $files = $this->entityManager->getRepository(Files::class)->findBy(['id' => $ids]);
        $images = [];

        foreach ($files as $file) {
            $uri = $this->dataUri($file);

            if ($uri !== null) {
                $images[$file->getId()] = $uri;
            }
        }

        return $images;
    }

    public function dataUri(?Files $file): ?string
    {
        if ($file === null) {
            return null;
        }

        $path = $this->filesUploadService->absolutePath($file);

        if ($path === null) {
            return null;
        }

        $bytes = @file_get_contents($path);

        if ($bytes === false) {
            return null;
        }

        return 'data:' . $file->getMimeType() . ';base64,' . base64_encode($bytes);
    }
}
