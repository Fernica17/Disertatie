<?php

namespace App\Entity;

use App\Enum\FolderType;
use App\Repository\FoldersRepository;
use App\Traits\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FoldersRepository::class)]
#[ORM\Table(name: 'folders')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_folders_parent')]
#[ORM\Index(columns: ['type'], name: 'idx_folders_type')]
#[ORM\Index(columns: ['company_id'], name: 'idx_folders_company')]
#[ORM\UniqueConstraint(name: 'uniq_folder_parent_slug', columns: ['parent_id', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class Folders
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\Column(length: 20, enumType: FolderType::class)]
    private ?FolderType $type = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $systemMapping = null;

    #[ORM\ManyToOne(targetEntity: Companies::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Companies $company = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Users $createdBy = null;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    /** @var Collection<int, Files> */
    #[ORM\OneToMany(targetEntity: Files::class, mappedBy: 'folder')]
    private Collection $files;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->files = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getType(): ?FolderType
    {
        return $this->type;
    }

    public function setType(FolderType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSystemMapping(): ?string
    {
        return $this->systemMapping;
    }

    public function setSystemMapping(?string $systemMapping): static
    {
        $this->systemMapping = $systemMapping;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getSystemMappingArray(): array
    {
        if ($this->systemMapping === null || $this->systemMapping === '') {
            return [];
        }

        return array_map('trim', explode(',', $this->systemMapping));
    }

    public function getCompany(): ?Companies
    {
        return $this->company;
    }

    public function setCompany(?Companies $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getCreatedBy(): ?Users
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Users $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @return Collection<int, Files>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function isSystem(): bool
    {
        return $this->type === FolderType::SYSTEM;
    }

    public function isCustom(): bool
    {
        return $this->type === FolderType::CUSTOM;
    }

    public function isClient(): bool
    {
        return $this->type === FolderType::CLIENT;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
