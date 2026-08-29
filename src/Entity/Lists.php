<?php

namespace App\Entity;

use App\Repository\ListsRepository;
use App\Traits\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ListsRepository::class)]
class Lists
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'validation.name.required')]
    private ?string $name = null;

    #[Gedmo\Slug(fields: ['name'])]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    /**
     * @var Collection<int, Elements>
     */
    #[ORM\OneToMany(targetEntity: Elements::class, mappedBy: 'list', cascade: ['persist'])]
    #[Assert\Valid]
    private Collection $elements;

    /**
     * @var Collection<int, Elements>
     */
    #[ORM\OneToMany(targetEntity: Elements::class, mappedBy: 'subList')]
    private Collection $parentElements;

    public function __construct()
    {
        $this->elements = new ArrayCollection();
        $this->parentElements = new ArrayCollection();
    }

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return Collection<int, Elements>
     */
    public function getElements(): Collection
    {
        return $this->elements;
    }

    public function addElement(Elements $element): static
    {
        if (!$this->elements->contains($element)) {
            $this->elements->add($element);
            $element->setList($this);
        }

        return $this;
    }

    public function removeElement(Elements $element): static
    {
        if ($this->elements->removeElement($element)) {
            // set the owning side to null (unless already changed)
            if ($element->getList() === $this) {
                $element->setList(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Elements>
     */
    public function getParentElements(): Collection
    {
        return $this->parentElements;
    }

    public function addParentElement(Elements $parentElement): static
    {
        if (!$this->parentElements->contains($parentElement)) {
            $this->parentElements->add($parentElement);
            $parentElement->setSubList($this);
        }

        return $this;
    }

    public function removeParentElement(Elements $parentElement): static
    {
        if ($this->parentElements->removeElement($parentElement)) {
            // set the owning side to null (unless already changed)
            if ($parentElement->getSubList() === $this) {
                $parentElement->setSubList(null);
            }
        }

        return $this;
    }

    public function getParentFormat(): string
    {
        $parents = $this->getParentElements();
        if ($parents->isEmpty()) {
            return '-';
        }

        return implode(', ', $parents->map(fn ($p) => $p->getName())->toArray());
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
