<?php

namespace App\Entity;

use App\Repository\ElementsRepository;
use App\Traits\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ElementsRepository::class)]
class Elements
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'validation.elements.name.not_blank')]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\ManyToOne(inversedBy: 'elements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lists $list = null;

    #[ORM\ManyToOne(inversedBy: 'parentElements')]
    private ?Lists $subList = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getList(): ?Lists
    {
        return $this->list;
    }

    public function setList(?Lists $list): static
    {
        $this->list = $list;

        return $this;
    }

    public function getSubList(): ?Lists
    {
        return $this->subList;
    }

    public function setSubList(?Lists $subList): static
    {
        $this->subList = $subList;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
