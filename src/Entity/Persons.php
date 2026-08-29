<?php

namespace App\Entity;

use App\Repository\PersonsRepository;
use App\Traits\TimestampTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A person in the face-searchable registry.
 *
 * Deliberately separate from Users: these records have no credentials, no roles
 * and no way to sign in. Their faces live in their own collection in the face
 * service, so a registry match can never be mistaken for a login.
 */
#[ORM\Entity(repositoryClass: PersonsRepository::class)]
#[ORM\Table(name: 'persons')]
#[ORM\Index(columns: ['last_name', 'first_name'], name: 'idx_persons_name')]
#[ORM\Index(columns: ['national_id'], name: 'idx_persons_national_id')]
#[ORM\HasLifecycleCallbacks]
class Persons
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'validation.person.first_name.required')]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'validation.person.last_name.required')]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $nationalId = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $idDocument = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\LessThanOrEqual('today', message: 'validation.person.birth_date.past')]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'validation.person.email.invalid')]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\ManyToOne(targetEntity: Cities::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Cities $city = null;

    #[ORM\ManyToOne(targetEntity: Countries::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Countries $country = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    /** Uploaded through the form; the file itself is stored via Files. */
    private ?UploadedFile $photoUpload = null;

    /** Set from the form to erase the photo and its face embedding. */
    private bool $removePhoto = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    public function getNationalId(): ?string
    {
        return $this->nationalId;
    }

    public function setNationalId(?string $nationalId): static
    {
        $this->nationalId = $nationalId;

        return $this;
    }

    public function getIdDocument(): ?string
    {
        return $this->idDocument;
    }

    public function setIdDocument(?string $idDocument): static
    {
        $this->idDocument = $idDocument;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getAge(): ?int
    {
        return $this->birthDate?->diff(new \DateTimeImmutable('today'))->y;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?Cities
    {
        return $this->city;
    }

    public function setCity(?Cities $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?Countries
    {
        return $this->country;
    }

    public function setCountry(?Countries $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getFullAddress(): string
    {
        return implode(', ', array_filter([
            $this->address,
            $this->city?->getName(),
            $this->country?->getName(),
        ]));
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getPhotoUpload(): ?UploadedFile
    {
        return $this->photoUpload;
    }

    public function setPhotoUpload(?UploadedFile $photoUpload): static
    {
        $this->photoUpload = $photoUpload;

        return $this;
    }

    public function isRemovePhoto(): bool
    {
        return $this->removePhoto;
    }

    public function setRemovePhoto(bool $removePhoto): static
    {
        $this->removePhoto = $removePhoto;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
