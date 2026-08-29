<?php

namespace App\Entity;

use App\Repository\FilesRepository;
use App\Traits\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FilesRepository::class)]
#[ORM\Table(name: 'files')]
#[ORM\Index(columns: ['entity', 'entity_id'], name: 'idx_files_entity')]
#[ORM\Index(columns: ['type'], name: 'idx_files_type')]
#[ORM\Index(columns: ['folder_id'], name: 'idx_files_folder')]
#[ORM\HasLifecycleCallbacks]
class Files
{
    use TimestampTrait;

    public const TYPE_DEFAULT = 'default';
    public const TYPE_COMPANIES_LOGO = 'companies_logo';
    public const TYPE_USER_AVATAR = 'user_avatar';
    public const TYPE_ORDER_ATTACHMENT = 'order_attachment';
    public const TYPE_OFFER_ATTACHMENT = 'offer_attachment';
    public const TYPE_OFFER_PDF = 'offer_pdf';
    public const TYPE_COMPANIES_DOCUMENT = 'companies_document';
    public const TYPE_CONTRACT_DOCUMENT = 'contract_document';
    public const TYPE_CONTRACT_ADDENDUM = 'contract_addendum';
    public const TYPE_EQUIPMENT_DOCUMENT = 'equipment_document';
    public const TYPE_EQUIPMENT_RECORD = 'equipment_record';
    public const TYPE_STOCK_LOT_CERTIFICATE = 'stock_lot_certificate';
    public const TYPE_TEST_DOCUMENT = 'test_document';
    public const TYPE_TEST_BULLETIN = 'test_bulletin';
    public const TYPE_PROJECT_DOCUMENT = 'project_document';
    public const TYPE_PROJECT_PHASE_DOCUMENT = 'project_phase_document';

    public const TYPE_CORRESPONDENCE_DOCUMENT = 'correspondence_document';
    public const TYPE_FOLDER_DOCUMENT = 'folder_document';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $originalName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $mimeType = null;

    #[ORM\Column]
    private ?int $size = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private string $type = self::TYPE_DEFAULT;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $entity = null;

    #[ORM\Column]
    private ?int $entityId = null;

    #[ORM\ManyToOne(targetEntity: Folders::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Folders $folder = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;

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

    public function getFormattedSize(): string
    {
        if ($this->size === null) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            ++$unitIndex;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    public function __toString(): string
    {
        return $this->originalName ?? '';
    }
}
