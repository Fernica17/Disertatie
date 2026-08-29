<?php

namespace App\Entity;

use App\Repository\CountriesRepository;
use App\Traits\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: CountriesRepository::class)]
class Countries
{
    use TimestampTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $code = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column]
    private ?bool $isDefault = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Slug(fields: ['name'], updatable: false)]
    private ?string $slug = null;

    /**
     * @var Collection<int, Counties>
     */
    #[ORM\OneToMany(targetEntity: Counties::class, mappedBy: 'country')]
    private Collection $counties;

    public function __construct()
    {
        $this->counties = new ArrayCollection();
        $this->isActive = true;
        $this->isDefault = false;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

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

    public function isDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

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
     * @return Collection<int, Counties>
     */
    public function getCounties(): Collection
    {
        return $this->counties;
    }

    public function addCounty(Counties $county): static
    {
        if (!$this->counties->contains($county)) {
            $this->counties->add($county);
            $county->setCountry($this);
        }

        return $this;
    }

    public function removeCounty(Counties $county): static
    {
        if ($this->counties->removeElement($county)) {
            // set the owning side to null (unless already changed)
            if ($county->getCountry() === $this) {
                $county->setCountry(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
