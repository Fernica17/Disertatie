<?php

namespace App\Entity;

use App\Repository\FaceSearchReportsRepository;
use App\Traits\TimestampTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A saved face search: the frame that was searched, and how the registry ranked
 * against it.
 *
 * The candidate list is stored as it was at the time rather than recomputed on
 * demand. A report is evidence of what the system answered on a given day; if
 * the registry changes afterwards, the report must not change with it.
 */
#[ORM\Entity(repositoryClass: FaceSearchReportsRepository::class)]
#[ORM\Table(name: 'face_search_reports')]
#[ORM\Index(columns: ['created_at'], name: 'idx_face_reports_created')]
#[ORM\HasLifecycleCallbacks]
class FaceSearchReports
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Who ran the search. Kept even if the account is later removed. */
    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Users $searchedBy = null;

    #[ORM\Column(length: 255)]
    private ?string $searchedByName = null;

    /** The frame that was searched, kept so the report can be read later. */
    #[ORM\ManyToOne(targetEntity: Files::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Files $queryPhoto = null;

    #[ORM\ManyToOne(targetEntity: Persons::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Persons $matchedPerson = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $topScore = null;

    /** The similarity the recogniser needed to call it a match. */
    #[ORM\Column(type: Types::FLOAT)]
    private float $matchThreshold = 0.0;

    /** The floor below which candidates were left out of the report. */
    #[ORM\Column(type: Types::FLOAT)]
    private float $reportThreshold = 0.0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $registrySize = 0;

    /**
     * Frozen ranking: name and photo id are copied in, not looked up, so the
     * report still reads correctly after a person is renamed or deleted.
     *
     * @var list<array{personId: int, name: string, score: float, photoId: ?int, deleted?: bool}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $candidates = [];

    /** The PDF written into the folder tree. */
    #[ORM\ManyToOne(targetEntity: Files::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Files $pdfFile = null;

    #[ORM\ManyToOne(targetEntity: Folders::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Folders $folder = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSearchedBy(): ?Users
    {
        return $this->searchedBy;
    }

    public function setSearchedBy(?Users $searchedBy): static
    {
        $this->searchedBy = $searchedBy;

        return $this;
    }

    public function getSearchedByName(): ?string
    {
        return $this->searchedByName;
    }

    public function setSearchedByName(string $searchedByName): static
    {
        $this->searchedByName = $searchedByName;

        return $this;
    }

    public function getQueryPhoto(): ?Files
    {
        return $this->queryPhoto;
    }

    public function setQueryPhoto(?Files $queryPhoto): static
    {
        $this->queryPhoto = $queryPhoto;

        return $this;
    }

    public function getMatchedPerson(): ?Persons
    {
        return $this->matchedPerson;
    }

    public function setMatchedPerson(?Persons $matchedPerson): static
    {
        $this->matchedPerson = $matchedPerson;

        return $this;
    }

    public function getTopScore(): ?float
    {
        return $this->topScore;
    }

    public function setTopScore(?float $topScore): static
    {
        $this->topScore = $topScore;

        return $this;
    }

    public function getMatchThreshold(): float
    {
        return $this->matchThreshold;
    }

    public function setMatchThreshold(float $matchThreshold): static
    {
        $this->matchThreshold = $matchThreshold;

        return $this;
    }

    public function getReportThreshold(): float
    {
        return $this->reportThreshold;
    }

    public function setReportThreshold(float $reportThreshold): static
    {
        $this->reportThreshold = $reportThreshold;

        return $this;
    }

    public function getRegistrySize(): int
    {
        return $this->registrySize;
    }

    public function setRegistrySize(int $registrySize): static
    {
        $this->registrySize = $registrySize;

        return $this;
    }

    /**
     * @return list<array{personId: int, name: string, score: float, photoId: ?int, deleted?: bool}>
     */
    public function getCandidates(): array
    {
        return $this->candidates;
    }

    /**
     * @param list<array{personId: int, name: string, score: float, photoId: ?int, deleted?: bool}> $candidates
     */
    public function setCandidates(array $candidates): static
    {
        $this->candidates = $candidates;

        return $this;
    }

    public function getPdfFile(): ?Files
    {
        return $this->pdfFile;
    }

    public function setPdfFile(?Files $pdfFile): static
    {
        $this->pdfFile = $pdfFile;

        return $this;
    }

    public function getFolder(): ?Folders
    {
        return $this->folder;
    }

    public function setFolder(?Folders $folder): static
    {
        $this->folder = $folder;

        return $this;
    }

    public function isMatched(): bool
    {
        return $this->matchedPerson !== null;
    }
}
